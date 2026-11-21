<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RentalType;
use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin extension of a running rental in two steps: choose the new end date (daily) or a number
 * of months (monthly), then check the remaining cost and upload the payment proof.
 */
class RentalExtensionController extends Controller
{
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * Step 1: choose how to extend, with the rental's details and earlier extensions.
     */
    public function create(Request $request, Rental $rental): View|RedirectResponse
    {
        if (! $rental->isActiveOn(CarbonImmutable::today())) {
            return $this->notActiveRedirect();
        }

        $rental->load(['garage', 'vehicle.user', 'extensions']);

        return view('admin.rentals.extensions.create', [
            'rental' => $rental,
            'rentalTypes' => RentalType::cases(),
            'defaults' => $request->query(),
        ]);
    }

    /**
     * Step 2: show the new end date, the days added and the remaining cost to pay.
     */
    public function preview(Request $request, Rental $rental): View|RedirectResponse
    {
        if (! $rental->isActiveOn(CarbonImmutable::today())) {
            return $this->notActiveRedirect();
        }

        try {
            [$input, $extension] = $this->validateExtension($request, $rental);
        } catch (ValidationException $exception) {
            // Always back to step 1: "back" could be this same page and loop when saving failed.
            throw $exception->redirectTo(route('admin.rentals.extensions.create', $rental));
        }

        $rental->load(['garage', 'vehicle.user']);

        return view('admin.rentals.extensions.preview', [
            'rental' => $rental,
            'input' => $input,
            'extension' => $extension,
        ]);
    }

    /**
     * Save the extension and move the rental's end date, after locking and re-checking the garage.
     */
    public function store(Request $request, Rental $rental): RedirectResponse
    {
        if (! $rental->isActiveOn(CarbonImmutable::today())) {
            return $this->notActiveRedirect();
        }

        [$input] = $this->validateExtension($request, $rental);
        $proof = $request->validate([
            'payment_proof' => ['required', 'image', 'mimes:'.implode(',', Rental::PAYMENT_PROOF_MIMES), 'max:'.Rental::PAYMENT_PROOF_MAX_KB],
        ])['payment_proof'];

        $extension = DB::transaction(fn () => $this->saveExtension($rental, $input, $proof));

        return redirect()->route('admin.rentals.index')->with(
            'success',
            "Sewa garasi {$rental->garage->code} diperpanjang sampai {$extension->new_end_date->translatedFormat('d M Y')}."
        );
    }

    /**
     * Validate the extension input and make sure the garage and vehicle are free for the added days.
     *
     * @return array{0: array<string, mixed>, 1: RentalExtension} the validated input and the unsaved extension
     *
     * @throws ValidationException
     */
    private function validateExtension(Request $request, Rental $rental): array
    {
        $input = $request->validate($this->validationRules($request, $rental));
        $extension = $this->draftExtension($rental, $input);
        $this->ensureExtraDaysAreFree($rental, $extension, $this->inputField($input));

        return [$input, $extension];
    }

    /**
     * Daily: a later end date (no day limit). Monthly: a number of months.
     *
     * @return array<string, mixed>
     */
    private function validationRules(Request $request, Rental $rental): array
    {
        $isMonthly = $request->input('extension_type') === RentalType::Monthly->value;
        $oldEndDate = $rental->end_date;

        return [
            'extension_type' => ['required', Rule::enum(RentalType::class)],
            'new_end_date' => [
                Rule::excludeIf($isMonthly), 'required', 'date_format:'.self::DATE_FORMAT,
                'after:'.$oldEndDate->toDateString(),
                'before_or_equal:'.$oldEndDate->addDays(RentalExtension::MAX_DAYS)->toDateString(),
            ],
            'months' => [
                Rule::excludeIf(! $isMonthly), 'required', 'integer',
                'min:'.RentalExtension::MIN_MONTHS, 'max:'.RentalExtension::MAX_MONTHS,
            ],
        ];
    }

    /**
     * Inside the transaction: lock the rental and its garage, rebuild the extension from the
     * locked rental (another admin may have just extended it), re-check, then save.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException when the extension no longer fits
     */
    private function saveExtension(Rental $rental, array $input, UploadedFile $proof): RentalExtension
    {
        $lockedRental = Rental::query()->lockForUpdate()->findOrFail($rental->id);
        Garage::query()->lockForUpdate()->findOrFail($lockedRental->garage_id);

        $extension = $this->draftExtension($lockedRental, $input);

        if ($extension->days < 1) {
            throw ValidationException::withMessages([
                $this->inputField($input) => 'Sewa ini baru saja diperpanjang. Muat ulang halaman dan pilih tanggal lagi.',
            ]);
        }

        $this->ensureExtraDaysAreFree($lockedRental, $extension, $this->inputField($input));

        $extension->payment_proof = $proof->store(Rental::PAYMENT_PROOF_DIRECTORY, Rental::PAYMENT_PROOF_DISK);
        $extension->save();
        $lockedRental->update(['end_date' => $extension->new_end_date]);

        return $extension;
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
        $type = RentalType::from($input['extension_type']);
        $newEndDate = isset($input['new_end_date'])
            ? CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $input['new_end_date'])
            : null;

        return RentalExtension::draftFor($rental, $type, $newEndDate, isset($input['months']) ? (int) $input['months'] : null);
    }

    /**
     * The field the admin filled in: the new end date (daily) or the months (monthly).
     *
     * @param  array<string, mixed>  $input
     */
    private function inputField(array $input): string
    {
        return $input['extension_type'] === RentalType::Monthly->value ? 'months' : 'new_end_date';
    }

    /**
     * Only a rental that covers today can be extended.
     */
    private function notActiveRedirect(): RedirectResponse
    {
        return redirect()->route('admin.rentals.index')
            ->with('error', 'Hanya sewa yang sedang berjalan hari ini yang bisa diperpanjang.');
    }
}
