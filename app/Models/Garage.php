<?php

namespace App\Models;

use App\Enums\GarageType;
use Carbon\CarbonInterface;
use Database\Factories\GarageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LengthException;

/**
 * A rentable garage with a type-prefixed code such as S001, C010 or E002.
 */
class Garage extends Model
{
    /** @use HasFactory<GarageFactory> */
    use HasFactory;

    public const CODE_NUMBER_DIGITS = 3;

    public const CODE_LENGTH = 1 + self::CODE_NUMBER_DIGITS;

    public const MAX_CODE_NUMBER = 999;

    public const MIN_BATCH_SIZE = 1;

    public const MAX_BATCH_SIZE = 999;

    /** Highest daily or kWh price; keeps a 25-day month plus charging well inside decimal(12,2). */
    public const MAX_PRICE = 100_000_000;

    public const PRICE_PRECISION = 12;

    public const PRICE_SCALE = 2;

    protected $fillable = ['code', 'type', 'daily_price', 'kwh_price'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GarageType::class,
            'daily_price' => 'decimal:'.self::PRICE_SCALE,
            'kwh_price' => 'decimal:'.self::PRICE_SCALE,
            'is_used' => 'boolean',
        ];
    }

    /**
     * Every rental ever made for this garage.
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * The number the next garage of this type gets (1 when none exist yet).
     */
    public static function nextCodeNumber(GarageType $type): int
    {
        return self::highestCodeNumber(static::query()->where('type', $type), $type) + 1;
    }

    /**
     * How many more garages of this type fit in the 3-digit code range.
     */
    public static function remainingCodeNumbers(GarageType $type): int
    {
        return self::MAX_CODE_NUMBER - (self::nextCodeNumber($type) - 1);
    }

    /**
     * Create a batch of garages of one type with the next free codes (C011, C012, ...).
     *
     * @param  int  $quantity  1 to MAX_BATCH_SIZE, and no more than remainingCodeNumbers()
     * @param  float|null  $kwhPrice  charging price; stored only for electric garages
     * @return list<string> the new codes, in order
     *
     * @throws InvalidArgumentException when the quantity is below MIN_BATCH_SIZE
     * @throws LengthException when the batch would go past code number 999
     */
    public static function createBatch(GarageType $type, int $quantity, float $dailyPrice, ?float $kwhPrice): array
    {
        if ($quantity < self::MIN_BATCH_SIZE) {
            throw new InvalidArgumentException('A garage batch needs at least '.self::MIN_BATCH_SIZE.' garage.');
        }

        return DB::transaction(function () use ($type, $quantity, $dailyPrice, $kwhPrice) {
            // Lock this type's rows so two admins creating at once never get the same numbers.
            $lockedQuery = static::query()->where('type', $type)->lockForUpdate();
            $firstNumber = self::highestCodeNumber($lockedQuery, $type) + 1;
            $lastNumber = $firstNumber + $quantity - 1;

            if ($lastNumber > self::MAX_CODE_NUMBER) {
                throw new LengthException("Only {$type->prefix()} codes up to ".self::MAX_CODE_NUMBER.' exist.');
            }

            $now = now();
            $codes = array_map(fn (int $number) => self::formatCode($type, $number), range($firstNumber, $lastNumber));

            static::query()->insert(array_map(fn (string $code) => [
                'code' => $code,
                'type' => $type->value,
                'daily_price' => $dailyPrice,
                'kwh_price' => $type->hasCharging() ? $kwhPrice : null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $codes));

            return $codes;
        });
    }

    /**
     * Build a garage code from its type and number, e.g. (Car, 10) -> "C010".
     */
    public static function formatCode(GarageType $type, int $number): string
    {
        return $type->prefix().str_pad((string) $number, self::CODE_NUMBER_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Garages that have a rental covering the given date.
     *
     * @param  Builder<Garage>  $query
     */
    public function scopeUsedOn(Builder $query, CarbonInterface $date): void
    {
        $query->whereHas('rentals', fn (Builder $rentals) => $rentals->activeOn($date));
    }

    /**
     * Garages with no rental overlapping the whole period (dates inclusive).
     *
     * @param  Builder<Garage>  $query
     */
    public function scopeAvailableBetween(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->whereDoesntHave('rentals', fn (Builder $rentals) => $rentals->overlapping($start, $end));
    }

    /**
     * Add an `is_used` flag per garage for the given date, in one query (no N+1).
     *
     * @param  Builder<Garage>  $query
     */
    public function scopeWithUsageOn(Builder $query, CarbonInterface $date): void
    {
        $query->withExists(['rentals as is_used' => fn (Builder $rentals) => $rentals->activeOn($date)]);
    }

    /**
     * Whether a rental covers the given date. A rental that starts later does not count.
     */
    public function isUsedOn(CarbonInterface $date): bool
    {
        return $this->rentals()->activeOn($date)->exists();
    }

    /**
     * Price of one month: the daily price times the monthly multiplier.
     */
    public function monthlyPrice(): float
    {
        return (float) $this->daily_price * Rental::MONTHLY_PRICE_MULTIPLIER;
    }

    /**
     * Highest code number matched by the query, 0 when it matches nothing.
     *
     * @param  Builder<Garage>  $query  garages of one type
     */
    private static function highestCodeNumber(Builder $query, GarageType $type): int
    {
        // Codes are zero-padded, so the highest string is also the highest number.
        $highestCode = $query->max('code');

        return $highestCode === null ? 0 : (int) substr($highestCode, strlen($type->prefix()));
    }
}
