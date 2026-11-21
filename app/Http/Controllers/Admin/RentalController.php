<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * Admin list of garages rented today, the starting point for extending a rental.
 */
class RentalController extends Controller
{
    public function __construct(private readonly RentalService $rentals) {}

    /**
     * Rentals that cover today, ending soonest first.
     */
    public function index(): View
    {
        return view('admin.rentals.index', [
            'rentals' => $this->rentals->activeToday(),
            'today' => CarbonImmutable::today(),
        ]);
    }
}
