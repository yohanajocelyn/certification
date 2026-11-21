# ERD - Garage rental

Source of truth for the schema; the migrations must match it exactly. The same diagram is kept raw in `erd.mmd`.
Column details and business rules: `database-structure.md`. Every table also has `created_at` / `updated_at`.

```mermaid
erDiagram
    USERS ||--o{ VEHICLES : "owns (ON DELETE CASCADE)"
    USERS ||--o{ RENTALS : "makes (ON DELETE RESTRICT)"
    GARAGES ||--o{ RENTALS : "is rented in (ON DELETE RESTRICT)"
    VEHICLES ||--o{ RENTALS : "is parked in (ON DELETE RESTRICT)"
    RENTALS ||--o{ RENTAL_EXTENSIONS : "is extended by (ON DELETE CASCADE)"

    USERS {
        bigint id PK
        char nik "UNIQUE, NOT NULL"
        varchar name "NOT NULL"
        varchar phone "NOT NULL"
        varchar email "UNIQUE, NOT NULL"
        varchar password "NOT NULL"
        varchar role "admin | tenant"
        varchar status "pending | approved | rejected"
    }
    GARAGES {
        bigint id PK
        char code "UNIQUE, S001 / C001 / E001"
        varchar type "bike | car | electric"
        decimal daily_price "NOT NULL"
        decimal kwh_price "NULL, electric only"
    }
    VEHICLES {
        bigint id PK
        bigint user_id FK "NOT NULL"
        varchar type "bicycle | motorcycle | car | electric_car"
        varchar plate_number "UNIQUE, NULL for bicycle"
        varchar brand_model "NOT NULL"
        decimal battery_capacity_kwh "NULL, electric only"
    }
    RENTALS {
        bigint id PK
        bigint user_id FK "NOT NULL"
        bigint garage_id FK "NOT NULL"
        bigint vehicle_id FK "NOT NULL"
        decimal current_battery_kwh "NULL, electric only"
        varchar rental_type "daily | monthly"
        smallint days "NULL, daily only"
        date start_date "NOT NULL"
        date end_date "NOT NULL, inclusive"
        decimal daily_price "price snapshot"
        decimal kwh_price "price snapshot, NULL"
        decimal rental_cost "NOT NULL"
        decimal charging_kwh "default 0"
        decimal charging_cost "default 0"
        decimal total_cost "NOT NULL"
        varchar payment_proof "image path"
    }
    RENTAL_EXTENSIONS {
        bigint id PK
        bigint rental_id FK "NOT NULL"
        varchar extension_type "daily | monthly"
        smallint months "NULL, monthly only"
        smallint days "NOT NULL, days added"
        date old_end_date "NOT NULL"
        date new_end_date "NOT NULL"
        decimal additional_cost "NOT NULL"
        varchar payment_proof "image path"
    }
```

## Relationships

| Relationship | Cardinality | Foreign key | ON DELETE | Why |
|---|---|---|---|---|
| User owns Vehicle | 1 : N | `vehicles.user_id` | CASCADE | A vehicle has no meaning without its owner |
| User makes Rental | 1 : N | `rentals.user_id` | RESTRICT | Rental history must be kept |
| Garage is rented in Rental | 1 : N | `rentals.garage_id` | RESTRICT | A garage with rentals cannot disappear |
| Vehicle is parked in Rental | 1 : N | `rentals.vehicle_id` | RESTRICT | Rentals keep their vehicle (no copied vehicle details) |
| Rental is extended by RentalExtension | 1 : N | `rental_extensions.rental_id` | CASCADE | An extension has no meaning without its rental |
| User rents Garage | M : N | through `rentals` | | |

## Constraints

| Table | Constraint |
|---|---|
| users | `nik` UNIQUE (16 digits), `email` UNIQUE |
| garages | `code` UNIQUE (S/C/E + 3 digits) |
| vehicles | `plate_number` UNIQUE, NULL only for bicycles |
| rentals | index `(garage_id, start_date, end_date)`, index `(vehicle_id, start_date, end_date)` |
