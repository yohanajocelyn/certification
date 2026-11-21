<?php

namespace Tests\Unit\Services;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-21');
        $this->service = app(ReportService::class);
    }

    // ---- Per month ------------------------------------------------------------------------

    public function test_monthly_counts_rentals_in_the_month_they_start(): void
    {
        // Arrange: 3 days x 10.000 = 30.000 each
        $this->rental('2026-03-05');
        $this->rental('2026-03-28');
        $this->rental('2026-07-01');
        $this->rental('2025-03-10');

        // Act
        $rows = $this->service->monthly(2026)->rows;

        // Assert: index 2 = March, index 6 = July; 2025 left out
        $this->assertCount(12, $rows);
        $this->assertSame(2, $rows[2]['rentals']);
        $this->assertEquals(60_000, $rows[2]['rental_revenue']);
        $this->assertSame(1, $rows[6]['rentals']);
        $this->assertSame(0, $rows[0]['rentals']);
    }

    public function test_monthly_counts_extension_revenue_in_the_month_it_was_made(): void
    {
        // Rental starts in November, extended (20.000) in December
        $rental = $this->rental('2026-11-28');
        RentalExtension::factory()->for($rental)->create(['days' => 2, 'additional_cost' => 20_000, 'created_at' => '2026-12-01 10:00:00']);

        $rows = $this->service->monthly(2026)->rows;

        $this->assertEquals(30_000, $rows[10]['total_revenue']);
        $this->assertSame(1, $rows[11]['extensions']);
        $this->assertEquals(20_000, $rows[11]['extension_revenue']);
        $this->assertEquals(20_000, $rows[11]['total_revenue']);
    }

    public function test_monthly_revenue_includes_charging(): void
    {
        $this->rental('2026-05-10', ['charging_kwh' => 40, 'charging_cost' => 100_000, 'total_cost' => 130_000]);

        $this->assertEquals(130_000, $this->service->monthly(2026)->rows[4]['rental_revenue']);
    }

    public function test_totals_add_up_every_row(): void
    {
        $this->rental('2026-01-10');
        $this->rental('2026-08-10');

        $totals = $this->service->monthly(2026)->totals;

        $this->assertSame('Total', $totals['label']);
        $this->assertSame(2, $totals['rentals']);
        $this->assertEquals(60_000, $totals['total_revenue']);
    }

    // ---- Per day --------------------------------------------------------------------------

    public function test_daily_has_one_row_per_day_of_the_month(): void
    {
        $this->assertCount(28, $this->service->daily(2027, 2)->rows);
        $this->assertCount(29, $this->service->daily(2028, 2)->rows);
        $this->assertCount(30, $this->service->daily(2026, 11)->rows);
    }

    public function test_daily_counts_rentals_on_their_start_day(): void
    {
        $this->rental('2026-11-21');
        $this->rental('2026-11-21');
        $this->rental('2026-11-30');
        $this->rental('2026-12-01');

        $rows = $this->service->daily(2026, 11)->rows;

        $this->assertSame(2, $rows[20]['rentals']);
        $this->assertSame(1, $rows[29]['rentals']);
        $this->assertSame('21 Nov 2026', $rows[20]['label']);
    }

    // ---- Per garage type ------------------------------------------------------------------

    public function test_garage_types_groups_rentals_and_extensions_by_garage_type(): void
    {
        // Arrange
        $this->rental('2026-11-05', [], Garage::factory()->bike()->create(['daily_price' => 10_000]));
        $carRental = $this->rental('2026-11-06', [], Garage::factory()->car()->create(['daily_price' => 10_000]));
        RentalExtension::factory()->for($carRental)->create(['additional_cost' => 50_000, 'created_at' => '2026-11-10 09:00:00']);

        // Act
        $rows = collect($this->service->garageTypes($this->date('2026-11-01'), $this->date('2026-11-30'))->rows)->keyBy('label');

        // Assert
        $this->assertSame(1, $rows[GarageType::Bike->label()]['rentals']);
        $this->assertEquals(80_000, $rows[GarageType::Car->label()]['total_revenue']);
        $this->assertSame(0, $rows[GarageType::Electric->label()]['rentals']);
    }

    public function test_period_includes_both_end_days(): void
    {
        $this->rental('2026-11-01');
        $this->rental('2026-11-30');
        $this->rental('2026-12-01');

        $totals = $this->service->garageTypes($this->date('2026-11-01'), $this->date('2026-11-30'))->totals;

        $this->assertSame(2, $totals['rentals']);
    }

    // ---- Per tenant -----------------------------------------------------------------------

    public function test_tenants_lists_each_tenant_once_with_highest_revenue_first(): void
    {
        // Arrange: Budi 1 rental (30.000), Sari 2 rentals (60.000)
        $budi = User::factory()->create(['name' => 'Budi']);
        $sari = User::factory()->create(['name' => 'Sari']);
        $this->rental('2026-11-02', [], null, $budi);
        $this->rental('2026-11-03', [], null, $sari);
        $this->rental('2026-11-04', [], null, $sari);

        // Act
        $rows = $this->service->tenants($this->date('2026-11-01'), $this->date('2026-11-30'))->rows;

        // Assert
        $this->assertCount(2, $rows);
        $this->assertStringStartsWith('Sari', $rows[0]['label']);
        $this->assertSame(2, $rows[0]['rentals']);
        $this->assertStringStartsWith('Budi', $rows[1]['label']);
    }

    public function test_tenants_includes_tenant_with_only_an_extension_in_the_period(): void
    {
        $tenant = User::factory()->create(['name' => 'Rina']);
        $rental = $this->rental('2026-10-28', [], null, $tenant);
        RentalExtension::factory()->for($rental)->create(['additional_cost' => 20_000, 'created_at' => '2026-11-02 08:00:00']);

        $rows = $this->service->tenants($this->date('2026-11-01'), $this->date('2026-11-30'))->rows;

        $this->assertCount(1, $rows);
        $this->assertSame(0, $rows[0]['rentals']);
        $this->assertEquals(20_000, $rows[0]['extension_revenue']);
    }

    public function test_tenants_without_activity_has_no_rows(): void
    {
        $this->assertSame([], $this->service->tenants($this->date('2026-11-01'), $this->date('2026-11-30'))->rows);
    }

    // ---- Helpers --------------------------------------------------------------------------

    /**
     * A 3-day daily rental (30.000) starting on the given date.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function rental(string $startDate, array $attributes = [], ?Garage $garage = null, ?User $tenant = null): Rental
    {
        $garage ??= Garage::factory()->car()->create(['daily_price' => 10_000]);
        $tenant ??= User::factory()->create();

        return Rental::factory()
            ->for($garage)
            ->for(Vehicle::factory()->for($tenant))
            ->daily(3)
            ->startingOn($startDate)
            ->create($attributes);
    }

    private function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }
}
