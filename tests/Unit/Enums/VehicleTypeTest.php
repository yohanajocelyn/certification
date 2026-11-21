<?php

namespace Tests\Unit\Enums;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use PHPUnit\Framework\TestCase;

class VehicleTypeTest extends TestCase
{
    public function test_bicycle_and_motorcycle_fit_only_bike_garage(): void
    {
        $this->assertSame([GarageType::Bike], VehicleType::Bicycle->allowedGarageTypes());
        $this->assertSame([GarageType::Bike], VehicleType::Motorcycle->allowedGarageTypes());
    }

    public function test_car_fits_only_car_garage(): void
    {
        $this->assertSame([GarageType::Car], VehicleType::Car->allowedGarageTypes());
    }

    public function test_electric_car_fits_car_and_electric_garage(): void
    {
        $this->assertSame([GarageType::Car, GarageType::Electric], VehicleType::ElectricCar->allowedGarageTypes());
    }

    public function test_can_use_garage_rejects_incompatible_garage(): void
    {
        $this->assertFalse(VehicleType::Car->canUseGarage(GarageType::Electric));
        $this->assertFalse(VehicleType::Motorcycle->canUseGarage(GarageType::Car));
        $this->assertTrue(VehicleType::ElectricCar->canUseGarage(GarageType::Car));
    }

    public function test_requires_plate_is_false_only_for_bicycle(): void
    {
        $this->assertFalse(VehicleType::Bicycle->requiresPlate());
        $this->assertTrue(VehicleType::Motorcycle->requiresPlate());
        $this->assertTrue(VehicleType::Car->requiresPlate());
        $this->assertTrue(VehicleType::ElectricCar->requiresPlate());
    }

    public function test_has_battery_is_true_only_for_electric_car(): void
    {
        $this->assertTrue(VehicleType::ElectricCar->hasBattery());
        $this->assertFalse(VehicleType::Car->hasBattery());
        $this->assertFalse(VehicleType::Bicycle->hasBattery());
    }
}
