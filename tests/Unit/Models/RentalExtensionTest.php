<?php

namespace Tests\Unit\Models;

use App\Enums\RentalType;
use App\Models\RentalExtension;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalExtensionTest extends TestCase
{
    use RefreshDatabase;

    private const DAILY_PRICE = 10_000.0;

    public function test_calculate_monthly_days_uses_current_end_month_for_every_month(): void
    {
        // Ends in January: 2 months = 31 + 31 days.
        $this->assertSame(62, RentalExtension::calculateMonthlyDays(CarbonImmutable::parse('2027-01-20'), 2));
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
}
