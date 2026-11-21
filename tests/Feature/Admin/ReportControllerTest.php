<?php

namespace Tests\Feature\Admin;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-21';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::TODAY);
        $this->admin = User::factory()->admin()->create();
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
        $rows = $this->report('admin.reports.monthly', ['year' => 2026])->viewData('rows');

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

        $rows = $this->report('admin.reports.monthly', ['year' => 2026])->viewData('rows');

        $this->assertEquals(30_000, $rows[10]['total_revenue']);
        $this->assertSame(1, $rows[11]['extensions']);
        $this->assertEquals(20_000, $rows[11]['extension_revenue']);
        $this->assertEquals(20_000, $rows[11]['total_revenue']);
    }

    public function test_monthly_revenue_includes_charging(): void
    {
        $this->rental('2026-05-10', ['charging_kwh' => 40, 'charging_cost' => 100_000, 'total_cost' => 130_000]);

        $rows = $this->report('admin.reports.monthly', ['year' => 2026])->viewData('rows');

        $this->assertEquals(130_000, $rows[4]['rental_revenue']);
    }

    public function test_monthly_totals_add_up_every_month(): void
    {
        $this->rental('2026-01-10');
        $this->rental('2026-08-10');

        $totals = $this->report('admin.reports.monthly', ['year' => 2026])->viewData('totals');

        $this->assertSame(2, $totals['rentals']);
        $this->assertEquals(60_000, $totals['total_revenue']);
    }

    public function test_monthly_without_year_defaults_to_current_year(): void
    {
        $response = $this->report('admin.reports.monthly');

        $response->assertOk();
        $response->assertSee('Tahun 2026');
        $response->assertSee('Desember');
    }

    public function test_monthly_with_invalid_year_returns_to_report_with_error(): void
    {
        $response = $this->report('admin.reports.monthly', ['year' => 1999]);

        $response->assertRedirect(route('admin.reports.monthly'));
        $response->assertSessionHasErrors('year');
    }

    public function test_reports_are_forbidden_for_tenant(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.reports.monthly'))->assertForbidden();
    }

    // ---- Per day --------------------------------------------------------------------------

    public function test_daily_has_one_row_per_day_of_the_month(): void
    {
        $this->assertCount(28, $this->report('admin.reports.daily', ['year' => 2027, 'month' => 2])->viewData('rows'));
        $this->assertCount(29, $this->report('admin.reports.daily', ['year' => 2028, 'month' => 2])->viewData('rows'));
    }

    public function test_daily_counts_rentals_on_their_start_day(): void
    {
        $this->rental('2026-11-21');
        $this->rental('2026-11-21');
        $this->rental('2026-11-30');
        $this->rental('2026-12-01');

        $rows = $this->report('admin.reports.daily', ['year' => 2026, 'month' => 11])->viewData('rows');

        $this->assertCount(30, $rows);
        $this->assertSame(2, $rows[20]['rentals']);
        $this->assertSame(1, $rows[29]['rentals']);
        $this->assertSame('21 Nov 2026', $rows[20]['label']);
    }

    public function test_daily_with_month_thirteen_returns_validation_error(): void
    {
        $this->report('admin.reports.daily', ['year' => 2026, 'month' => 13])
            ->assertSessionHasErrors('month');
    }

    // ---- Per garage type ------------------------------------------------------------------

    public function test_garage_types_groups_rentals_and_extensions_by_garage_type(): void
    {
        // Arrange
        $this->rental('2026-11-05', [], Garage::factory()->bike()->create(['daily_price' => 10_000]));
        $carRental = $this->rental('2026-11-06', [], Garage::factory()->car()->create(['daily_price' => 10_000]));
        RentalExtension::factory()->for($carRental)->create(['additional_cost' => 50_000, 'created_at' => '2026-11-10 09:00:00']);

        // Act
        $rows = collect($this->report('admin.reports.garage-types', ['from' => '2026-11-01', 'to' => '2026-11-30'])->viewData('rows'))
            ->keyBy('label');

        // Assert
        $this->assertSame(1, $rows[GarageType::Bike->label()]['rentals']);
        $this->assertEquals(80_000, $rows[GarageType::Car->label()]['total_revenue']);
        $this->assertSame(0, $rows[GarageType::Electric->label()]['rentals']);
    }

    public function test_garage_types_period_includes_both_end_days(): void
    {
        $this->rental('2026-11-01');
        $this->rental('2026-11-30');
        $this->rental('2026-12-01');

        $totals = $this->report('admin.reports.garage-types', ['from' => '2026-11-01', 'to' => '2026-11-30'])->viewData('totals');

        $this->assertSame(2, $totals['rentals']);
    }

    public function test_garage_types_without_period_defaults_to_current_month(): void
    {
        $this->report('admin.reports.garage-types')->assertSee('01 Nov 2026 – 30 Nov 2026');
    }

    public function test_period_ending_before_it_starts_returns_validation_error(): void
    {
        $response = $this->report('admin.reports.garage-types', ['from' => '2026-11-30', 'to' => '2026-11-01']);

        $response->assertRedirect(route('admin.reports.garage-types'));
        $response->assertSessionHasErrors('to');
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
        $rows = $this->report('admin.reports.tenants', ['from' => '2026-11-01', 'to' => '2026-11-30'])->viewData('rows');

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

        $rows = $this->report('admin.reports.tenants', ['from' => '2026-11-01', 'to' => '2026-11-30'])->viewData('rows');

        $this->assertCount(1, $rows);
        $this->assertSame(0, $rows[0]['rentals']);
        $this->assertEquals(20_000, $rows[0]['extension_revenue']);
    }

    public function test_tenants_without_activity_shows_empty_state(): void
    {
        $this->report('admin.reports.tenants')
            ->assertSee('Belum ada penyewa yang menyewa atau memperpanjang pada periode ini.');
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
            ->for($tenant)
            ->for(Vehicle::factory()->for($tenant))
            ->daily(3)
            ->startingOn($startDate)
            ->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function report(string $routeName, array $query = []): TestResponse
    {
        return $this->actingAs($this->admin)->get(route($routeName, $query));
    }
}
