<?php

namespace Tests\Unit\Models;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalExtensionTest extends TestCase
{
    use RefreshDatabase;

    private const DAILY_PRICE = 10_000.0;

    public function test_calculate_whole_months_uses_current_end_month_for_every_month(): void
    {
        // Ends in January: every month counts 31 days, so 69 days = 2 months + 7 days.
        $this->assertSame(2, RentalExtension::calculateWholeMonths(CarbonImmutable::parse('2027-01-10'), 69));
    }

    public function test_calculate_whole_months_one_day_short_of_a_month_returns_zero(): void
    {
        $this->assertSame(0, RentalExtension::calculateWholeMonths(CarbonImmutable::parse('2027-01-10'), 30));
    }

    public function test_calculate_whole_months_exactly_one_month_returns_one(): void
    {
        $this->assertSame(1, RentalExtension::calculateWholeMonths(CarbonImmutable::parse('2027-01-10'), 31));
    }

    public function test_calculate_whole_months_ending_in_february_counts_28_days_per_month(): void
    {
        $this->assertSame(1, RentalExtension::calculateWholeMonths(CarbonImmutable::parse('2027-02-10'), 28));
    }

    public function test_calculate_additional_cost_adds_monthly_price_per_month_and_daily_price_per_leftover_day(): void
    {
        // 2 x (25 x 10.000) + 7 x 10.000
        $this->assertSame(570_000.0, RentalExtension::calculateAdditionalCost(self::DAILY_PRICE, 2, 7));
    }

    public function test_draft_for_splits_the_added_days_into_whole_months_and_leftover_days(): void
    {
        // Arrange: rental ends 10 Jan 2027
        $garage = Garage::factory()->car()->create(['daily_price' => self::DAILY_PRICE]);
        $rental = Rental::factory()->for($garage)->daily(5)->startingOn('2027-01-06')->create();

        // Act: extend to 20 Mar 2027 (11 Jan - 20 Mar = 69 days)
        $extension = RentalExtension::draftFor($rental, CarbonImmutable::parse('2027-03-20'));

        // Assert
        $this->assertSame(69, $extension->days);
        $this->assertSame(2, $extension->months);
        $this->assertSame(7, $extension->leftoverDays());
        $this->assertSame('2027-03-20', $extension->new_end_date->toDateString());
        $this->assertEquals(570_000, $extension->additional_cost);
    }
}
