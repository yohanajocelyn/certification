# Garage rental plan

Goal: a Laravel MVC site where admins create garages in batches, verify tenants, extend rentals and see reports, and approved tenants rent garages for their vehicles.

Sources: `CLAUDE.md` (rules), `docs/database-structure.md` + `docs/erd.mmd` (schema and business rules), `docs/ui-reference.md` (look only).
Architecture: controllers -> Eloquent models, enums for fixed values, validation in controllers, no Form Requests / Services / Repositories.
Owners: `laravel-architect` (docs, review), `laravel-backend` (M + C + tests), `laravel-frontend` (views).

Each batch ends green: `php artisan test`, `./vendor/bin/pint`, `npm run build`, then a git checkpoint commit. No batch starts while the previous one is red.

## Decisions (answered 2026-11-21)

- [x] YES D1 Monthly length: one month = days in the start month (10 Feb 2027 -> 9 Mar, 28 days; 10 Jan -> 9 Feb, 31 days)?
- [x] YES D2 Monthly extension: every added month uses the day count of the month the rental currently ends in (ends in Jan, +2 months = +31 +31)?
- [x] NO D3 (keep the price copies) Drop `rentals.daily_price` / `kwh_price` copies too (garage prices can't be edited)?
- [x] YES D4 Vehicle input: step 1 lets the tenant pick one of their vehicles or add a new one?

## Routes overview

| Area | Method + URI | Name | Controller@action |
|---|---|---|---|
| Guest | GET/POST `/login` | `login`, `login.store` | `AuthController@create/store` |
| Guest | GET/POST `/register` | `register`, `register.store` | `RegisterController@create/store` |
| Auth | POST `/logout` | `logout` | `AuthController@destroy` |
| Auth | GET `/` | `home` | `HomeController` (redirect by role) |
| Admin | GET `/admin/garages`, GET `/admin/garages/create`, POST `/admin/garages` | `admin.garages.*` | `Admin\GarageController@index/create/store` |
| Admin | GET `/admin/tenants`, PATCH `/admin/tenants/{user}` | `admin.tenants.*` | `Admin\TenantController@index/update` |
| Admin | GET `/admin/rentals` (active today) | `admin.rentals.index` | `Admin\RentalController@index` |
| Admin | GET/POST `/admin/rentals/{rental}/extensions/create`, `/admin/rentals/{rental}/extensions` | `admin.rentals.extensions.*` | `Admin\RentalExtensionController@create/store` |
| Admin | GET `/admin/reports/{monthly,daily,garage-types,tenants}` | `admin.reports.*` | `Admin\ReportController@monthly/daily/garageTypes/tenants` |
| Tenant | GET `/rentals`, GET `/rentals/create`, GET `/rentals/preview`, POST `/rentals` | `rentals.*` | `RentalController@index/create/preview/store` |
| Tenant | GET `/vehicles` | `vehicles.index` | `VehicleController@index` (added later: replaces the "Sewa Baru" menu link) |

Middleware: `auth`, `guest`, and a small `role` alias (`EnsureUserHasRole`, takes a `UserRole` value) registered in `bootstrap/app.php`.

## Batch 0 - Foundation (no features)

Owner: laravel-architect, then laravel-backend.

- [x] `docs/erd.md` from `docs/erd.mmd` (Mermaid block + ON DELETE notes), keep both in sync
- [x] Apply D1-D4 answers to `docs/database-structure.md` / `docs/erd.mmd`
- [x] `config/app.php` timezone `env('APP_TIMEZONE', 'UTC')`; `.env.example`: `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`, MySQL `DB_*`
- [x] `config/admin.php` reading `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NIK`, `ADMIN_PHONE`; placeholders in `.env.example` (seeder reads `config('admin.*')`, never `env()`)
- [x] `lang/id/validation.php` for the rules and attribute names this app uses (Indonesian UI, English code)
- [x] `phpunit.xml` uses the separate MySQL database `garage_rental_testing` (CLAUDE.md §11 changed by the developer from SQLite to MySQL)
Done when: `php artisan test` green, `php artisan config:show admin` shows the keys.

## Batch 1 - Domain core: enums, migrations, models, factories

Owner: laravel-backend. Test first for every model helper.

- [x] Enums `app/Enums/`: `UserRole`, `UserStatus`, `GarageType` (`prefix()`, `label()`, `hasCharging()`), `VehicleType` (`allowedGarageTypes()`, `requiresPlate()`, `hasBattery()`, `label()`), `RentalType` (`label()`)
- [x] Migrations: alter `users` (nik, phone, role, status), `garages`, `vehicles`, `rentals` (+ 2 indexes), `rental_extensions`, exactly as `docs/database-structure.md`
- [x] Models with constants, `$fillable`, `casts()` (enums, dates, decimals), typed relations:
  - `User`: `isAdmin()`, `isApproved()`, `vehicles()`, `rentals()`
  - `Garage`: `MAX_BATCH_SIZE`, `MAX_CODE_NUMBER`, `nextCodeNumber()`, `formatCode()`, `scopeUsedOn()`, `scopeAvailableBetween()`, `isUsedOn()`, `monthlyPrice()`
  - `Vehicle`: `isParkedBetween()`
  - `Rental`: `MONTHLY_PRICE_MULTIPLIER`, `calculateEndDate()`, `maxDailyDays()`, `calculateRentalCost()`, `calculateChargingKwh()`, `calculateChargingCost()`, `scopeActiveOn()`, `scopeOverlapping()`
  - `RentalExtension`: `calculateMonthlyDays()`, `calculateAdditionalCost()`
- [x] Factories for all five models (states: `admin()`, `pending()`, `electric()`, `daily()`, `monthly()`...)
- [x] Unit tests (positive / negative / boundary): end dates (Feb, leap year, month end), max days per month, costs, charging (EV in E vs C garage, full battery = 0), code numbering (first = 001, continues per type), overlap edges (touching dates overlap because they are inclusive), vehicle-garage compatibility
Done when: all unit tests green.

## Batch 1b - Vehicle class hierarchy (OOP)

- [x] Done after Batch 7, see `docs/plans/oop-plan.md`: `Vehicle` parent implementing `Parkable`, children `Bicycle`, `Motorcycle`, `Car`, `ElectricCar` (single-table inheritance on `vehicles.type`); `VehicleType` keeps only cases and labels

## Change - rentals.user_id removed

- [x] A rental's tenant is traced through its vehicle (`$rental->vehicle->user`); `User::rentals()` is a HasManyThrough via `vehicles`. Migration edited in place (run `php artisan migrate:fresh --seed`); ERD and database structure updated

## Batch 2 - Layout, auth, roles, seeding

Owners: laravel-backend (controllers, middleware, seeders, tests) -> laravel-frontend (views).

- [x] `AuthController` create/store/destroy (pattern from DSC-Home-Assignment, rewritten: enum roles, no `Route::has` fallbacks). Login rejects pending / rejected tenants with their own message and logs them out
- [x] `RegisterController` create/store: NIK 16 digits unique, name, phone, email unique, password confirmed; creates `tenant` + `pending`, redirects to login with "waiting for verification"
- [x] `EnsureUserHasRole` middleware + alias; `HomeController` shows a welcome page for now (the garage list and my-rentals pages don't exist yet); Batches 3 and 5 add the navbar links and switch it to a role redirect
- [x] `AdminSeeder` (`updateOrCreate` by `config('admin.email')`), `DatabaseSeeder` with demo garages, tenants, vehicles, rentals via factories
- [x] Views: `x-layout` (sidebar menu per role, flash area), `x-button`, `x-link-button`, `x-input`, `x-select`, `x-alert`; pages `auth/login`, `auth/register`
- [x] Feature tests: register valid / invalid / NIK 15 & 17 digits / duplicate email & NIK; login approved / pending / rejected / wrong password; logout; tenant blocked from `/admin/*` (403), guest redirected to login
Done when: tests green, admin can log in from seeded `.env` data.

## Batch 3 - Admin: garages (batch create + list)

- [x] `Admin\GarageController` index (paginated, filter by type, status for today from `isUsedOn(today)`), create, store
- [x] Store: validate type, quantity 1..remaining for that type (max 999), daily_price > 0, kwh_price required for electric and prohibited otherwise; generate codes inside `DB::transaction` with a lock so two batches never reuse a number
- [x] Views: `admin/garages/index` (`x-table`, empty state), `admin/garages/create` (kWh field shown only for electric via small script)
- [x] Feature tests: S001.. first batch, continues from C010 -> C011, quantity 999 ok / 1000 fails / exceeding remaining fails, electric without kWh fails, non-electric with kWh fails, status shows "used" only while a rental covers today (future rental -> still available)
- [x] Admins now land on the garage list after login; navbar has the "Garasi" link; DemoSeeder uses `Garage::createBatch()`

## Batch 4 - Admin: tenant verification

- [x] `Admin\TenantController` index (pending first, filter by status), update (approve / reject, only from `pending`)
- [x] Views: `admin/tenants/index` with approve / reject buttons
- [x] Feature tests: approve, reject, cannot change an already decided tenant, cannot target an admin, approved tenant can now log in

## Batch 5 - Tenant: new rental (two-step) + my rentals

- [x] `RentalController@create` (step 1): vehicle (D4), current battery for EV, rental type, days (daily), start date (default today), garage type limited to the vehicle type
- [x] `RentalController@preview` (step 2, GET so a failed upload can return to it): validate step 1, compute end date, list garages of that type available for the whole period, each with its rental cost, charging cost and total; payment proof upload
- [x] `RentalController@store`: re-validate everything with the same private rules method, then inside `DB::transaction`: lock the garage row, re-check garage and vehicle availability, create vehicle if new, store proof on the `public` disk, create the rental
- [x] `RentalController@index`: my rentals with garage code, vehicle, dates, total, extensions, status (upcoming / active / finished, derived)
- [x] `resources/js/app.js`: show/hide plate, battery and days fields; filter garage-type options (UI only)
- [x] Views: `rentals/create`, `rentals/preview`, `rentals/index`
- [x] Feature tests (`Storage::fake`, `travelTo`): each vehicle -> garage rule and its rejections, bicycle without plate ok / motorcycle without plate fails, days 0 / max / max+1 per month (Feb 2027 max 27, Nov max 29), start yesterday fails / future ok, monthly end dates, garage taken in overlapping period is hidden and rejected on store, same vehicle in two garages at once rejected, current battery > capacity fails, charging only in E garage, proof missing / not an image / too large fails, tenant sees only own rentals, future rental leaves garage "available" today
- [x] Harian/Bulanan toggle: daily days editable up to (days in start month − 1); monthly days read-only and filled with the days in the start month (display only, server recalculates)
- [x] Tenants land on "Sewa Saya" after login

## Batch 6 - Admin: extend rental

- [x] `Admin\RentalController@index`: rentals active today (`start_date ≤ today ≤ end_date`)
- [x] `Admin\RentalExtensionController@create/preview/store` (two steps like the tenant flow): choose daily (new end date) or monthly (number of months, D2); preview remaining cost on the server before saving; proof upload; in one transaction: overlap checks for garage and vehicle (ignoring this rental), create extension, update `rentals.end_date`
- [x] Views: `admin/rentals/index`, `admin/rentals/extensions/create`
- [x] Feature tests: daily extension cost, monthly extension days + cost, new end date not after old fails, overlap with the next booking fails, inactive (finished / future) rental cannot be extended, no upper day limit
- [x] Failed step-2 checks always return to step 1 (both flows), so a failed save can never loop on the preview page
- [x] Tenant rental list shows extension costs under the total

## Batch 7 - Admin: reports

- [x] `Admin\ReportController`: monthly (year), daily (year + month), per garage type (period), per tenant (period). Each shows rental count and revenue = rental + charging + extensions. Rentals counted by `start_date`, extensions by their `created_at`
- [x] Load the period's rentals/extensions with Eloquent date scopes and group them in PHP collections (no raw SQL, per CLAUDE.md)
- [x] Views: `admin/reports/*` with filter forms and `x-table`, totals row, empty state
- [x] Feature tests: correct buckets and sums, extension revenue lands in its own month, empty period renders zeros, invalid year/month fails validation

## Batch 8 - Documentation and final check

Owner: laravel-architect.

- [ ] `docs/class-diagram.md` from the final code (controllers, models, class-based components only)
- [ ] `docs/test-report.md`: TC table per module, totals, `php artisan test` output
- [ ] README: description, setup (+ `php artisan storage:link`), tests, libraries table
- [ ] `./vendor/bin/pint`, `npm run build`, CLAUDE.md section 14.9 checklist, architect review of the whole app
