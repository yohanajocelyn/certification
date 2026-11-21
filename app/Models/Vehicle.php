<?php

namespace App\Models;

use App\Enums\VehicleType;
use Carbon\CarbonInterface;
use Database\Factories\VehicleFactory;
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
