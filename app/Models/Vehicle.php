<?php

namespace App\Models;

use App\Enums\VehicleType;
use Carbon\CarbonInterface;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant's vehicle. Vehicles are never edited, so rentals reference them directly.
 */
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    public const PLATE_MAX_LENGTH = 15;

    public const BRAND_MODEL_MAX_LENGTH = 100;

    public const KWH_PRECISION = 6;

    public const KWH_SCALE = 2;

    /** Largest battery value a decimal(6,2) column holds. */
    public const MAX_BATTERY_KWH = 9_999.99;

    /** Letters, digits and single spaces, e.g. "B 1234 XYZ". */
    public const PLATE_PATTERN = '/^[A-Za-z0-9]+( [A-Za-z0-9]+)*$/';

    protected $fillable = ['type', 'plate_number', 'brand_model', 'battery_capacity_kwh'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'battery_capacity_kwh' => 'decimal:'.self::KWH_SCALE,
        ];
    }

    /**
     * Plates are stored in capitals without extra spaces, so "b  1234 xyz" and "B 1234 XYZ" match.
     */
    protected function plateNumber(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => self::normalizePlate($value));
    }

    /**
     * Capitalise a plate and squeeze repeated spaces; blank becomes null (bicycles have no plate).
     */
    public static function normalizePlate(?string $plate): ?string
    {
        $squeezed = trim((string) preg_replace('/\s+/', ' ', (string) $plate));

        return $squeezed === '' ? null : strtoupper($squeezed);
    }

    /**
     * Short description for lists, e.g. "Mobil · Toyota Avanza · B 1234 XYZ".
     */
    public function displayName(): string
    {
        return implode(' · ', array_filter([$this->type->label(), $this->brand_model, $this->plate_number]));
    }

    /**
     * The tenant who owns this vehicle.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Every rental this vehicle has been parked in.
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Whether this vehicle already has a garage for any day of the period.
     *
     * @param  Rental|null  $ignore  a rental to leave out, e.g. the one being extended
     */
    public function isParkedBetween(CarbonInterface $start, CarbonInterface $end, ?Rental $ignore = null): bool
    {
        return $this->rentals()
            ->overlapping($start, $end)
            ->when($ignore, fn ($rentals) => $rentals->whereKeyNot($ignore->getKey()))
            ->exists();
    }
}
