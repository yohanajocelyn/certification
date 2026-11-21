<?php

namespace App\Enums;

/**
 * The three kinds of garage, each with its own code prefix.
 */
enum GarageType: string
{
    case Bike = 'bike';
    case Car = 'car';
    case Electric = 'electric';

    /**
     * Letter that starts every garage code of this type (S001, C001, E001).
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Bike => 'S',
            self::Car => 'C',
            self::Electric => 'E',
        };
    }

    /**
     * Whether electric cars parked here are charged to full (and pay per kWh).
     */
    public function hasCharging(): bool
    {
        return $this === self::Electric;
    }

    /**
     * Indonesian name shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Bike => 'Garasi Sepeda',
            self::Car => 'Garasi Mobil',
            self::Electric => 'Garasi Mobil Listrik',
        };
    }
}
