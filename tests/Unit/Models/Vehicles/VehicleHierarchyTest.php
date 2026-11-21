<?php

namespace Tests\Unit\Models\Vehicles;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Models\Vehicle;
use App\Models\Vehicles\Bicycle;
use App\Models\Vehicles\Car;
use App\Models\Vehicles\ElectricCar;
use App\Models\Vehicles\Motorcycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleHierarchyTest extends TestCase
{
    use RefreshDatabase;

    // ---- canParkIn per type ---------------------------------------------------------------

    public function test_can_park_in_accepts_allowed_garages(): void
    {
        $this->assertTrue((new Bicycle)->canParkIn(GarageType::Bike));
        $this->assertTrue((new Motorcycle)->canParkIn(GarageType::Bike));
        $this->assertTrue((new Car)->canParkIn(GarageType::Car));
        $this->assertTrue((new ElectricCar)->canParkIn(GarageType::Car));
        $this->assertTrue((new ElectricCar)->canParkIn(GarageType::Electric));
    }

    public function test_can_park_in_refuses_other_garages(): void
    {
        $this->assertFalse((new Bicycle)->canParkIn(GarageType::Car));
        $this->assertFalse((new Motorcycle)->canParkIn(GarageType::Electric));
        $this->assertFalse((new Car)->canParkIn(GarageType::Electric));
        $this->assertFalse((new Car)->canParkIn(GarageType::Bike));
        $this->assertFalse((new ElectricCar)->canParkIn(GarageType::Bike));
    }

    // ---- Charging (polymorphism) ----------------------------------------------------------

    public function test_electric_car_charging_kwh_is_missing_battery(): void
    {
        $electricCar = (new ElectricCar)->fill(['battery_capacity_kwh' => 60]);

        $this->assertSame(40.0, $electricCar->chargingKwh(20));
    }

    public function test_electric_car_with_full_battery_needs_no_charging(): void
    {
        $electricCar = (new ElectricCar)->fill(['battery_capacity_kwh' => 60]);

        $this->assertSame(0.0, $electricCar->chargingKwh(60));
    }

    // ---- One table, the right class (single-table inheritance) ----------------------------

    public function test_find_returns_the_child_class_of_each_stored_type(): void
    {
        foreach (VehicleType::cases() as $type) {
            $saved = Vehicle::factory()->ofType($type)->create();

            $this->assertInstanceOf(Vehicle::SUBCLASSES[$type->value], Vehicle::find($saved->id));
        }
    }
}
