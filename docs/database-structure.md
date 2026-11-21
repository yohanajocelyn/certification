### Tables

**users**

| Column | Type | Rules |
|---|---|---|
| id | bigint PK | |
| nik | char(16) | UNIQUE, NOT NULL (`User::NIK_LENGTH = 16`), digits only |
| name | varchar(100) | NOT NULL |
| phone | varchar(20) | NOT NULL |
| email | varchar(255) | UNIQUE, NOT NULL, used as the username |
| password | varchar(255) | NOT NULL, hashed |
| role | varchar(20) | NOT NULL, `UserRole`: `admin`, `tenant`, default `tenant` |
| status | varchar(20) | NOT NULL, `UserStatus`: `pending`, `approved`, `rejected`, default `pending` |
| remember_token, timestamps | | |

The admin account is seeded from `.env` through `config/admin.php` (no admin registration).

**garages**

| Column | Type | Rules |
|---|---|---|
| id | bigint PK | |
| code | char(4) | UNIQUE, NOT NULL, e.g. `S001`, `C010`, `E002` |
| type | varchar(20) | NOT NULL, `GarageType`: `bike` (S), `car` (C), `electric` (E) |
| daily_price | decimal(12,2) | NOT NULL, > 0 |
| kwh_price | decimal(12,2) | NULL, required only when type = `electric` |
| timestamps | | |

No stored status column. "Used" / "available" is worked out from the rentals for the current date (see business rules), so no cron job is needed.

**vehicles**

| Column | Type | Rules |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK → users.id | NOT NULL, ON DELETE CASCADE (a vehicle means nothing without its owner) |
| type | varchar(20) | NOT NULL, `VehicleType`: `bicycle`, `motorcycle`, `car`, `electric_car` |
| plate_number | varchar(15) | NULL only for bicycles, UNIQUE (MySQL allows many NULLs) |
| brand_model | varchar(100) | NOT NULL, e.g. "Toyota Avanza" |
| battery_capacity_kwh | decimal(6,2) | NULL, required only for `electric_car` (full charge capacity) |
| timestamps | | |

Vehicles cannot be edited, so rentals reference them directly with no copied vehicle details.

Single-table inheritance: every vehicle type lives in this one table. `type` decides the class each row is loaded as (`Vehicle::SUBCLASSES`): `bicycle` → `Bicycle`, `motorcycle` → `Motorcycle`, `car` → `Car`, `electric_car` → `ElectricCar`, all children of `App\Models\Vehicle`. Columns a type does not use stay NULL (`plate_number` for bicycles, `battery_capacity_kwh` for everything but electric cars).

**rentals**

| Column | Type | Rules |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK → users.id | NOT NULL, ON DELETE RESTRICT |
| garage_id | bigint FK → garages.id | NOT NULL, ON DELETE RESTRICT |
| vehicle_id | bigint FK → vehicles.id | NOT NULL, ON DELETE RESTRICT (rental history keeps its vehicle) |
| current_battery_kwh | decimal(6,2) | NULL, battery level entered at rental time (electric cars only), must be ≤ capacity |
| rental_type | varchar(20) | NOT NULL, `RentalType`: `daily`, `monthly` |
| days | unsigned smallint | NULL, only for daily, 1 to (days in start month − 1) |
| start_date | date | NOT NULL, today or later |
| end_date | date | NOT NULL, calculated, **inclusive** (last rented day); updated when the rental is extended |
| daily_price | decimal(12,2) | NOT NULL, copy of the garage price at rental time (kept on purpose, used for extensions and reports) |
| kwh_price | decimal(12,2) | NULL, copy of the garage kWh price at rental time |
| rental_cost | decimal(12,2) | NOT NULL |
| charging_kwh | decimal(6,2) | NOT NULL, default 0 |
| charging_cost | decimal(12,2) | NOT NULL, default 0 |
| total_cost | decimal(12,2) | NOT NULL, rental_cost + charging_cost |
| payment_proof | varchar(255) | NOT NULL, stored image path |
| timestamps | | |
| index | (garage_id, start_date, end_date) | speeds up the garage overlap check |
| index | (vehicle_id, start_date, end_date) | speeds up the vehicle overlap check |

**rental_extensions**

| Column | Type | Rules |
|---|---|---|
| id | bigint PK | |
| rental_id | bigint FK → rentals.id | NOT NULL, ON DELETE CASCADE (an extension means nothing without its rental) |
| extension_type | varchar(20) | NOT NULL, `RentalType`: `daily`, `monthly` |
| months | unsigned smallint | NULL, only for monthly extensions, ≥ 1 |
| days | unsigned smallint | NOT NULL, number of days added |
| old_end_date | date | NOT NULL |
| new_end_date | date | NOT NULL, > old_end_date, no day limit |
| additional_cost | decimal(12,2) | NOT NULL |
| payment_proof | varchar(255) | NOT NULL, stored image path |
| timestamps | | |

### Relationships

| From | To | Cardinality | FK and ON DELETE |
|---|---|---|---|
| User | Vehicle | 1 – N | vehicles.user_id, CASCADE |
| User | Rental | 1 – N | rentals.user_id, RESTRICT |
| Garage | Rental | 1 – N | rentals.garage_id, RESTRICT |
| Vehicle | Rental | 1 – N | rentals.vehicle_id, RESTRICT |
| Rental | RentalExtension | 1 – N | rental_extensions.rental_id, CASCADE |
| User | Garage | M – N through `rentals` | |

### ERD (Mermaid)

See `erd.md` (rendered) and `erd.mmd` (same diagram, raw).

### Business rules (where each one lives)

All rental dates are inclusive: a rental covers every day from `start_date` to `end_date`.

| Rule | Where |
|---|---|
| Monthly price = 25 × daily price | `Rental::MONTHLY_PRICE_MULTIPLIER = 25` |
| Daily end date = start + days − 1 (21 Nov, 5 days → 25 Nov) | `Rental::calculateEndDate()` |
| Monthly end date: one month = number of days in the start month, so end = start + that count − 1 (10 Jan → 9 Feb, 10 Feb 2027 → 9 Mar, 1 Feb 2028 → 29 Feb) | `Rental::calculateEndDate()` |
| Daily max days = days in the start month − 1 | `Rental::maxDailyDays(Carbon $start)` |
| A new rental is at most 1 month | follows from the two rules above |
| Start date defaults to today, may be in the future, never in the past | controller validation `after_or_equal:today` |
| Garage is "used" on a date when a rental covers it: `start_date ≤ date ≤ end_date`. A rental starting in the future does not make the garage used yet | `Garage::isUsedOn()` / `Garage::scopeUsedOn()` |
| Garage free for a period = no rental overlapping it: `existing.start ≤ new.end AND new.start ≤ existing.end` | `Garage::scopeAvailableBetween($start, $end)` |
| One vehicle per garage, and one garage per vehicle at a time | garage overlap check + `Vehicle::isParkedBetween($start, $end)` |
| On a new rental the tenant picks one of their own vehicles or adds a new one (a new vehicle is saved to `vehicles`) | `RentalController` validation: `vehicle_id` must belong to the tenant |
| Bicycle, motorcycle → bike garage. Car → car garage. Electric car → car or electric garage | `allowedGarageTypes()` overridden in each `Vehicle` child; `Vehicle::canParkIn()` |
| Plate required except bicycle | `Vehicle::requiresPlate()` (true), overridden by `Bicycle` |
| Battery capacity and current battery required for electric car (any garage) | `Vehicle::hasBattery()` (false), overridden by `ElectricCar` |
| Charging = (capacity − current) kWh × kwh_price, only for an electric car in an electric garage | `Vehicle::chargingKwh()` (0) overridden by `ElectricCar`; `Rental::calculateChargingKwh()` asks it only for garages that charge |
| Garage code = type prefix + 3-digit per-type increment | `GarageType::prefix()` + `Garage::nextCodeNumber(GarageType)` |
| Max 999 garages per batch, and per type (3 digits) | `Garage::MAX_BATCH_SIZE = 999`, `Garage::MAX_CODE_NUMBER = 999` |
| Daily extension: admin picks a later end date, no day limit; cost = extra days × daily_price | `RentalExtension::calculateAdditionalCost()` |
| Monthly extension: admin picks a number of months; each month adds the day count of the month the rental currently ends in (ends in January → +31 per month); cost = months × 25 × daily_price | `RentalExtension::calculateMonthlyDays()` / `calculateAdditionalCost()` |
| Extension must not overlap another rental of the same garage or of the same vehicle | overlap scopes, ignoring the rental being extended |
| Extending updates `rentals.end_date` and stores the extension row in one transaction | `RentalExtensionController::store()` |
| Only approved tenants can log in / rent; pending and rejected see a message | `AuthController::login()` checks `UserStatus::Approved` |
