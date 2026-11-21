<?php

namespace Tests\Unit\Models;

use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RentalExtensionTest extends TestCase
{
    use RefreshDatabase;

    private const DAILY_PRICE = 10_000.0;

    public function test_calculate_extra_days_counts_days_between_end_dates(): void
    {
        $days = RentalExtension::calculateExtraDays(CarbonImmutable::parse('2026-11-25'), CarbonImmutable::parse('2026-11-30'));

        $this->assertSame(5, $days);
    }

    public function test_calculate_monthly_days_uses_current_end_month_for_every_month(): void
    {
        // Ends in January: 2 months = 31 + 31 days.
        $this->assertSame(62, RentalExtension::calculateMonthlyDays(CarbonImmutable::parse('2027-01-20'), 2));
    }

    public function test_calculate_monthly_days_for_february_end_uses_28_days(): void
    {
        $this->assertSame(28, RentalExtension::calculateMonthlyDays(CarbonImmutable::parse('2027-02-15'), 1));
    }

    public function test_calculate_additional_cost_daily_multiplies_days_by_daily_price(): void
    {
        $this->assertSame(40_000.0, RentalExtension::calculateAdditionalCost(RentalType::Daily, self::DAILY_PRICE, 4));
    }

    public function test_calculate_additional_cost_monthly_uses_monthly_price_per_month(): void
    {
        $cost = RentalExtension::calculateAdditionalCost(RentalType::Monthly, self::DAILY_PRICE, 62, 2);

        $this->assertSame(500_000.0, $cost);
    }

    public function test_draft_for_daily_extension_counts_days_and_cost_from_new_end_date(): void
    {
        $rental = Rental::factory()->for($this->garageAt(10_000))->daily(5)->startingOn('2026-11-21')->make();

        $extension = RentalExtension::draftFor($rental, RentalType::Daily, CarbonImmutable::parse('2026-11-30'), null);

        $this->assertSame(5, $extension->days);
        $this->assertSame('2026-11-25', $extension->old_end_date->toDateString());
        $this->assertSame('2026-11-30', $extension->new_end_date->toDateString());
        $this->assertSame('2026-11-26', $extension->firstExtraDay()->toDateString());
        $this->assertEquals(50_000, $extension->additional_cost);
        $this->assertNull($extension->months);
    }

    public function test_draft_for_monthly_extension_uses_end_month_length_for_every_month(): void
    {
        // Ends 20 Jan 2027: 2 months = 31 + 31 days -> 23 Mar 2027
        $rental = Rental::factory()->for($this->garageAt(10_000))->daily(20)->startingOn('2027-01-01')->make();

        $extension = RentalExtension::draftFor($rental, RentalType::Monthly, null, 2);

        $this->assertSame(62, $extension->days);
        $this->assertSame('2027-03-23', $extension->new_end_date->toDateString());
        $this->assertEquals(500_000, $extension->additional_cost);
    }

    public function test_draft_for_daily_extension_without_new_end_date_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RentalExtension::draftFor(Rental::factory()->make(), RentalType::Daily, null, null);
    }

    public function test_draft_for_monthly_extension_with_zero_months_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RentalExtension::draftFor(Rental::factory()->make(), RentalType::Monthly, null, 0);
    }

    public function test_created_between_covers_whole_end_day(): void
    {
        RentalExtension::factory()->create(['created_at' => '2026-11-30 23:59:00']);
        RentalExtension::factory()->create(['created_at' => '2026-12-01 00:00:00']);

        $count = RentalExtension::createdBetween(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'))->count();

        $this->assertSame(1, $count);
    }

    public function test_rental_relation_returns_extended_rental(): void
    {
        $rental = Rental::factory()->create();
        $extension = RentalExtension::factory()->for($rental)->create();

        $this->assertTrue($extension->rental->is($rental));
        $this->assertTrue($rental->extensions->first()->is($extension));
    }

    /**
     * A car garage with the given daily price; rentals use their garage's price.
     */
    private function garageAt(float $dailyPrice): Garage
    {
        return Garage::factory()->car()->create(['daily_price' => $dailyPrice]);
    }
}
