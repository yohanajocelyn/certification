<?php

namespace App\Models;

use App\Enums\GarageType;
use Carbon\CarbonInterface;
use Database\Factories\GarageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public const MAX_BATCH_SIZE = 999;

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
        // Codes are zero-padded, so the highest string is also the highest number.
        $highestCode = static::query()->where('type', $type)->max('code');

        if ($highestCode === null) {
            return 1;
        }

        return (int) substr($highestCode, strlen($type->prefix())) + 1;
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
}
