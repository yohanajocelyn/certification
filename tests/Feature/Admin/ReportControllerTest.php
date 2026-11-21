<?php

namespace Tests\Feature\Admin;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * HTTP side of the reports: filters, defaults, access and rendering. The counting itself is
 * covered by Tests\Unit\Services\ReportServiceTest.
 */
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

    public function test_monthly_without_year_defaults_to_current_year(): void
    {
        $response = $this->report('admin.reports.monthly');

        $response->assertOk();
        $this->assertSame(2026, $response->viewData('filters')['year']);
        $this->assertMatchesRegularExpression('/name="year"\s+type="number"\s+value="2026"/', $response->getContent());
    }

    public function test_monthly_page_shows_a_row_per_month_and_the_totals(): void
    {
        Rental::factory()->for(Garage::factory()->car()->create(['daily_price' => 10_000]))
            ->for(Vehicle::factory())->daily(3)->startingOn('2026-03-05')->create();

        $response = $this->report('admin.reports.monthly', ['year' => 2026]);

        $response->assertSeeTextInOrder(['Januari', 'Maret', 'Desember', 'Total']);
        $response->assertSee('Rp 30.000');
    }

    public function test_monthly_with_invalid_year_returns_to_report_with_error(): void
    {
        $response = $this->report('admin.reports.monthly', ['year' => 1999]);

        $response->assertRedirect(route('admin.reports.monthly'));
        $response->assertSessionHasErrors('year');
    }

    public function test_daily_page_shows_every_day_of_the_month(): void
    {
        $response = $this->report('admin.reports.daily', ['year' => 2027, 'month' => 2]);

        $response->assertOk();
        $this->assertCount(28, $response->viewData('rows'));
        $response->assertSee('28 Feb 2027');
    }

    public function test_daily_with_month_thirteen_returns_validation_error(): void
    {
        $this->report('admin.reports.daily', ['year' => 2026, 'month' => 13])
            ->assertSessionHasErrors('month');
    }

    public function test_garage_types_without_period_defaults_to_current_month(): void
    {
        $html = $this->report('admin.reports.garage-types')->getContent();

        $this->assertMatchesRegularExpression('/name="from"\s+type="date"\s+value="2026-11-01"/', $html);
        $this->assertMatchesRegularExpression('/name="to"\s+type="date"\s+value="2026-11-30"/', $html);
    }

    public function test_period_ending_before_it_starts_returns_validation_error(): void
    {
        $response = $this->report('admin.reports.garage-types', ['from' => '2026-11-30', 'to' => '2026-11-01']);

        $response->assertRedirect(route('admin.reports.garage-types'));
        $response->assertSessionHasErrors('to');
    }

    public function test_tenants_page_lists_tenants_with_activity(): void
    {
        $tenant = User::factory()->create(['name' => 'Budi Santoso']);
        Rental::factory()->for(Vehicle::factory()->for($tenant))->startingOn('2026-11-05')->create();

        $this->report('admin.reports.tenants')->assertSee('Budi Santoso');
    }

    public function test_tenants_without_activity_shows_empty_state(): void
    {
        $this->report('admin.reports.tenants')
            ->assertSee('Belum ada penyewa yang menyewa atau memperpanjang pada periode ini.');
    }

    public function test_reports_are_forbidden_for_tenant(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.reports.monthly'))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function report(string $routeName, array $query = []): TestResponse
    {
        return $this->actingAs($this->admin)->get(route($routeName, $query));
    }
}
