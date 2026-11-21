<?php

namespace App\Services;

use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Exceptions\RentalNotExtendableException;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Results\RentalQuote;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rental use cases: a tenant's rentals, quoting and booking a garage, and an admin extending a
 * running rental. Conflicts tied to a form field are thrown as ValidationException so the message
 * appears under that field.
 */
class RentalService
{
    /** vehicle_id value meaning "register a new vehicle with this rental". */
    public const NEW_VEHICLE = 'new';

    /** Format of start_date and new_end_date in the input arrays. */
    public const DATE_FORMAT = 'Y-m-d';

    private const RENTALS_PER_PAGE = 10;

    private const ACTIVE_RENTALS_PER_PAGE = 15;

    /**
     * The tenant's rentals, newest start first, with extension count and extension costs.
     *
     * @return LengthAwarePaginator<int, Rental>
     */
    public function rentalsOf(User $tenant): LengthAwarePaginator
    {
        return $tenant->rentals()
            ->with(['garage', 'vehicle'])
            ->withCount('extensions')
            ->withSum('extensions', 'additional_cost')
            ->latest('start_date')
            ->paginate(self::RENTALS_PER_PAGE);
    }

    /**
     * Everything shown before paying: the period, and an unsaved rental with its total for every
     * garage free for the whole period, per garage type the vehicle fits.
     *
     * @param  array<string, mixed>  $details  validated step 1 (vehicle, rental_type, start_date, days, battery)
     *
     * @throws ValidationException when the vehicle already has a garage in that period (vehicle_id)
     */
    public function quote(User $tenant, array $details): RentalQuote
    {
        $vehicle = $this->vehicleFor($tenant, $details);
        $start = $this->startDate($details);
        $end = $this->endDate($details);
        $this->ensureVehicleIsFree($vehicle, $start, $end);

        $offers = [];
        foreach ($vehicle->allowedGarageTypes() as $garageType) {
            $offers[$garageType->value] = Garage::query()
                ->where('type', $garageType)
                ->availableBetween($start, $end)
                ->orderBy('code')
                ->get()
                ->map(fn (Garage $garage) => $this->draftRental($garage, $vehicle, $details));
        }

        return new RentalQuote(
            $vehicle,
            RentalType::from($details['rental_type']),
            $start,
            $end,
            Rental::countDays($start, $end),
            $offers,
        );
    }

    /**
     * Book the garage: lock it (and an existing vehicle) so two requests cannot take it at the same
     * moment, re-check everything, save a new vehicle, store the payment proof and save the rental.
     *
     * @param  array<string, mixed>  $details  validated step 1
     *
     * @throws ValidationException when the garage does not fit the vehicle (garage_type), the garage
     *                             was just taken (garage_id) or the vehicle is busy (vehicle_id)
     */
    public function book(User $tenant, array $details, int $garageId, UploadedFile $paymentProof): Rental
    {
        $vehicle = $this->vehicleFor($tenant, $details);

        return DB::transaction(function () use ($tenant, $vehicle, $details, $garageId, $paymentProof) {
            $garage = Garage::query()->lockForUpdate()->findOrFail($garageId);

            if (! $vehicle->canParkIn($garage->type)) {
                throw ValidationException::withMessages([
                    'garage_type' => 'Tipe garasi ini tidak cocok untuk kendaraan Anda.',
                ]);
            }

            $draft = $this->draftRental($garage, $vehicle, $details);
            $this->ensureGarageIsFree($garage, $draft->start_date, $draft->end_date);

            if ($vehicle->exists) {
                Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
                $this->ensureVehicleIsFree($vehicle, $draft->start_date, $draft->end_date);
            } else {
                $tenant->vehicles()->save($vehicle);
                $draft->vehicle()->associate($vehicle);
            }

            $draft->payment_proof = $paymentProof->store(Rental::PAYMENT_PROOF_DIRECTORY, Rental::PAYMENT_PROOF_DISK);
            // The vehicle links the rental to the tenant; rentals have no user_id of their own.
            $draft->save();

            return $draft;
        });
    }

    /**
     * Rentals covering today, ending soonest first, for the admin's extension list.
     *
     * @return LengthAwarePaginator<int, Rental>
     */
    public function activeToday(): LengthAwarePaginator
    {
        return Rental::query()
            ->activeOn(CarbonImmutable::today())
            ->with(['garage', 'vehicle.user'])
            ->withCount('extensions')
            ->orderBy('end_date')
            ->orderBy('id')
            ->paginate(self::ACTIVE_RENTALS_PER_PAGE);
    }

    /**
     * Load a running rental with what the extension form shows (garage, tenant, earlier extensions).
     *
     * @throws RentalNotExtendableException when the rental does not cover today
     */
    public function prepareExtension(Rental $rental): Rental
    {
        $this->ensureExtendable($rental);

        return $rental->load(['garage', 'vehicle.user', 'extensions']);
    }

    /**
     * An unsaved extension with the days added, the new end date and the remaining cost.
     *
     * @param  array<string, mixed>  $input  validated: extension_type, and new_end_date (daily) or months (monthly)
     *
     * @throws RentalNotExtendableException when the rental does not cover today
     * @throws ValidationException when the added days clash with another booking (new_end_date or months)
     */
    public function quoteExtension(Rental $rental, array $input): RentalExtension
    {
        $this->ensureExtendable($rental);
        $rental->load(['garage', 'vehicle.user']);

        $extension = $this->draftExtension($rental, $input);
        $this->ensureExtraDaysAreFree($rental, $extension, $this->extensionField($input));

        return $extension;
    }

    /**
     * Save the extension and move the rental's end date. The rental is locked and the extension
     * rebuilt from it, because another admin may have just extended the same rental.
     *
     * @param  array<string, mixed>  $input  validated: extension_type, and new_end_date (daily) or months (monthly)
     *
     * @throws RentalNotExtendableException when the rental does not cover today
     * @throws ValidationException when the extension no longer fits (new_end_date or months)
     */
    public function extend(Rental $rental, array $input, UploadedFile $paymentProof): RentalExtension
    {
        $this->ensureExtendable($rental);
        $field = $this->extensionField($input);

        return DB::transaction(function () use ($rental, $input, $paymentProof, $field) {
            $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);
            Garage::query()->lockForUpdate()->findOrFail($lockedRental->garage_id);

            $extension = $this->draftExtension($lockedRental, $input);

            if ($extension->days < 1) {
                throw ValidationException::withMessages([
                    $field => 'Sewa ini baru saja diperpanjang. Muat ulang halaman dan pilih tanggal lagi.',
                ]);
            }

            $this->ensureExtraDaysAreFree($lockedRental, $extension, $field);

            $extension->payment_proof = $paymentProof->store(Rental::PAYMENT_PROOF_DIRECTORY, Rental::PAYMENT_PROOF_DISK);
            $extension->save();
            $lockedRental->update(['end_date' => $extension->new_end_date]);

            return $extension;
        });
    }

    /**
     * The tenant's chosen vehicle, or a new unsaved one of the right class built from the form.
     *
     * @param  array<string, mixed>  $details
     */
    private function vehicleFor(User $tenant, array $details): Vehicle
    {
        if ($details['vehicle_id'] !== self::NEW_VEHICLE) {
            return $tenant->vehicles()->findOrFail($details['vehicle_id']);
        }

        return Vehicle::forType(VehicleType::from($details['vehicle_type']))->fill([
            'plate_number' => $details['plate_number'] ?? null,
            'brand_model' => $details['brand_model'],
            'battery_capacity_kwh' => $details['battery_capacity_kwh'] ?? null,
        ]);
    }

    /**
     * Unsaved rental with dates and costs for this garage and vehicle.
     *
     * @param  array<string, mixed>  $details
     */
    private function draftRental(Garage $garage, Vehicle $vehicle, array $details): Rental
    {
        return Rental::draft(
            $garage,
            $vehicle,
            RentalType::from($details['rental_type']),
            $this->startDate($details),
            isset($details['days']) ? (int) $details['days'] : null,
            isset($details['current_battery_kwh']) ? (float) $details['current_battery_kwh'] : null,
        );
    }

    /**
     * A garage holds one vehicle: no other rental may share a day with the period.
     *
     * @throws ValidationException
     */
    private function ensureGarageIsFree(Garage $garage, CarbonInterface $start, CarbonInterface $end): void
    {
        $isFree = Garage::query()->whereKey($garage->id)->availableBetween($start, $end)->exists();

        if (! $isFree) {
            throw ValidationException::withMessages([
                'garage_id' => "Garasi {$garage->code} baru saja disewa orang lain untuk tanggal tersebut. Pilih garasi lain.",
            ]);
        }
    }

    /**
     * One vehicle can only be in one garage at a time.
     *
     * @throws ValidationException when the vehicle already has a garage for any day of the period
     */
    private function ensureVehicleIsFree(Vehicle $vehicle, CarbonInterface $start, CarbonInterface $end): void
    {
        if ($vehicle->exists && $vehicle->isParkedBetween($start, $end)) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Kendaraan ini sudah berada di garasi lain pada tanggal tersebut.',
            ]);
        }
    }

    /**
     * Only a rental that covers today can be extended.
     *
     * @throws RentalNotExtendableException
     */
    private function ensureExtendable(Rental $rental): void
    {
        if (! $rental->isActiveOn(CarbonImmutable::today())) {
            throw RentalNotExtendableException::notActiveToday();
        }
    }

    /**
     * The added days must not clash with the next booking of the garage or with another
     * garage the vehicle is booked in.
     *
     * @param  string  $field  input that receives the error message
     *
     * @throws ValidationException
     */
    private function ensureExtraDaysAreFree(Rental $rental, RentalExtension $extension, string $field): void
    {
        $firstExtraDay = $extension->firstExtraDay();
        $nextBooking = $rental->garage->rentals()
            ->whereKeyNot($rental->id)
            ->overlapping($firstExtraDay, $extension->new_end_date)
            ->orderBy('start_date')
            ->first();

        if ($nextBooking !== null) {
            $latestEnd = $nextBooking->start_date->subDay()->translatedFormat('d M Y');

            throw ValidationException::withMessages([
                $field => "Garasi {$rental->garage->code} sudah dipesan mulai {$nextBooking->start_date->translatedFormat('d M Y')}. "
                    ."Perpanjangan paling lama sampai {$latestEnd}.",
            ]);
        }

        if ($rental->vehicle->isParkedBetween($firstExtraDay, $extension->new_end_date, $rental)) {
            throw ValidationException::withMessages([
                $field => 'Kendaraan ini sudah dipesan di garasi lain pada tanggal perpanjangan.',
            ]);
        }
    }

    /**
     * Unsaved extension from validated input.
     *
     * @param  array<string, mixed>  $input
     */
    private function draftExtension(Rental $rental, array $input): RentalExtension
    {
        $newEndDate = isset($input['new_end_date'])
            ? CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $input['new_end_date'])
            : null;

        return RentalExtension::draftFor(
            $rental,
            RentalType::from($input['extension_type']),
            $newEndDate,
            isset($input['months']) ? (int) $input['months'] : null,
        );
    }

    /**
     * The field the admin filled in: the new end date (daily) or the months (monthly).
     *
     * @param  array<string, mixed>  $input
     */
    private function extensionField(array $input): string
    {
        return $input['extension_type'] === RentalType::Monthly->value ? 'months' : 'new_end_date';
    }

    /**
     * First rented day from validated input.
     *
     * @param  array<string, mixed>  $details
     */
    private function startDate(array $details): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $details['start_date']);
    }

    /**
     * Last rented day from validated input (inclusive).
     *
     * @param  array<string, mixed>  $details
     */
    private function endDate(array $details): CarbonImmutable
    {
        $days = isset($details['days']) ? (int) $details['days'] : null;

        return Rental::calculateEndDate(RentalType::from($details['rental_type']), $this->startDate($details), $days);
    }
}
