<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\RentalExtensionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An admin moving a rental's end date later, with its extra cost and payment proof.
 */
class RentalExtension extends Model
{
    /** @use HasFactory<RentalExtensionFactory> */
    use HasFactory;

    protected $fillable = [
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
            'months' => 'integer',
            'days' => 'integer',
            'old_end_date' => 'immutable_date',
            'new_end_date' => 'immutable_date',
            'additional_cost' => 'decimal:'.config('rules.garage.price_scale'),
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
     * An unsaved extension of the rental up to the new end date, with its days, whole months and
     * cost worked out, used both to show the admin the remaining cost and to save it.
     */
    public static function draftFor(Rental $rental, CarbonInterface $newEndDate): self
    {
        $oldEndDate = $rental->end_date;
        $days = self::calculateExtraDays($oldEndDate, $newEndDate);
        $months = self::calculateWholeMonths($oldEndDate, $days);

        $extension = new self([
            'months' => $months,
            'days' => $days,
            'old_end_date' => $oldEndDate,
            'new_end_date' => $oldEndDate->addDays($days),
            // Garage prices never change after creation, so the rental's garage price is the booked price.
            'additional_cost' => self::calculateAdditionalCost(
                (float) $rental->garage->daily_price,
                $months,
                $days - $months * $oldEndDate->daysInMonth,
            ),
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
     * Extensions made on a day inside the period (both ends inclusive); used by the reports.
     *
     * @param  Builder<RentalExtension>  $query
     */
    public function scopeCreatedBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereBetween('created_at', [
            CarbonImmutable::instance($from)->startOfDay(),
            CarbonImmutable::instance($to)->endOfDay(),
        ]);
    }

    /**
     * Days in one extension month: the day count of the month the rental ended in before the
     * extension (ends in January -> 31).
     */
    public function monthLength(): int
    {
        return $this->old_end_date->daysInMonth;
    }

    /**
     * Days left over after the whole months, charged at the daily price.
     */
    public function leftoverDays(): int
    {
        return $this->days - $this->months * $this->monthLength();
    }

    /**
     * Days added: the gap between the old and the new end date (the old end date is already paid).
     */
    public static function calculateExtraDays(CarbonInterface $oldEndDate, CarbonInterface $newEndDate): int
    {
        return (int) $oldEndDate->copy()->startOfDay()->diffInDays($newEndDate->copy()->startOfDay());
    }

    /**
     * Whole months in the added days. Every month counts the days of the month the rental currently
     * ends in (ends 10 Jan, 69 days -> 2 months of 31 days, 7 days left over).
     */
    public static function calculateWholeMonths(CarbonInterface $oldEndDate, int $days): int
    {
        return intdiv(max($days, 0), $oldEndDate->daysInMonth);
    }

    /**
     * Extra cost: months x the monthly price + leftover days x the daily price.
     */
    public static function calculateAdditionalCost(float $dailyPrice, int $months, int $leftoverDays): float
    {
        $monthlyPrice = $dailyPrice * config('rules.rental.monthly_price_multiplier');

        return round($monthlyPrice * $months + $dailyPrice * $leftoverDays, config('rules.garage.price_scale'));
    }
}
