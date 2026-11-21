<?php

namespace Tests\Unit\Services;

use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleServiceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleService $service;

    private User $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-21');
        $this->service = app(VehicleService::class);
        $this->tenant = User::factory()->create();
    }

    public function test_all_for_tenant_returns_only_own_vehicles_by_name(): void
    {
        Vehicle::factory()->for($this->tenant)->create(['brand_model' => 'Toyota Avanza']);
        Vehicle::factory()->for($this->tenant)->create(['brand_model' => 'Honda Brio']);
        Vehicle::factory()->create(['brand_model' => 'Other Tenant Car']);

        $names = $this->service->allForTenant($this->tenant)->pluck('brand_model')->all();

        $this->assertSame(['Honda Brio', 'Toyota Avanza'], $names);
    }

    public function test_paginate_for_tenant_loads_the_garage_each_vehicle_is_in_today(): void
    {
        $car = Vehicle::factory()->for($this->tenant)->create();
        Rental::factory()->for($car, 'vehicle')->startingOn('2026-11-21')->create();

        $vehicle = $this->service->paginateForTenant($this->tenant)->first();

        $this->assertTrue($vehicle->relationLoaded('rentalsToday'));
        $this->assertCount(1, $vehicle->rentalsToday);
    }
}
