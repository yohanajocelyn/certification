<?php

namespace App\Models\Vehicles;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Models\Vehicle;

/**
 * An electric car: needs a plate and a battery capacity, parks in a car or an electric garage,
 * and is charged to full in an electric garage.
 */
class ElectricCar extends Vehicle
{
    public const ?VehicleType TYPE = VehicleType::ElectricCar;

    /**
     * @return list<GarageType>
     */
    public function allowedGarageTypes(): array
    {
        return [GarageType::Car, GarageType::Electric];
    }

    /**
     * Electric cars register their battery.
     */
    public function hasBattery(): bool
    {
        return true;
    }

    /**
     * kWh missing to a full battery: capacity minus the current level, never below zero.
     *
     * @param  float|null  $currentBatteryKwh  battery level entered by the tenant
     */
    public function chargingKwh(?float $currentBatteryKwh): float
    {
        $missingKwh = (float) $this->battery_capacity_kwh - (float) $currentBatteryKwh;

        return round(max(0.0, $missingKwh), config('rules.vehicle.kwh_scale'));
    }
}
