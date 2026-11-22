<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\RentalNotExtendableException;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\RentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin extension of a running rental in two steps: choose the new end date, then check the
 * remaining cost (whole months + leftover days) and upload the payment proof.
 */
class RentalExtensionController extends Controller
{
    public function __construct(private readonly RentalService $rentals) {}

    /**
     * Step 1: choose the new end date, with the rental's details and earlier extensions.
     */
    public function create(Request $request, Rental $rental): View|RedirectResponse
    {
        try {
            $rental = $this->rentals->prepareExtension($rental);
        } catch (RentalNotExtendableException $exception) {
            return $this->backToList($exception);
        }

        return view('admin.rentals.extensions.create', [
            'rental' => $rental,
            'defaults' => $request->query(),
        ]);
    }

    /**
     * Step 2: show the new end date, the months and days added and the remaining cost to pay.
     */
    public function preview(Request $request, Rental $rental): View|RedirectResponse
    {
        try {
            $input = $request->validate($this->validationRules($rental));
            $extension = $this->rentals->quoteExtension($rental, $input);
        } catch (RentalNotExtendableException $exception) {
            return $this->backToList($exception);
        } catch (ValidationException $exception) {
            // Always back to step 1: "back" could be this same page and loop when saving failed.
            throw $exception->redirectTo(route('admin.rentals.extensions.create', $rental));
        }

        return view('admin.rentals.extensions.preview', [
            'rental' => $rental,
            'input' => $input,
            'extension' => $extension,
        ]);
    }

    /**
     * Save the extension and move the rental's end date.
     */
    public function store(Request $request, Rental $rental): RedirectResponse
    {
        $input = $request->validate($this->validationRules($rental));
        $proof = $request->validate([
            'payment_proof' => ['required', 'image', 'mimes:'.implode(',', Rental::PAYMENT_PROOF_MIMES), 'max:'.config('rules.rental.payment_proof_max_kb')],
        ])['payment_proof'];

        try {
            $extension = $this->rentals->extend($rental, $input, $proof);
        } catch (RentalNotExtendableException $exception) {
            return $this->backToList($exception);
        }

        return redirect()->route('admin.rentals.index')->with(
            'success',
            "Sewa garasi {$rental->garage->code} diperpanjang sampai {$extension->new_end_date->translatedFormat('d M Y')}."
        );
    }

    /**
     * A new end date after the current one (no day limit beyond the column size).
     *
     * @return array<string, mixed>
     */
    private function validationRules(Rental $rental): array
    {
        $oldEndDate = $rental->end_date;

        return [
            'new_end_date' => [
                'required', 'date_format:'.RentalService::DATE_FORMAT,
                'after:'.$oldEndDate->toDateString(),
                'before_or_equal:'.$oldEndDate->addDays(config('rules.rental_extension.max_days'))->toDateString(),
            ],
        ];
    }

    /**
     * Back to today's rentals with the reason the rental cannot be extended.
     */
    private function backToList(RentalNotExtendableException $exception): RedirectResponse
    {
        return redirect()->route('admin.rentals.index')->with('error', $exception->getMessage());
    }
}
