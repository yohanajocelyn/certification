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

    protected $fillable = ['code', 'type', 'daily_price', 'kwh_price'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GarageType::class,
            'daily_price' => 'decimal:'.config('rules.garage.price_scale'),
            'kwh_price' => 'decimal:'.config('rules.garage.price_scale'),
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
        // Codes are zero-padded, so the highest string is also the highest number.
        $highestCode = static::query()->where('type', $type)->max('code');

        return $highestCode === null ? 1 : self::codeNumber($highestCode) + 1;
    }

    /**
     * The number inside a garage code, e.g. "C010" -> 10.
     */
    public static function codeNumber(string $code): int
    {
        return (int) substr($code, -config('rules.garage.code_number_digits'));
    }

    /**
     * How many more garages of this type fit before config('rules.garage.max_code_number') (999).
     */
    public static function remainingCodeNumbers(GarageType $type): int
    {
        return config('rules.garage.max_code_number') - (self::nextCodeNumber($type) - 1);
    }

    /**
     * Build a garage code from its type and number, e.g. (Car, 10) -> "C010".
     */
    public static function formatCode(GarageType $type, int $number): string
    {
        return $type->prefix().str_pad((string) $number, config('rules.garage.code_number_digits'), '0', STR_PAD_LEFT);
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
     * Used means a rental covers the date; a rental that starts later does not count.
     *
     * @param  Builder<Garage>  $query
     */
    public function scopeWithUsageOn(Builder $query, CarbonInterface $date): void
    {
        $query->withExists(['rentals as is_used' => fn (Builder $rentals) => $rentals->activeOn($date)]);
    }

    /**
     * Price of one month: the daily price times the monthly multiplier.
     */
    public function monthlyPrice(): float
    {
        return (float) $this->daily_price * config('rules.rental.monthly_price_multiplier');
    }
}
