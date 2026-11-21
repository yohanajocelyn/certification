# Class diagram

Drawn from the code, following CLAUDE.md section 12: controllers, models, class-based components and
the interface they realise; no views. Laravel's own parents are name-only boxes. The full diagram
(controllers and the other models) is completed in the documentation batch.

## Vehicle hierarchy (single-table inheritance on `vehicles.type`)

```mermaid
classDiagram
    direction TB

    class Model
    class Parkable {
        <<interface>>
        +allowedGarageTypes() array
        +requiresPlate() bool
        +hasBattery() bool
    }
    class VehicleType {
        <<enumeration>>
        Bicycle
        Motorcycle
        Car
        ElectricCar
        +label() string
    }

    class Vehicle {
        +array SUBCLASSES
        +VehicleType TYPE
        +int PLATE_MAX_LENGTH
        +int BRAND_MODEL_MAX_LENGTH
        +float MAX_BATTERY_KWH
        #string table
        #array fillable
        +forType(type: VehicleType) Vehicle
        +newFromBuilder(attributes: array, connection: string) Vehicle
        +getForeignKey() string
        +allowedGarageTypes() array
        +requiresPlate() bool
        +hasBattery() bool
        +chargingKwh(currentBatteryKwh: float) float
        +canParkIn(garageType: GarageType) bool
        +normalizePlate(plate: string) string
        +displayName() string
        +user() BelongsTo
        +rentals() HasMany
        +rentalsToday() HasMany
        +isParkedBetween(start: CarbonInterface, end: CarbonInterface, ignore: Rental) bool
        #casts() array
        #booted() void
    }
    class Bicycle {
        +VehicleType TYPE
        +allowedGarageTypes() array
        +requiresPlate() bool
    }
    class Motorcycle {
        +VehicleType TYPE
        +allowedGarageTypes() array
    }
    class Car {
        +VehicleType TYPE
        +allowedGarageTypes() array
    }
    class ElectricCar {
        +VehicleType TYPE
        +allowedGarageTypes() array
        +hasBattery() bool
        +chargingKwh(currentBatteryKwh: float) float
    }

    class User
    class Rental

    Model <|-- Vehicle
    Parkable <|.. Vehicle
    Vehicle <|-- Bicycle
    Vehicle <|-- Motorcycle
    Vehicle <|-- Car
    Vehicle <|-- ElectricCar
    Vehicle ..> VehicleType
    User "1" -- "0..*" Vehicle : owns
    Vehicle "1" -- "0..*" Rental : is parked in
```

| OOP principle | Where it shows |
|---|---|
| Inheritance | `Bicycle`, `Motorcycle`, `Car`, `ElectricCar` extend `Vehicle`, which extends Eloquent `Model` |
| Polymorphism | Each child overrides `allowedGarageTypes()` / `requiresPlate()` / `hasBattery()`; `ElectricCar` overrides `chargingKwh()`, so `Rental` asks any vehicle the same question without checking its class |
| Abstraction | `Parkable` states what every parkable thing must answer; callers use `canParkIn()` without knowing the type |
| Encapsulation | `type` is not mass-assignable: the class sets it (`forType()`, the child's `TYPE`), so a car can never be stored as a bicycle |
| Open/Closed | A new vehicle type = a new child class + one `SUBCLASSES` entry, without editing the rental code |
