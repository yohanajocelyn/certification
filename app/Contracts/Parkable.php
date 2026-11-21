<?php

namespace App\Contracts;

use App\Enums\GarageType;

/**
 * Something that can be parked in a garage, and the rules for parking it.
 */
interface Parkable
{
    /**
     * Garage types this can be parked in.
     *
     * @return list<GarageType>
     */
    public function allowedGarageTypes(): array;

    /**
     * Whether a plate number must be registered.
     */
    public function requiresPlate(): bool;

    /**
     * Whether battery capacity and current battery level must be entered.
     */
    public function hasBattery(): bool;
}
