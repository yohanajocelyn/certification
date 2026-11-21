<?php

namespace Tests\Unit\Models\Vehicles;

use App\Contracts\Parkable;
use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Models\Rental;
use App\Models\User;
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

    // ---- Rules per child class (inheritance + overriding) ---------------------------------

    public function test_bicycle_needs_no_plate_and_parks_only_in_bike_garage(): void
    {
        $bicycle = new Bicycle;

        $this->assertFalse($bicycle->requiresPlate());
        $this->assertFalse($bicycle->hasBattery());
        $this->assertSame([GarageType::Bike], $bicycle->allowedGarageTypes());
    }

    public function test_motorcycle_needs_plate_and_parks_only_in_bike_garage(): void
    {
        $motorcycle = new Motorcycle;

        $this->assertTrue($motorcycle->requiresPlate());
        $this->assertFalse($motorcycle->hasBattery());
        $this->assertSame([GarageType::Bike], $motorcycle->allowedGarageTypes());
    }

    public function test_car_needs_plate_and_parks_only_in_car_garage(): void
    {
        $car = new Car;

        $this->assertTrue($car->requiresPlate());
        $this->assertFalse($car->hasBattery());
        $this->assertSame([GarageType::Car], $car->allowedGarageTypes());
    }

    public function test_electric_car_needs_plate_and_battery_and_parks_in_car_or_electric_garage(): void
    {
        $electricCar = new ElectricCar;

        $this->assertTrue($electricCar->requiresPlate());
        $this->assertTrue($electricCar->hasBattery());
        $this->assertSame([GarageType::Car, GarageType::Electric], $electricCar->allowedGarageTypes());
    }

    public function test_every_vehicle_class_is_a_parkable_vehicle(): void
    {
        foreach (Vehicle::SUBCLASSES as $class) {
            $vehicle = new $class;

            $this->assertInstanceOf(Vehicle::class, $vehicle);
            $this->assertInstanceOf(Parkable::class, $vehicle);
        }
    }

    public function test_plain_vehicle_parks_nowhere(): void
    {
        $this->assertSame([], (new Vehicle)->allowedGarageTypes());
        $this->assertFalse((new Vehicle)->canParkIn(GarageType::Car));
    }

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

    public function test_electric_car_charging_never_goes_below_zero(): void
    {
        $electricCar = (new ElectricCar)->fill(['battery_capacity_kwh' => 60]);

        $this->assertSame(0.0, $electricCar->chargingKwh(65));
    }

    public function test_vehicles_without_battery_never_charge(): void
    {
        $this->assertSame(0.0, (new Car)->chargingKwh(20));
        $this->assertSame(0.0, (new Bicycle)->chargingKwh(null));
    }

    // ---- One table, the right class (single-table inheritance) ----------------------------

    public function test_for_type_builds_the_child_class_with_its_type_set(): void
    {
        foreach (VehicleType::cases() as $type) {
            $vehicle = Vehicle::forType($type);

            $this->assertInstanceOf(Vehicle::SUBCLASSES[$type->value], $vehicle);
            $this->assertSame($type, $vehicle->type);
        }
    }

    public function test_find_returns_the_child_class_of_each_stored_type(): void
    {
        foreach (VehicleType::cases() as $type) {
            $saved = Vehicle::factory()->ofType($type)->create();

            $this->assertInstanceOf(Vehicle::SUBCLASSES[$type->value], Vehicle::find($saved->id));
        }
    }

    public function test_relations_return_child_classes(): void
    {
        $tenant = User::factory()->create();
        Vehicle::factory()->electricCar()->for($tenant)->create();
        $rental = Rental::factory()->for(Vehicle::factory()->bicycle()->for($tenant))->create();

        $this->assertInstanceOf(ElectricCar::class, $tenant->vehicles()->where('type', VehicleType::ElectricCar)->first());
        $this->assertInstanceOf(Bicycle::class, $rental->fresh()->vehicle);
    }

    public function test_child_query_returns_only_its_own_type(): void
    {
        Vehicle::factory()->electricCar()->create();
        Vehicle::factory()->car()->create();
        Vehicle::factory()->bicycle()->create();

        $this->assertCount(1, ElectricCar::all());
        $this->assertCount(3, Vehicle::all());
    }

    public function test_child_rentals_use_the_shared_vehicle_id_column(): void
    {
        $electricCar = Vehicle::factory()->electricCar()->create();
        Rental::factory()->for($electricCar, 'vehicle')->create();

        $this->assertSame('vehicle_id', $electricCar->getForeignKey());
        $this->assertCount(1, $electricCar->rentals);
    }

    public function test_type_cannot_be_changed_by_mass_assignment(): void
    {
        $car = Vehicle::forType(VehicleType::Car)->fill(['type' => VehicleType::Bicycle->value, 'brand_model' => 'Avanza']);

        $this->assertSame(VehicleType::Car, $car->type);
    }

    public function test_child_factory_creates_its_own_type(): void
    {
        $this->assertInstanceOf(ElectricCar::class, ElectricCar::factory()->create());
        $this->assertSame(VehicleType::Motorcycle, Motorcycle::factory()->create()->type);
    }
}
