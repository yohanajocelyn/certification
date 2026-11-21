<?php

namespace App\Models\Vehicles;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Models\Vehicle;

/**
 * A motorcycle: needs a plate, parks only in a bike garage.
 */
class Motorcycle extends Vehicle
{
    public const ?VehicleType TYPE = VehicleType::Motorcycle;

    /**
     * @return list<GarageType>
     */
    public function allowedGarageTypes(): array
    {
        return [GarageType::Bike];
    }
}
