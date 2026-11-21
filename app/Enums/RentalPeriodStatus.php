<?php

namespace App\Enums;

/**
 * Where a rental stands on a given date; worked out from its dates, never stored.
 */
enum RentalPeriodStatus: string
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    case Finished = 'finished';

    /**
     * Indonesian name shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Akan Datang',
            self::Active => 'Aktif',
            self::Finished => 'Selesai',
        };
    }

    /**
     * Colour used by the x-status component.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Upcoming => 'warning',
            self::Active => 'success',
            self::Finished => 'neutral',
        };
    }
}
