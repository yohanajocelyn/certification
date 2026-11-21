## Batch 1b - Vehicle hierarchy (OOP)

Goal: replace VehicleType behavior methods with a class hierarchy.
Schema unchanged (single-table inheritance on `vehicles.type`).
Owner: laravel-backend, then laravel-architect (review + diagram).
Status: done 2026-11-21. Deviations, agreed in chat:
- Charging uses polymorphism instead of an `instanceof` check: `Vehicle::chargingKwh()` returns 0 and `ElectricCar` overrides it; `Rental::calculateChargingKwh()` calls it for garages that charge.
- `type` is not in `$fillable`; children set it from their `TYPE` constant (one place instead of `$attributes` + scope).
- `SUBCLASSES` keys are `VehicleType` values (PHP arrays cannot use enum cases as keys).
- Extra: `getForeignKey()` returns `vehicle_id` (children would guess `electric_car_id`), the factory builds the child class (`newModel()`), and factory `->for($vehicle, 'vehicle')` names the relation because the class name no longer matches it.

### Contract
- [x] app/Contracts/Parkable.php
      allowedGarageTypes(): array<GarageType>
      requiresPlate(): bool
      hasBattery(): bool

### Parent
- [x] app/Models/Vehicle.php implements Parkable (concrete, not abstract)
      - shared: $table = 'vehicles', $fillable, casts(), user(), rentals()
      - defaults: requiresPlate() true, hasBattery() false,
        allowedGarageTypes() []
      - canParkIn(GarageType $type): bool  (uses allowedGarageTypes)
      - const SUBCLASSES = [VehicleType => class] map
      - newFromBuilder(): returns the subclass for `type`
      - static forType(VehicleType $type): Vehicle (new instance of subclass)

### Children (app/Models/Vehicles/)
- [x] Bicycle      requiresPlate() false; garages [Bike]
- [x] Motorcycle   garages [Bike]
- [x] Car          garages [Car]
- [x] ElectricCar  hasBattery() true; garages [Car, Electric];
                   chargingKwh(float $current): float  (capacity - current, min 0)
- [x] Each child: protected $attributes = ['type' => VehicleType::X]
      and a global scope `where type = X` (booted()), so
      ElectricCar::all() returns only electric cars

### Enum cleanup
- [x] VehicleType keeps only cases + label(); remove
      allowedGarageTypes / requiresPlate / hasBattery

### Call sites
- [x] Rental::calculateChargingKwh() delegates to ElectricCar::chargingKwh()
      only when $vehicle instanceof ElectricCar and garage is Electric
- [x] RentalController: build new vehicles with Vehicle::forType($type)->fill(...)
- [x] Validation: plate required_if requiresPlate(), battery required_if hasBattery()
- [x] VehicleFactory: states bicycle(), motorcycle(), car(), electricCar()

### Tests (unit)
- [x] Each subclass: allowedGarageTypes, requiresPlate, hasBattery
- [x] canParkIn positive + negative per type
- [x] Vehicle::find() returns the right subclass for each type
- [x] ElectricCar::all() excludes other types
- [x] chargingKwh: normal, full battery = 0, current > capacity = 0
- [x] Existing rental/charging tests still green

### Docs
- [x] docs/class-diagram.md: Parkable <<interface>> realized by Vehicle,
      4 subclasses with inheritance arrows, Vehicle -> User / Rental
- [x] docs/database-structure.md: note STI on vehicles.type

Done when: php artisan test, pint, npm run build all green; commit.