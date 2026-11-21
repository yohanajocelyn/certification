<?php

namespace Tests\Unit\Models;

use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_max_daily_days_is_days_in_start_month_minus_one(): void
    {
        $this->assertSame(29, Rental::maxDailyDays(CarbonImmutable::parse('2026-11-21')));
        $this->assertSame(30, Rental::maxDailyDays(CarbonImmutable::parse('2027-01-05')));
    }

    public function test_calculate_rental_cost_daily_multiplies_days_by_daily_price(): void
    {
        $this->assertSame(50_000.0, Rental::calculateRentalCost(RentalType::Daily, self::DAILY_PRICE, 5));
    }

    public function test_calculate_rental_cost_monthly_is_25_times_daily_price(): void
    {
        $this->assertSame(250_000.0, Rental::calculateRentalCost(RentalType::Monthly, self::DAILY_PRICE));
    }

    public function test_charging_kwh_for_electric_car_in_car_garage_is_zero(): void
    {
        $kwh = Rental::calculateChargingKwh($this->electricCar(60), $this->garage(GarageType::Car), 20);

        $this->assertSame(0.0, $kwh);
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

    private function electricCar(float $capacityKwh): Vehicle
    {
        return Vehicle::forType(VehicleType::ElectricCar)->fill(['battery_capacity_kwh' => $capacityKwh]);
    }

    private function garage(GarageType $type): Garage
    {
        return new Garage(['type' => $type, 'daily_price' => self::DAILY_PRICE, 'kwh_price' => self::KWH_PRICE]);
    }
}
