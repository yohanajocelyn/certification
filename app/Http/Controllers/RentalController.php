<?php

namespace App\Http\Controllers;

use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Tenant pages: my rentals, and renting a garage in two steps
 * (1. vehicle and dates, 2. garage, total and payment proof).
 */
class RentalController extends Controller
{
    /** Value of the vehicle choice that means "register a new vehicle". */
    public const NEW_VEHICLE = 'new';

    private const RENTALS_PER_PAGE = 10;

    private const DATE_FORMAT = 'Y-m-d';

    /**
     * List the tenant's own rentals, newest start first.
     */
    public function index(Request $request): View
    {
        $rentals = $request->user()->rentals()
            ->with(['garage', 'vehicle'])
            ->withCount('extensions')
            ->withSum('extensions', 'additional_cost')
            ->latest('start_date')
            ->paginate(self::RENTALS_PER_PAGE);

        return view('rentals.index', ['rentals' => $rentals, 'today' => CarbonImmutable::today()]);
    }

    /**
     * Step 1: choose a vehicle, rental type, start date and number of days.
     * Query values (from the "change" link on step 2) refill the form.
     */
    public function create(Request $request): View
    {
        $vehicles = $request->user()->vehicles()->orderBy('brand_model')->get();
        $typesWhere = fn (callable $rule) => array_values(array_map(
            fn (VehicleType $type) => $type->value,
            array_filter(VehicleType::cases(), fn (VehicleType $type) => $rule(Vehicle::forType($type))),
        ));

        return view('rentals.create', [
            'vehicles' => $vehicles,
            'vehicleTypes' => VehicleType::cases(),
            'plateVehicleTypes' => $typesWhere(fn (Vehicle $vehicle) => $vehicle->requiresPlate()),
            'batteryVehicleTypes' => $typesWhere(fn (Vehicle $vehicle) => $vehicle->hasBattery()),
            'electricVehicleIds' => $vehicles->filter(fn (Vehicle $vehicle) => $vehicle->hasBattery())->pluck('id')->all(),
            'rentalTypes' => RentalType::cases(),
            'today' => CarbonImmutable::today(),
            'startDate' => $this->requestedStartDate($request),
            'initialEndDate' => $this->requestedEndDate($request),
            'defaults' => $request->query(),
        ]);
    }

    /**
     * Step 2: show the end date, the garages free for the whole period and each one's total.
     */
    public function preview(Request $request): View
    {
        try {
            [$details, $vehicle] = $this->validateRentalDetails($request);
        } catch (ValidationException $exception) {
            // Always back to step 1: "back" could be this same page and loop when saving failed.
            throw $exception->redirectTo(route('rentals.create'));
        }

        $start = $this->startDate($details);
        $end = $this->endDate($details);

        $offers = [];
        foreach ($vehicle->allowedGarageTypes() as $garageType) {
            $offers[$garageType->value] = Garage::query()
                ->where('type', $garageType)
                ->availableBetween($start, $end)
                ->orderBy('code')
                ->get()
                ->map(fn (Garage $garage) => $this->draftRental($garage, $vehicle, $details));
        }

        return view('rentals.preview', [
            'details' => $details,
            'vehicle' => $vehicle,
            'rentalType' => RentalType::from($details['rental_type']),
            'start' => $start,
            'end' => $end,
            'durationDays' => Rental::countDays($start, $end),
            'offers' => $offers,
        ]);
    }

    /**
     * Save the rental: re-check everything, lock the garage so nobody takes it at the same moment,
     * store the payment proof and create the vehicle if it is new.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user();
        [$details, $vehicle] = $this->validateRentalDetails($request);
        $allowedGarageTypes = array_map(fn (GarageType $type) => $type->value, $vehicle->allowedGarageTypes());

        $choice = $request->validate([
            'garage_type' => ['required', Rule::in($allowedGarageTypes)],
            'garage_id' => ['required', 'integer', Rule::exists('garages', 'id')->where('type', $request->input('garage_type'))],
            'payment_proof' => ['required', 'image', 'mimes:'.implode(',', Rental::PAYMENT_PROOF_MIMES), 'max:'.Rental::PAYMENT_PROOF_MAX_KB],
        ]);

        $rental = DB::transaction(
            fn () => $this->saveRental($tenant, $vehicle, $details, (int) $choice['garage_id'], $choice['payment_proof'])
        );

        return redirect()->route('rentals.index')
            ->with('success', "Sewa garasi {$rental->garage->code} berhasil disimpan.");
    }

    /**
     * Validate step 1 (sent again with step 2) and make sure the vehicle is free for the period.
     *
     * @return array{0: array<string, mixed>, 1: Vehicle} the validated input and the chosen (or new, unsaved) vehicle
     *
     * @throws ValidationException
     */
    private function validateRentalDetails(Request $request): array
    {
        if ($request->has('plate_number')) {
            $request->merge(['plate_number' => Vehicle::normalizePlate($request->input('plate_number'))]);
        }

        $details = $request->validate($this->rentalDetailsRules($request, $request->user()));
        $vehicle = $this->resolveVehicle($details, $request->user());
        $this->ensureVehicleIsFree($vehicle, $this->startDate($details), $this->endDate($details));

        return [$details, $vehicle];
    }

    /**
     * Step 1 rules. Fields that do not apply (plate of a bicycle, battery of a petrol car,
     * days of a monthly rental, new-vehicle fields for an existing vehicle) are dropped.
     *
     * @return array<string, mixed>
     */
    private function rentalDetailsRules(Request $request, User $tenant): array
    {
        $ownVehicles = $tenant->vehicles()->get()->keyBy('id');
        $isNewVehicle = $request->input('vehicle_id') === self::NEW_VEHICLE;
        $newVehicleType = $isNewVehicle ? VehicleType::tryFrom((string) $request->input('vehicle_type')) : null;
        // A new vehicle's rules come from its class before it exists: Vehicle::forType(...)->requiresPlate().
        $newVehicle = $newVehicleType ? Vehicle::forType($newVehicleType) : null;
        $existingVehicle = $isNewVehicle ? null : $ownVehicles->get($request->input('vehicle_id'));
        $hasBattery = ($newVehicle ?? $existingVehicle)?->hasBattery() ?? false;
        $newWithPlate = $isNewVehicle && ($newVehicle?->requiresPlate() ?? true);
        $newWithBattery = $isNewVehicle && $hasBattery;
        $isDaily = $request->input('rental_type') === RentalType::Daily->value;
        $kwhRules = ['numeric', 'decimal:0,'.Vehicle::KWH_SCALE];

        return [
            'vehicle_id' => ['required', Rule::in([...$ownVehicles->keys()->map(fn ($id) => (string) $id), self::NEW_VEHICLE])],
            'vehicle_type' => [Rule::excludeIf(! $isNewVehicle), 'required', Rule::enum(VehicleType::class)],
            'plate_number' => [
                Rule::excludeIf(! $newWithPlate), 'required', 'string', 'max:'.Vehicle::PLATE_MAX_LENGTH,
                'regex:'.Vehicle::PLATE_PATTERN, 'unique:vehicles,plate_number',
            ],
            'brand_model' => [Rule::excludeIf(! $isNewVehicle), 'required', 'string', 'max:'.Vehicle::BRAND_MODEL_MAX_LENGTH],
            'battery_capacity_kwh' => [Rule::excludeIf(! $newWithBattery), 'required', ...$kwhRules, 'gt:0', 'max:'.Vehicle::MAX_BATTERY_KWH],
            'current_battery_kwh' => [
                Rule::excludeIf(! $hasBattery), 'required', ...$kwhRules, 'min:0',
                $isNewVehicle ? 'lte:battery_capacity_kwh' : 'max:'.(float) $existingVehicle?->battery_capacity_kwh,
            ],
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'start_date' => ['required', 'date_format:'.self::DATE_FORMAT, 'after_or_equal:today'],
            'days' => [
                Rule::excludeIf(! $isDaily), 'required', 'integer', 'min:'.Rental::MIN_DAILY_DAYS,
                'max:'.Rental::maxDailyDays($this->requestedStartDate($request)),
            ],
        ];
    }

    /**
     * Inside the transaction: lock the garage (and an existing vehicle) so two requests cannot
     * take them at the same moment, re-check both, then save the vehicle if new and the rental.
     *
     * @param  array<string, mixed>  $details  validated step 1
     *
     * @throws ValidationException when the garage or vehicle was taken in the meantime
     */
    private function saveRental(User $tenant, Vehicle $vehicle, array $details, int $garageId, UploadedFile $paymentProof): Rental
    {
        $garage = Garage::query()->lockForUpdate()->findOrFail($garageId);
        $draft = $this->draftRental($garage, $vehicle, $details);

        $isGarageFree = Garage::query()->whereKey($garage->id)
            ->availableBetween($draft->start_date, $draft->end_date)
            ->exists();

        if (! $isGarageFree) {
            throw ValidationException::withMessages([
                'garage_id' => "Garasi {$garage->code} baru saja disewa orang lain untuk tanggal tersebut. Pilih garasi lain.",
            ]);
        }

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
     * The tenant's chosen vehicle, or a new unsaved one built from the form.
     *
     * @param  array<string, mixed>  $details
     */
    private function resolveVehicle(array $details, User $tenant): Vehicle
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

    /**
     * End date shown on step 1 when the form is refilled, or null while the days are not valid yet.
     */
    private function requestedEndDate(Request $request): ?CarbonImmutable
    {
        $start = $this->requestedStartDate($request);
        $type = RentalType::tryFrom((string) $request->input('rental_type')) ?? RentalType::Daily;
        $days = filter_var($request->input('days'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => Rental::MIN_DAILY_DAYS, 'max_range' => Rental::maxDailyDays($start)],
        ]);

        if ($type === RentalType::Daily && $days === false) {
            return null;
        }

        return Rental::calculateEndDate($type, $start, $days === false ? null : $days);
    }

    /**
     * Start date from the raw request, used to size the day limit; today when it is missing or invalid.
     */
    private function requestedStartDate(Request $request): CarbonImmutable
    {
        $value = $request->input('start_date');

        if (! is_string($value) || ! CarbonImmutable::canBeCreatedFromFormat($value, self::DATE_FORMAT)) {
            return CarbonImmutable::today();
        }

        return CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $value);
    }
}
