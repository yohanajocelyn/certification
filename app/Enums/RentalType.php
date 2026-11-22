<?php

namespace App\Enums;

/**
 * How a rental is charged: per day or per month. (Extensions have no type: they are split into
 * whole months and leftover days.)
 */
enum RentalType: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';

    /**
     * Indonesian name shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Harian',
            self::Monthly => 'Bulanan',
        };
    }
}
