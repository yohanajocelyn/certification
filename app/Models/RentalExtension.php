<?php

namespace App\Models;

use App\Enums\RentalType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\RentalExtensionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * An admin moving a rental's end date later, with its extra cost and payment proof.
 */
class RentalExtension extends Model
{
    /** @use HasFactory<RentalExtensionFactory> */
    use HasFactory;

    public const MIN_MONTHS = 1;

    /** Column limit of `days` (unsigned smallint), not a business limit: extensions have no day limit. */
    public const MAX_DAYS = 65_535;

    /** Most months that always fit in MAX_DAYS, even when every month counts 31 days. */
    public const MAX_MONTHS = 2_114;

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
     * An unsaved extension of the rental with its days, new end date and cost worked out,
     * used both to show the admin the remaining cost and to save it.
     *
     * @param  CarbonInterface|null  $newEndDate  required for a daily extension
     * @param  int|null  $months  required for a monthly extension
     *
     * @throws InvalidArgumentException when the input for the chosen type is missing
     */
    public static function draftFor(Rental $rental, RentalType $type, ?CarbonInterface $newEndDate, ?int $months): self
    {
        $oldEndDate = $rental->end_date;
        $isMonthly = $type === RentalType::Monthly;

        if ($isMonthly ? ($months === null || $months < self::MIN_MONTHS) : $newEndDate === null) {
            throw new InvalidArgumentException('A daily extension needs a new end date, a monthly one at least one month.');
        }

        $days = $isMonthly
            ? self::calculateMonthlyDays($oldEndDate, $months)
            : self::calculateExtraDays($oldEndDate, $newEndDate);

        $extension = new self([
            'extension_type' => $type,
            'months' => $isMonthly ? $months : null,
            'days' => $days,
            'old_end_date' => $oldEndDate,
            'new_end_date' => $oldEndDate->addDays($days),
            'additional_cost' => self::calculateAdditionalCost($type, (float) $rental->daily_price, $days, $months),
        ]);
        $extension->rental()->associate($rental);

        return $extension;
    }

    /**
     * First day the extension adds (the day after the old end date).
     */
    public function firstExtraDay(): CarbonImmutable
    {
        return $this->old_end_date->addDay();
    }

    /**
     * Public URL of the uploaded payment proof image.
     */
    public function paymentProofUrl(): string
    {
        return Storage::disk(Rental::PAYMENT_PROOF_DISK)->url($this->payment_proof);
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
