<?php

namespace App\Http\Controllers;

use App\Services\VehicleService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tenant page listing their own vehicles. Vehicles are registered while renting and never edited.
 */
class VehicleController extends Controller
{
    public function __construct(private readonly VehicleService $vehicles) {}

    /**
     * The tenant's vehicles with where each one is parked today.
     */
    public function index(Request $request): View
    {
        return view('vehicles.index', ['vehicles' => $this->vehicles->paginateForTenant($request->user())]);
    }
}
