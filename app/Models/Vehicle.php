<?php

namespace App\Models;

use App\Contracts\Parkable;
use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Models\Vehicles\Bicycle;
use App\Models\Vehicles\Car;
use App\Models\Vehicles\ElectricCar;
use App\Models\Vehicles\Motorcycle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Parent of every vehicle a tenant can park. All types share the `vehicles` table (single-table
 * inheritance on `type`); each child class holds the parking rules of its type. Vehicles are never
 * edited, so rentals reference them directly.
 */
class Vehicle extends Model implements Parkable
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /** The child class for each stored `type` value. Adding a vehicle type = a new child + one entry here. */
    public const SUBCLASSES = [
        VehicleType::Bicycle->value => Bicycle::class,
        VehicleType::Motorcycle->value => Motorcycle::class,
        VehicleType::Car->value => Car::class,
        VehicleType::ElectricCar->value => ElectricCar::class,
    ];

    /** The type a child class stands for; null on this parent class. */
    public const ?VehicleType TYPE = null;

    /** Letters, digits and single spaces, e.g. "B 1234 XYZ". */
    public const PLATE_PATTERN = '/^[A-Za-z0-9]+( [A-Za-z0-9]+)*$/';

    /** Children would otherwise get tables named after their class (electric_cars). */
    protected $table = 'vehicles';

    /** `type` is left out on purpose: the class decides it, so a car can never be saved as a bicycle. */
    protected $fillable = ['plate_number', 'brand_model', 'battery_capacity_kwh'];

    /**
     * Children store their own type, so a new ElectricCar is saved with type electric_car.
     */
    public function __construct(array $attributes = [])
    {
        if (static::TYPE !== null) {
            $this->attributes['type'] = static::TYPE->value;
        }

        parent::__construct($attributes);
    }

    /**
     * Children query only their own rows, e.g. ElectricCar::all() returns only electric cars.
     */
    protected static function booted(): void
    {
        if (static::TYPE !== null) {
            static::addGlobalScope('vehicle_type', fn (Builder $query) => $query->where(
                $query->qualifyColumn('type'),
                static::TYPE->value,
            ));
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'battery_capacity_kwh' => 'decimal:'.config('rules.vehicle.kwh_scale'),
        ];
    }

    /**
     * A new, unsaved vehicle of the right child class for the type.
     */
    public static function forType(VehicleType $type): self
    {
        $class = self::SUBCLASSES[$type->value];

        return new $class;
    }

    /**
     * Turn each row loaded from the database into the child class of its `type`
     * (Vehicle::find(), $user->vehicles and $rental->vehicle all return Car, ElectricCar, ...).
     *
     * @param  array<string, mixed>|object  $attributes
     * @param  string|null  $connection
     * @return static
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $attributes = (array) $attributes;
        $class = self::SUBCLASSES[$attributes['type'] ?? ''] ?? self::class;

        $model = (new $class)->newInstance([], true);
        $model->setRawAttributes($attributes, true);
        $model->setConnection($connection ?? $this->getConnectionName());
        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * Children share the parent's foreign key: rentals.vehicle_id, not rentals.electric_car_id.
     */
    public function getForeignKey(): string
    {
        return 'vehicle_id';
    }

    /**
     * Garage types this vehicle may be parked in. A vehicle of unknown type fits nowhere.
     *
     * @return list<GarageType>
     */
    public function allowedGarageTypes(): array
    {
        return [];
    }

    /**
     * Whether a plate number is required. Most vehicles need one.
     */
    public function requiresPlate(): bool
    {
        return true;
    }

    /**
     * Whether battery capacity and current battery level must be entered.
     */
    public function hasBattery(): bool
    {
        return false;
    }

    /**
     * kWh needed to charge to full. Only vehicles with a battery override this.
     *
     * @param  float|null  $currentBatteryKwh  battery level entered by the tenant
     */
    public function chargingKwh(?float $currentBatteryKwh): float
    {
        return 0.0;
    }

    /**
     * Whether this vehicle may be parked in the given garage type.
     */
    public function canParkIn(GarageType $garageType): bool
    {
        return in_array($garageType, $this->allowedGarageTypes(), true);
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
        return implode(' · ', array_filter([$this->type?->label(), $this->brand_model, $this->plate_number]));
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
     * Rentals covering today (at most one: a vehicle is in one garage at a time). Eager-load it to
     * show where every vehicle is parked in one query.
     */
    public function rentalsToday(): HasMany
    {
        return $this->rentals()->activeOn(CarbonImmutable::today());
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

    /**
     * Factory for this class; children get their own type's factory state (ElectricCar::factory()).
     */
    protected static function newFactory(): VehicleFactory
    {
        return VehicleFactory::new()->ofType(static::TYPE ?? VehicleType::Car);
    }
}
