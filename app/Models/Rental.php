<?php

namespace App\Models;

use App\Enums\RentalType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\RentalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
