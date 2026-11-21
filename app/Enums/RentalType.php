<?php

namespace App\Enums;

/**
 * How a rental or an extension is charged: per day or per month.
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
