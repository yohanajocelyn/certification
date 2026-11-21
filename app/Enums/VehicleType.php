<?php

namespace App\Enums;

/**
 * The kinds of vehicle a tenant can park, and which garages each one fits.
 */
enum VehicleType: string
{
    case Bicycle = 'bicycle';
    case Motorcycle = 'motorcycle';
    case Car = 'car';
    case ElectricCar = 'electric_car';

    /**
     * Garage types this vehicle may be parked in.
     *
     * @return list<GarageType>
     */
    public function allowedGarageTypes(): array
    {
        return match ($this) {
            self::Bicycle, self::Motorcycle => [GarageType::Bike],
            self::Car => [GarageType::Car],
            self::ElectricCar => [GarageType::Car, GarageType::Electric],
        };
    }

    /**
     * Whether this vehicle may be parked in the given garage type.
     */
    public function canUseGarage(GarageType $garageType): bool
    {
        return in_array($garageType, $this->allowedGarageTypes(), true);
    }

    /**
     * Whether a plate number is required (every vehicle except a bicycle).
     */
    public function requiresPlate(): bool
    {
        return $this !== self::Bicycle;
    }

    /**
     * Whether battery capacity and current battery level must be entered.
     */
    public function hasBattery(): bool
    {
        return $this === self::ElectricCar;
    }

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
