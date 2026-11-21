<?php

namespace Tests\Unit\Models;

use App\Enums\GarageType;
use App\Enums\RentalPeriodStatus;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class RentalTest extends TestCase
{
    use RefreshDatabase;

    private const DAILY_PRICE = 10_000.0;

    private const KWH_PRICE = 2_500.0;

    public function test_calculate_end_date_daily_counts_start_day_as_first_day(): void
    {
        $end = Rental::calculateEndDate(RentalType::Daily, CarbonImmutable::parse('2026-11-21'), 5);

        $this->assertSame('2026-11-25', $end->toDateString());
    }

    public function test_calculate_end_date_daily_one_day_ends_on_start_day(): void
    {
        $end = Rental::calculateEndDate(RentalType::Daily, CarbonImmutable::parse('2026-11-21'), 1);

        $this->assertSame('2026-11-21', $end->toDateString());
    }

    public function test_calculate_end_date_daily_without_days_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Rental::calculateEndDate(RentalType::Daily, CarbonImmutable::parse('2026-11-21'));
    }

    public function test_calculate_end_date_monthly_in_31_day_month_lasts_31_days(): void
    {
        $end = Rental::calculateEndDate(RentalType::Monthly, CarbonImmutable::parse('2027-01-10'));

        $this->assertSame('2027-02-09', $end->toDateString());
    }

    public function test_calculate_end_date_monthly_in_february_lasts_28_days(): void
    {
        $end = Rental::calculateEndDate(RentalType::Monthly, CarbonImmutable::parse('2027-02-10'));

        $this->assertSame('2027-03-09', $end->toDateString());
    }

    public function test_calculate_end_date_monthly_in_leap_february_lasts_29_days(): void
    {
        $end = Rental::calculateEndDate(RentalType::Monthly, CarbonImmutable::parse('2028-02-01'));

        $this->assertSame('2028-02-29', $end->toDateString());
    }

    public function test_calculate_end_date_monthly_from_month_end_uses_start_month_length(): void
    {
        $end = Rental::calculateEndDate(RentalType::Monthly, CarbonImmutable::parse('2027-01-31'));

        $this->assertSame('2027-03-02', $end->toDateString());
    }

    public function test_max_daily_days_is_days_in_start_month_minus_one(): void
    {
        $this->assertSame(29, Rental::maxDailyDays(CarbonImmutable::parse('2026-11-21')));
        $this->assertSame(30, Rental::maxDailyDays(CarbonImmutable::parse('2027-01-05')));
    }

    public function test_max_daily_days_in_february_depends_on_leap_year(): void
    {
        $this->assertSame(27, Rental::maxDailyDays(CarbonImmutable::parse('2027-02-01')));
        $this->assertSame(28, Rental::maxDailyDays(CarbonImmutable::parse('2028-02-01')));
    }

    public function test_calculate_rental_cost_daily_multiplies_days_by_daily_price(): void
    {
        $this->assertSame(50_000.0, Rental::calculateRentalCost(RentalType::Daily, self::DAILY_PRICE, 5));
    }

    public function test_calculate_rental_cost_monthly_is_25_times_daily_price(): void
    {
        $this->assertSame(250_000.0, Rental::calculateRentalCost(RentalType::Monthly, self::DAILY_PRICE));
    }

    public function test_charging_kwh_for_electric_car_in_electric_garage_fills_battery(): void
    {
        $kwh = Rental::calculateChargingKwh($this->electricCar(60), $this->garage(GarageType::Electric), 20);

        $this->assertSame(40.0, $kwh);
    }

    public function test_charging_kwh_for_electric_car_in_car_garage_is_zero(): void
    {
        $kwh = Rental::calculateChargingKwh($this->electricCar(60), $this->garage(GarageType::Car), 20);

        $this->assertSame(0.0, $kwh);
    }

    public function test_charging_kwh_for_full_battery_is_zero(): void
    {
        $kwh = Rental::calculateChargingKwh($this->electricCar(60), $this->garage(GarageType::Electric), 60);

        $this->assertSame(0.0, $kwh);
    }

    public function test_charging_kwh_for_petrol_car_is_zero(): void
    {
        $car = Vehicle::forType(VehicleType::Car);

        $this->assertSame(0.0, Rental::calculateChargingKwh($car, $this->garage(GarageType::Electric), null));
    }

    public function test_charging_cost_multiplies_kwh_by_kwh_price(): void
    {
        $this->assertSame(100_000.0, Rental::calculateChargingCost(40, self::KWH_PRICE));
    }

    public function test_active_on_includes_first_and_last_rented_day(): void
    {
        // Arrange: 21 Nov to 25 Nov
        Rental::factory()->daily(5)->startingOn('2026-11-21')->create();

        // Act + Assert
        $this->assertSame(1, Rental::activeOn(CarbonImmutable::parse('2026-11-21'))->count());
        $this->assertSame(1, Rental::activeOn(CarbonImmutable::parse('2026-11-25'))->count());
    }

    public function test_active_on_excludes_days_outside_the_rental(): void
    {
        Rental::factory()->daily(5)->startingOn('2026-11-21')->create();

        $this->assertSame(0, Rental::activeOn(CarbonImmutable::parse('2026-11-20'))->count());
        $this->assertSame(0, Rental::activeOn(CarbonImmutable::parse('2026-11-26'))->count());
    }

    public function test_overlapping_counts_shared_last_day_as_overlap(): void
    {
        Rental::factory()->daily(5)->startingOn('2026-11-21')->create();

        $overlaps = Rental::overlapping(CarbonImmutable::parse('2026-11-25'), CarbonImmutable::parse('2026-11-30'));

        $this->assertSame(1, $overlaps->count());
    }

    public function test_overlapping_excludes_period_starting_day_after_end(): void
    {
        Rental::factory()->daily(5)->startingOn('2026-11-21')->create();

        $overlaps = Rental::overlapping(CarbonImmutable::parse('2026-11-26'), CarbonImmutable::parse('2026-11-30'));

        $this->assertSame(0, $overlaps->count());
    }

    public function test_draft_for_electric_car_in_electric_garage_adds_charging_to_total(): void
    {
        // Arrange: 60 kWh battery at 20 kWh, 5 days at 10.000, 2.500 per kWh
        $garage = $this->garage(GarageType::Electric);

        // Act
        $draft = Rental::draft($garage, $this->electricCar(60), RentalType::Daily, CarbonImmutable::parse('2026-11-21'), 5, 20);

        // Assert
        $this->assertSame('2026-11-25', $draft->end_date->toDateString());
        $this->assertEquals(50_000, $draft->rental_cost);
        $this->assertEquals(40, $draft->charging_kwh);
        $this->assertEquals(100_000, $draft->charging_cost);
        $this->assertEquals(150_000, $draft->total_cost);
        $this->assertFalse($draft->exists);
    }

    public function test_draft_monthly_has_no_days_and_costs_25_daily_prices(): void
    {
        $car = Vehicle::forType(VehicleType::Car);

        $draft = Rental::draft($this->garage(GarageType::Car), $car, RentalType::Monthly, CarbonImmutable::parse('2027-02-10'), 12, null);

        $this->assertNull($draft->days);
        $this->assertSame('2027-03-09', $draft->end_date->toDateString());
        $this->assertEquals(250_000, $draft->total_cost);
    }

    public function test_draft_for_petrol_car_keeps_no_battery_level(): void
    {
        $car = Vehicle::forType(VehicleType::Car);

        $draft = Rental::draft($this->garage(GarageType::Car), $car, RentalType::Daily, CarbonImmutable::parse('2026-11-21'), 1, 50);

        $this->assertNull($draft->current_battery_kwh);
    }

    public function test_count_days_includes_first_and_last_day(): void
    {
        $this->assertSame(5, Rental::countDays(CarbonImmutable::parse('2026-11-21'), CarbonImmutable::parse('2026-11-25')));
        $this->assertSame(1, Rental::countDays(CarbonImmutable::parse('2026-11-21'), CarbonImmutable::parse('2026-11-21')));
    }

    public function test_period_status_on_is_upcoming_active_then_finished(): void
    {
        $rental = Rental::factory()->daily(5)->startingOn('2026-11-21')->make();

        $this->assertSame(RentalPeriodStatus::Upcoming, $rental->periodStatusOn(CarbonImmutable::parse('2026-11-20')));
        $this->assertSame(RentalPeriodStatus::Active, $rental->periodStatusOn(CarbonImmutable::parse('2026-11-21')));
        $this->assertSame(RentalPeriodStatus::Active, $rental->periodStatusOn(CarbonImmutable::parse('2026-11-25')));
        $this->assertSame(RentalPeriodStatus::Finished, $rental->periodStatusOn(CarbonImmutable::parse('2026-11-26')));
    }

    public function test_is_active_on_only_between_start_and_end(): void
    {
        $rental = Rental::factory()->daily(5)->startingOn('2026-11-21')->make();

        $this->assertTrue($rental->isActiveOn(CarbonImmutable::parse('2026-11-25')));
        $this->assertFalse($rental->isActiveOn(CarbonImmutable::parse('2026-11-26')));
        $this->assertFalse($rental->isActiveOn(CarbonImmutable::parse('2026-11-20')));
    }

    public function test_starting_between_includes_both_end_days(): void
    {
        Rental::factory()->startingOn('2026-11-01')->create();
        Rental::factory()->startingOn('2026-11-30')->create();
        Rental::factory()->startingOn('2026-12-01')->create();

        $count = Rental::startingBetween(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'))->count();

        $this->assertSame(2, $count);
    }

    public function test_rentals_table_keeps_no_copy_of_the_garage_prices(): void
    {
        // Garage prices never change after creation, so the rental reads them from its garage.
        $this->assertFalse(Schema::hasColumn('rentals', 'daily_price'));
        $this->assertFalse(Schema::hasColumn('rentals', 'kwh_price'));
    }

    public function test_rentals_table_has_no_user_id_column(): void
    {
        $this->assertFalse(Schema::hasColumn('rentals', 'user_id'));
    }

    public function test_tenant_of_a_rental_is_the_owner_of_its_vehicle(): void
    {
        $tenant = User::factory()->create();
        $rental = Rental::factory()->for(Vehicle::factory()->for($tenant))->create();

        $this->assertTrue($rental->vehicle->user->is($tenant));
    }

    public function test_tenant_rentals_are_found_through_their_own_vehicles_only(): void
    {
        $tenant = User::factory()->create();
        $car = Vehicle::factory()->for($tenant)->create();
        $bicycle = Vehicle::factory()->bicycle()->for($tenant)->create();
        $ownRentals = [
            Rental::factory()->for($car, 'vehicle')->create(),
            Rental::factory()->for($bicycle, 'vehicle')->create(),
        ];
        Rental::factory()->create();

        $this->assertEqualsCanonicalizing(
            array_map(fn (Rental $rental) => $rental->id, $ownRentals),
            $tenant->rentals()->pluck('rentals.id')->all(),
        );
    }

    public function test_tenant_with_rentals_cannot_be_deleted_so_history_is_kept(): void
    {
        // Deleting the user cascades to the vehicles, which rentals restrict.
        $rental = Rental::factory()->create();
        $this->expectException(QueryException::class);

        try {
            $rental->vehicle->user->delete();
        } finally {
            $this->assertDatabaseHas('rentals', ['id' => $rental->id]);
        }
    }

    private function electricCar(float $capacityKwh): Vehicle
    {
        return Vehicle::forType(VehicleType::ElectricCar)->fill(['battery_capacity_kwh' => $capacityKwh]);
    }

    private function garage(GarageType $type): Garage
    {
        return new Garage(['type' => $type, 'daily_price' => self::DAILY_PRICE, 'kwh_price' => self::KWH_PRICE]);
    }
}
