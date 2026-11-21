<?php

namespace Tests\Feature\Admin;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalControllerTest extends TestCase
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

    public function test_index_lists_only_rentals_running_today(): void
    {
        // Arrange
        Rental::factory()->for(Garage::factory()->create(['code' => 'C001']))->startingOn(self::TODAY)->create();
        Rental::factory()->for(Garage::factory()->create(['code' => 'C002']))->startingOn('2026-12-01')->create();
        Rental::factory()->for(Garage::factory()->create(['code' => 'C003']))->daily(2)->startingOn('2026-11-10')->create();

        // Act
        $response = $this->actingAs($this->admin)->get(route('admin.rentals.index'));

        // Assert
        $response->assertOk();
        $response->assertSee('C001');
        $response->assertDontSee('C002');
        $response->assertDontSee('C003');
    }

    public function test_index_shows_tenant_and_days_left(): void
    {
        $tenant = User::factory()->create(['name' => 'Budi Santoso']);
        Rental::factory()->for(Vehicle::factory()->for($tenant))
            ->daily(5)->startingOn(self::TODAY)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.rentals.index'));

        $response->assertSee('Budi Santoso');
        $response->assertSee('Sisa 5 hari');
    }

    public function test_index_without_active_rentals_shows_empty_state(): void
    {
        $this->actingAs($this->admin)->get(route('admin.rentals.index'))
            ->assertSee('Tidak ada garasi yang sedang disewa hari ini.');
    }

    public function test_index_is_forbidden_for_tenant(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.rentals.index'))->assertForbidden();
    }
}
