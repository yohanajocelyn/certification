<?php

namespace App\Enums;

/**
 * The stored vehicle types and their names. How each type behaves (garages, plate, battery,
 * charging) lives in its class under App\Models\Vehicles; see Vehicle::SUBCLASSES.
 */
enum VehicleType: string
{
    case Bicycle = 'bicycle';
    case Motorcycle = 'motorcycle';
    case Car = 'car';
    case ElectricCar = 'electric_car';

    /**
     * Indonesian name shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Bicycle => 'Sepeda',
            self::Motorcycle => 'Sepeda Motor',
            self::Car => 'Mobil',
            self::ElectricCar => 'Mobil Listrik',
        };
    }
}
