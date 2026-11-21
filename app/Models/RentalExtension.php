<?php

namespace App\Models;

use App\Enums\RentalType;
use Carbon\CarbonInterface;
use Database\Factories\RentalExtensionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin moving a rental's end date later, with its extra cost and payment proof.
 */
class RentalExtension extends Model
{
    /** @use HasFactory<RentalExtensionFactory> */
    use HasFactory;

    public const MIN_MONTHS = 1;

    protected $fillable = [
        'extension_type',
        'months',
        'days',
        'old_end_date',
        'new_end_date',
        'additional_cost',
        'payment_proof',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extension_type' => RentalType::class,
            'months' => 'integer',
            'days' => 'integer',
            'old_end_date' => 'immutable_date',
            'new_end_date' => 'immutable_date',
            'additional_cost' => 'decimal:'.Garage::PRICE_SCALE,
        ];
    }

    /**
     * The rental being extended.
     */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /**
     * Days added by a daily extension: the gap between the old and the new end date.
     */
    public static function calculateExtraDays(CarbonInterface $oldEndDate, CarbonInterface $newEndDate): int
    {
        return (int) $oldEndDate->copy()->startOfDay()->diffInDays($newEndDate->copy()->startOfDay());
    }

    /**
     * Days added by a monthly extension. Every month counts as the number of days in the
     * month the rental currently ends in (ends in January, 2 months -> 31 + 31 days).
     */
    public static function calculateMonthlyDays(CarbonInterface $currentEndDate, int $months): int
    {
        return $currentEndDate->daysInMonth * $months;
    }

    /**
     * Extra cost: daily = days x daily price, monthly = months x the monthly price.
     *
     * @param  int  $days  days added (used for daily extensions)
     * @param  int|null  $months  months added (used for monthly extensions)
     */
    public static function calculateAdditionalCost(RentalType $type, float $dailyPrice, int $days, ?int $months = null): float
    {
        if ($type === RentalType::Monthly) {
            return round($dailyPrice * Rental::MONTHLY_PRICE_MULTIPLIER * (int) $months, Garage::PRICE_SCALE);
        }

        return round($dailyPrice * $days, Garage::PRICE_SCALE);
    }
}
