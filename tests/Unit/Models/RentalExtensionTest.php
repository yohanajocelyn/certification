<?php

namespace Tests\Unit\Models;

use App\Enums\RentalType;
use App\Models\Rental;
use App\Models\RentalExtension;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_rental_relation_returns_extended_rental(): void
    {
        $rental = Rental::factory()->create();
        $extension = RentalExtension::factory()->for($rental)->create();

        $this->assertTrue($extension->rental->is($rental));
        $this->assertTrue($rental->extensions->first()->is($extension));
    }
}
