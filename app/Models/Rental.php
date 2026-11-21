<?php

namespace App\Models;

use App\Enums\RentalPeriodStatus;
use App\Enums\RentalType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\RentalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * One vehicle parked in one garage for a period. Dates are inclusive: the rental
 * covers every day from start_date up to and including end_date.
 */
class Rental extends Model
{
    /** @use HasFactory<RentalFactory> */
    use HasFactory;

    public const MONTHLY_PRICE_MULTIPLIER = 25;

    public const MIN_DAILY_DAYS = 1;

    public const PAYMENT_PROOF_DISK = 'public';

    public const PAYMENT_PROOF_DIRECTORY = 'payment-proofs';

    public const PAYMENT_PROOF_MAX_KB = 2048;

    public const PAYMENT_PROOF_MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    protected $fillable = [
        'garage_id',
        'vehicle_id',
        'current_battery_kwh',
        'rental_type',
        'days',
        'start_date',
        'end_date',
        'daily_price',
        'kwh_price',
        'rental_cost',
        'charging_kwh',
        'charging_cost',
        'total_cost',
        'payment_proof',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $money = 'decimal:'.Garage::PRICE_SCALE;
        $kwh = 'decimal:'.Vehicle::KWH_SCALE;

        return [
            'rental_type' => RentalType::class,
            'days' => 'integer',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'current_battery_kwh' => $kwh,
            'charging_kwh' => $kwh,
            'daily_price' => $money,
            'kwh_price' => $money,
            'rental_cost' => $money,
            'charging_cost' => $money,
            'total_cost' => $money,
        ];
    }

    /**
     * The tenant who made this rental.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The rented garage.
     */
    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    /**
     * The vehicle parked in the garage.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Extensions added by an admin, oldest first.
     */
    public function extensions(): HasMany
    {
        return $this->hasMany(RentalExtension::class)->oldest();
    }

    /**
     * An unsaved rental with its end date and every cost worked out, used both to show
     * the tenant the total before paying and to save the rental. Only the tenant and the
     * payment proof are left to set.
     *
     * @param  int|null  $days  number of days, required for daily rentals
     * @param  float|null  $currentBatteryKwh  battery level entered by the tenant (electric cars)
     */
    public static function draft(
        Garage $garage,
        Vehicle $vehicle,
        RentalType $type,
        CarbonInterface $start,
        ?int $days,
        ?float $currentBatteryKwh,
    ): self {
        $dailyPrice = (float) $garage->daily_price;
        $kwhPrice = $garage->kwh_price === null ? null : (float) $garage->kwh_price;
        $rentalCost = self::calculateRentalCost($type, $dailyPrice, $days);
        $chargingKwh = self::calculateChargingKwh($vehicle, $garage, $currentBatteryKwh);
        $chargingCost = self::calculateChargingCost($chargingKwh, $kwhPrice);

        $rental = new self([
            'current_battery_kwh' => $vehicle->type->hasBattery() ? $currentBatteryKwh : null,
            'rental_type' => $type,
            'days' => $type === RentalType::Daily ? $days : null,
            'start_date' => CarbonImmutable::instance($start)->startOfDay(),
            'end_date' => self::calculateEndDate($type, $start, $days),
            'daily_price' => $dailyPrice,
            'kwh_price' => $kwhPrice,
            'rental_cost' => $rentalCost,
            'charging_kwh' => $chargingKwh,
            'charging_cost' => $chargingCost,
            'total_cost' => round($rentalCost + $chargingCost, Garage::PRICE_SCALE),
        ]);
        $rental->garage()->associate($garage);
        $rental->vehicle()->associate($vehicle);

        return $rental;
    }

    /**
     * Last rented day (inclusive).
     *
     * Daily: start + days - 1 (21 Nov, 5 days -> 25 Nov).
     * Monthly: one month is the number of days in the start month (10 Feb 2027 -> 9 Mar).
     *
     * @param  int|null  $days  number of days, required for daily rentals
     *
     * @throws InvalidArgumentException when a daily rental has no number of days
     */
    public static function calculateEndDate(RentalType $type, CarbonInterface $start, ?int $days = null): CarbonImmutable
    {
        $start = CarbonImmutable::instance($start)->startOfDay();

        if ($type === RentalType::Monthly) {
            return $start->addDays($start->daysInMonth - 1);
        }

        if ($days === null || $days < self::MIN_DAILY_DAYS) {
            throw new InvalidArgumentException('A daily rental needs at least one day.');
        }

        return $start->addDays($days - 1);
    }

    /**
     * Longest daily rental allowed when starting on this date: days in that month minus one.
     */
    public static function maxDailyDays(CarbonInterface $start): int
    {
        return $start->daysInMonth - 1;
    }

    /**
     * Rent for the period, without charging.
     *
     * @param  int|null  $days  number of days, required for daily rentals
     */
    public static function calculateRentalCost(RentalType $type, float $dailyPrice, ?int $days = null): float
    {
        if ($type === RentalType::Monthly) {
            return round($dailyPrice * self::MONTHLY_PRICE_MULTIPLIER, Garage::PRICE_SCALE);
        }

        return round($dailyPrice * (int) $days, Garage::PRICE_SCALE);
    }

    /**
     * kWh needed to charge the vehicle to full. Only electric cars in an electric garage are charged.
     *
     * @param  float|null  $currentBatteryKwh  battery level entered by the tenant
     */
    public static function calculateChargingKwh(Vehicle $vehicle, Garage $garage, ?float $currentBatteryKwh): float
    {
        if (! $vehicle->type->hasBattery() || ! $garage->type->hasCharging()) {
            return 0.0;
        }

        $missingKwh = (float) $vehicle->battery_capacity_kwh - (float) $currentBatteryKwh;

        return round(max(0.0, $missingKwh), Vehicle::KWH_SCALE);
    }

    /**
     * Price of charging the given amount of kWh.
     */
    public static function calculateChargingCost(float $chargingKwh, ?float $kwhPrice): float
    {
        return round($chargingKwh * (float) $kwhPrice, Garage::PRICE_SCALE);
    }

    /**
     * Number of rented days, counting both the first and the last day.
     */
    public function durationInDays(): int
    {
        return self::countDays($this->start_date, $this->end_date);
    }

    /**
     * Days from start to end, both included (21 Nov to 25 Nov = 5).
     */
    public static function countDays(CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
    }

    /**
     * Whether the rental has not started yet, is running, or is over on the given date.
     */
    public function periodStatusOn(CarbonInterface $date): RentalPeriodStatus
    {
        $day = CarbonImmutable::instance($date)->startOfDay();

        if ($day->lessThan($this->start_date)) {
            return RentalPeriodStatus::Upcoming;
        }

        return $day->greaterThan($this->end_date) ? RentalPeriodStatus::Finished : RentalPeriodStatus::Active;
    }

    /**
     * Public URL of the uploaded payment proof image.
     */
    public function paymentProofUrl(): string
    {
        return Storage::disk(self::PAYMENT_PROOF_DISK)->url($this->payment_proof);
    }

    /**
     * Rentals that cover the given date.
     *
     * @param  Builder<Rental>  $query
     */
    public function scopeActiveOn(Builder $query, CarbonInterface $date): void
    {
        $query->overlapping($date, $date);
    }

    /**
     * Rentals sharing at least one day with the period (both ends inclusive).
     *
     * @param  Builder<Rental>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        // whereDate compares the date part only, so a stored time can never shift the result.
        $query->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString());
    }
}
