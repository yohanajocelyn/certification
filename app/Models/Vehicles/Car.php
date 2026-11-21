<?php

namespace App\Models\Vehicles;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Models\Vehicle;

/**
 * A petrol car: needs a plate, parks only in a car garage.
 */
class Car extends Vehicle
{
    public const ?VehicleType TYPE = VehicleType::Car;

    /**
     * @return list<GarageType>
     */
    public function allowedGarageTypes(): array
    {
        return [GarageType::Car];
    }
}
