<?php

namespace App\Models\Vehicles;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Models\Vehicle;

/**
 * A bicycle: no plate, parks only in a bike garage.
 */
class Bicycle extends Vehicle
{
    public const ?VehicleType TYPE = VehicleType::Bicycle;

    /**
     * @return list<GarageType>
     */
    public function allowedGarageTypes(): array
    {
        return [GarageType::Bike];
    }

    /**
     * Bicycles have no plate number.
     */
    public function requiresPlate(): bool
    {
        return false;
    }
}
