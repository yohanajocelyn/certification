<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * Admin list of garages rented today, the starting point for extending a rental.
 */
class RentalController extends Controller
{
    private const RENTALS_PER_PAGE = 15;

    /**
     * Rentals that cover today, ending soonest first.
     */
    public function index(): View
    {
        $today = CarbonImmutable::today();

        $rentals = Rental::query()
            ->activeOn($today)
            ->with(['garage', 'vehicle.user'])
            ->withCount('extensions')
            ->orderBy('end_date')
            ->orderBy('id')
            ->paginate(self::RENTALS_PER_PAGE);

        return view('admin.rentals.index', ['rentals' => $rentals, 'today' => $today]);
    }
}
