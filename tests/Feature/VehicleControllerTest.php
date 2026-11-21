<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-21';

    private User $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::TODAY);
        $this->tenant = User::factory()->create();
    }

    public function test_index_lists_only_the_tenants_own_vehicles(): void
    {
        Vehicle::factory()->for($this->tenant)->create(['brand_model' => 'Toyota Avanza', 'plate_number' => 'B 1234 XYZ']);
        Vehicle::factory()->create(['brand_model' => 'Other Tenant Car']);

        $response = $this->actingAs($this->tenant)->get(route('vehicles.index'));

        $response->assertOk();
        $response->assertSee('Toyota Avanza');
        $response->assertSee('B 1234 XYZ');
        $response->assertDontSee('Other Tenant Car');
    }

    public function test_index_shows_the_garage_a_vehicle_is_in_today(): void
    {
        $car = Vehicle::factory()->for($this->tenant)->create();
        Rental::factory()->for($car, 'vehicle')->for($this->tenant)
            ->for(Garage::factory()->car()->create(['code' => 'C007']))
            ->daily(5)->startingOn(self::TODAY)->create();

        $response = $this->actingAs($this->tenant)->get(route('vehicles.index'));

        $response->assertSeeTextInOrder(['Garasi C007', 's.d. 25 Nov 2026']);
    }

    public function test_index_does_not_count_a_future_booking_as_parked_today(): void
    {
        $car = Vehicle::factory()->for($this->tenant)->create();
        Rental::factory()->for($car, 'vehicle')->for($this->tenant)->startingOn('2026-12-01')->create();

        $response = $this->actingAs($this->tenant)->get(route('vehicles.index'));

        $response->assertSee('Tidak di garasi');
        $response->assertDontSee('s.d.');
    }

    public function test_index_shows_battery_only_for_electric_cars_and_no_plate_for_bicycles(): void
    {
        Vehicle::factory()->electricCar(60)->for($this->tenant)->create(['brand_model' => 'Ioniq 5']);
        Vehicle::factory()->bicycle()->for($this->tenant)->create(['brand_model' => 'Polygon']);

        $vehicles = $this->actingAs($this->tenant)->get(route('vehicles.index'))->assertSee('60 kWh')->viewData('vehicles');

        $this->assertNull($vehicles->firstWhere('brand_model', 'Polygon')->plate_number);
    }

    public function test_index_has_no_rental_count_or_action_column(): void
    {
        Vehicle::factory()->for($this->tenant)->create();

        $response = $this->actingAs($this->tenant)->get(route('vehicles.index'));

        $response->assertDontSee('Jumlah Sewa');
        $response->assertDontSee('Aksi');
    }

    public function test_index_without_vehicles_explains_how_to_add_one(): void
    {
        $this->actingAs($this->tenant)->get(route('vehicles.index'))
            ->assertSee('Belum ada kendaraan. Kendaraan didaftarkan saat menyewa garasi.');
    }

    public function test_index_is_forbidden_for_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('vehicles.index'))->assertForbidden();
    }

    public function test_tenant_menu_shows_vehicles_instead_of_new_rental(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('vehicles.index'));

        $response->assertSee('Kendaraan Saya');
        $response->assertDontSee('>Sewa Baru<', false);
    }

    public function test_rental_form_opened_from_a_vehicle_preselects_it(): void
    {
        Vehicle::factory()->for($this->tenant)->create(['brand_model' => 'Avanza']);
        $second = Vehicle::factory()->for($this->tenant)->create(['brand_model' => 'Brio']);

        $html = $this->actingAs($this->tenant)->get(route('rentals.create', ['vehicle_id' => $second->id]))->getContent();

        $this->assertMatchesRegularExpression('/<option value="'.$second->id.'" selected>/', $html);
    }
}
