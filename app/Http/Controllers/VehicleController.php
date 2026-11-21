<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tenant page listing their own vehicles. Vehicles are registered while renting and never edited.
 */
class VehicleController extends Controller
{
    private const VEHICLES_PER_PAGE = 10;

    /**
     * The tenant's vehicles with where each one is parked today and how often it was rented.
     */
    public function index(Request $request): View
    {
        $vehicles = $request->user()->vehicles()
            ->with('rentalsToday.garage')
            ->withCount('rentals')
            ->orderBy('brand_model')
            ->paginate(self::VEHICLES_PER_PAGE);

        return view('vehicles.index', ['vehicles' => $vehicles]);
    }
}
