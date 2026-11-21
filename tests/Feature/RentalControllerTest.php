<?php

namespace Tests\Feature;

use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RentalControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-21';

    private User $tenant;

    private Vehicle $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::TODAY);
        Storage::fake(Rental::PAYMENT_PROOF_DISK);
        $this->tenant = User::factory()->create();
        $this->car = Vehicle::factory()->for($this->tenant)->create(['brand_model' => 'Toyota Avanza']);
    }

    // ---- Step 1: create -------------------------------------------------------------------

    public function test_create_lists_only_the_tenants_own_vehicles(): void
    {
        Vehicle::factory()->create(['brand_model' => 'Other Tenant Car']);

        $response = $this->actingAs($this->tenant)->get(route('rentals.create'));

        $response->assertOk();
        $response->assertSee('Toyota Avanza');
        $response->assertDontSee('Other Tenant Car');
    }

    public function test_create_offers_daily_and_monthly_toggle_with_month_limit(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('rentals.create'));

        $response->assertSee('Harian');
        $response->assertSee('Bulanan');
        $response->assertSee('Maksimal 29 hari.');
    }

    public function test_create_lists_vehicles_in_a_dropdown_with_a_new_vehicle_option(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('rentals.create'));

        $this->assertMatchesRegularExpression('/<select\s+id="vehicle_id"\s+name="vehicle_id"/', $response->getContent());
        $response->assertSee('+ Kendaraan baru');
    }

    public function test_create_refilled_daily_shows_the_last_rented_day(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('rentals.create', ['start_date' => self::TODAY, 'days' => 5]));

        $this->assertSame('2026-11-25', $this->shownEndDate($response->getContent()));
    }

    public function test_create_refilled_monthly_shows_end_date_from_the_start_month(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('rentals.create', [
            'start_date' => '2027-01-10',
            'rental_type' => RentalType::Monthly->value,
        ]));

        $this->assertSame('2027-02-09', $this->shownEndDate($response->getContent()));
        $this->assertMatchesRegularExpression('/name="days"\s+type="number"\s+value="31"/', $response->getContent());
    }

    public function test_create_with_days_over_the_limit_leaves_end_date_empty(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('rentals.create', ['start_date' => self::TODAY, 'days' => 30]));

        $this->assertSame('', $this->shownEndDate($response->getContent()));
    }

    public function test_create_renders_new_vehicle_fields_hidden_when_tenant_has_a_vehicle(): void
    {
        $html = $this->actingAs($this->tenant)->get(route('rentals.create'))->getContent();

        $this->assertMatchesRegularExpression('/data-show-when="vehicle_id:new"\s+hidden/', $html);
    }

    public function test_create_renders_new_vehicle_fields_visible_when_tenant_has_no_vehicle(): void
    {
        $newTenant = User::factory()->create();

        $html = $this->actingAs($newTenant)->get(route('rentals.create'))->getContent();

        $this->assertMatchesRegularExpression('/data-show-when="vehicle_id:new"\s+class=/', $html);
    }

    public function test_create_is_forbidden_for_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('rentals.create'))->assertForbidden();
    }

    // ---- Step 2: preview ------------------------------------------------------------------

    public function test_preview_daily_shows_end_date_and_free_garages_of_matching_type(): void
    {
        // Arrange
        Garage::factory()->car()->create(['code' => 'C001', 'daily_price' => 10_000]);
        Garage::factory()->bike()->create(['code' => 'S001']);

        // Act
        $response = $this->actingAs($this->tenant)->get(route('rentals.preview', $this->details(['days' => 5])));

        // Assert
        $response->assertOk();
        $response->assertSee('25 Nov 2026');
        $response->assertSee('C001');
        $response->assertDontSee('S001');
        $response->assertSee('Rp 50.000');
    }

    public function test_preview_monthly_lasts_the_days_of_the_start_month(): void
    {
        Garage::factory()->car()->create(['daily_price' => 10_000]);

        $response = $this->actingAs($this->tenant)->get(route('rentals.preview', $this->details([
            'rental_type' => RentalType::Monthly->value,
            'days' => 99,
        ])));

        $response->assertSessionHasNoErrors();
        $response->assertSee('20 Des 2026');
        $response->assertSee('30 hari');
        $response->assertSee('Rp 250.000');
    }

    public function test_preview_daily_at_month_limit_succeeds(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('rentals.preview', $this->details(['days' => 29])));

        $response->assertOk();
        $response->assertSessionHasNoErrors();
    }

    public function test_preview_daily_over_month_limit_returns_validation_error(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('rentals.preview', $this->details(['days' => 30])));

        $response->assertSessionHasErrors('days');
    }

    public function test_preview_daily_limit_follows_february_of_the_start_date(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->details(['start_date' => '2027-02-01', 'days' => 27])))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->details(['start_date' => '2027-02-01', 'days' => 28])))
            ->assertSessionHasErrors('days');
    }

    public function test_preview_with_zero_days_returns_validation_error(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->details(['days' => 0])))
            ->assertSessionHasErrors('days');
    }

    public function test_preview_with_start_date_in_the_past_returns_validation_error(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->details(['start_date' => '2026-11-20'])))
            ->assertSessionHasErrors('start_date');
    }

    public function test_preview_with_future_start_date_succeeds(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->details(['start_date' => '2026-12-15'])))
            ->assertOk()
            ->assertSee('15 Des 2026');
    }

    public function test_preview_hides_garage_rented_in_an_overlapping_period(): void
    {
        $taken = Garage::factory()->car()->create(['code' => 'C001']);
        Garage::factory()->car()->create(['code' => 'C002']);
        Rental::factory()->for($taken)->daily(2)->startingOn('2026-11-25')->create();

        $offers = $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->details(['days' => 5])))
            ->viewData('offers');

        $this->assertSame(['C002'], $offers[GarageType::Car->value]->map(fn ($draft) => $draft->garage->code)->all());
    }

    public function test_preview_lists_garage_whose_rental_ended_the_day_before(): void
    {
        $garage = Garage::factory()->car()->create(['code' => 'C001']);
        Rental::factory()->for($garage)->daily(1)->startingOn('2026-11-20')->create();

        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->details()))
            ->assertSee('C001');
    }

    public function test_preview_for_vehicle_already_parked_in_period_returns_validation_error(): void
    {
        Rental::factory()->for($this->car, 'vehicle')->daily(3)->startingOn(self::TODAY)->create();

        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->details()))
            ->assertSessionHasErrors('vehicle_id');
    }

    public function test_preview_with_another_tenants_vehicle_returns_validation_error(): void
    {
        $foreignCar = Vehicle::factory()->create();

        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->details(['vehicle_id' => $foreignCar->id])))
            ->assertSessionHasErrors('vehicle_id');
    }

    public function test_preview_for_electric_car_offers_car_and_electric_garages_with_charging(): void
    {
        // Arrange: 60 kWh capacity, 20 kWh left, electric garage charges 2.500 per kWh
        $electricCar = Vehicle::factory()->electricCar(60)->for($this->tenant)->create();
        Garage::factory()->car()->create(['code' => 'C001', 'daily_price' => 10_000]);
        Garage::factory()->electric()->create(['code' => 'E001', 'daily_price' => 10_000, 'kwh_price' => 2_500]);

        // Act
        $offers = $this->actingAs($this->tenant)->get(route('rentals.preview', $this->details([
            'vehicle_id' => $electricCar->id,
            'current_battery_kwh' => 20,
        ])))->viewData('offers');

        // Assert
        $this->assertEquals(30_000, $offers[GarageType::Car->value]->first()->total_cost);
        $this->assertEquals(130_000, $offers[GarageType::Electric->value]->first()->total_cost);
    }

    public function test_preview_for_electric_car_without_current_battery_returns_validation_error(): void
    {
        $electricCar = Vehicle::factory()->electricCar(60)->for($this->tenant)->create();

        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->details(['vehicle_id' => $electricCar->id])))
            ->assertSessionHasErrors('current_battery_kwh');
    }

    public function test_preview_with_current_battery_above_capacity_returns_validation_error(): void
    {
        $electricCar = Vehicle::factory()->electricCar(60)->for($this->tenant)->create();

        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->details(['vehicle_id' => $electricCar->id, 'current_battery_kwh' => 60.01])))
            ->assertSessionHasErrors('current_battery_kwh');
    }

    public function test_preview_new_bicycle_without_plate_succeeds(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->newVehicleDetails(VehicleType::Bicycle, ['plate_number' => ''])))
            ->assertSessionHasNoErrors();
    }

    public function test_preview_new_motorcycle_without_plate_returns_validation_error(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->newVehicleDetails(VehicleType::Motorcycle, ['plate_number' => ''])))
            ->assertSessionHasErrors('plate_number');
    }

    public function test_preview_new_vehicle_with_registered_plate_returns_validation_error(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->newVehicleDetails(VehicleType::Car, ['plate_number' => strtolower($this->car->plate_number)])))
            ->assertSessionHasErrors('plate_number');
    }

    public function test_preview_new_electric_car_without_capacity_returns_validation_error(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->newVehicleDetails(VehicleType::ElectricCar, ['current_battery_kwh' => 10])))
            ->assertSessionHasErrors('battery_capacity_kwh');
    }

    public function test_preview_new_electric_car_with_battery_above_capacity_returns_validation_error(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('rentals.preview', $this->newVehicleDetails(VehicleType::ElectricCar, [
                'battery_capacity_kwh' => 50,
                'current_battery_kwh' => 51,
            ])))
            ->assertSessionHasErrors('current_battery_kwh');
    }

    // ---- Step 2 submit: store -------------------------------------------------------------

    public function test_store_saves_rental_with_costs_and_proof_and_marks_garage_used_today(): void
    {
        // Arrange
        $garage = Garage::factory()->car()->create(['code' => 'C001', 'daily_price' => 10_000]);

        // Act
        $response = $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($garage, ['days' => 5]));

        // Assert
        $response->assertRedirect(route('rentals.index'));
        $response->assertSessionHas('success', 'Sewa garasi C001 berhasil disimpan.');
        $rental = Rental::sole();
        $this->assertTrue($rental->vehicle->user->is($this->tenant));
        $this->assertSame('2026-11-25', $rental->end_date->toDateString());
        $this->assertEquals(50_000, $rental->total_cost);
        Storage::disk(Rental::PAYMENT_PROOF_DISK)->assertExists($rental->payment_proof);
        $this->assertTrue(Garage::withUsageOn(CarbonImmutable::today())->findOrFail($garage->id)->is_used);
    }

    public function test_store_with_future_start_does_not_mark_garage_used_today(): void
    {
        $garage = Garage::factory()->car()->create();

        $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($garage, ['start_date' => '2026-12-01']));

        $this->assertDatabaseCount('rentals', 1);
        $this->assertFalse(Garage::withUsageOn(CarbonImmutable::today())->findOrFail($garage->id)->is_used);
    }

    public function test_store_monthly_saves_no_days_and_25_daily_prices(): void
    {
        $garage = Garage::factory()->car()->create(['daily_price' => 10_000]);

        $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($garage, [
            'rental_type' => RentalType::Monthly->value,
            'days' => 30,
        ]));

        $rental = Rental::sole();
        $this->assertNull($rental->days);
        $this->assertSame('2026-12-20', $rental->end_date->toDateString());
        $this->assertEquals(250_000, $rental->rental_cost);
    }

    public function test_store_with_new_vehicle_registers_it_for_the_tenant(): void
    {
        $garage = Garage::factory()->bike()->create();

        $this->actingAs($this->tenant)->post(route('rentals.store'), array_merge(
            $this->newVehicleDetails(VehicleType::Motorcycle, ['plate_number' => 'd 4321 abc']),
            $this->garageChoice($garage),
        ));

        $vehicle = Vehicle::firstWhere('plate_number', 'D 4321 ABC');
        $this->assertTrue($vehicle->user->is($this->tenant));
        $this->assertTrue(Rental::sole()->vehicle->is($vehicle));
    }

    public function test_store_electric_car_in_electric_garage_charges_to_full(): void
    {
        $electricCar = Vehicle::factory()->electricCar(60)->for($this->tenant)->create();
        $garage = Garage::factory()->electric()->create(['daily_price' => 10_000, 'kwh_price' => 2_500]);

        $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($garage, [
            'vehicle_id' => $electricCar->id,
            'current_battery_kwh' => 20,
        ]));

        $rental = Rental::sole();
        $this->assertEquals(40, $rental->charging_kwh);
        $this->assertEquals(100_000, $rental->charging_cost);
        $this->assertEquals(130_000, $rental->total_cost);
    }

    public function test_store_electric_car_in_car_garage_keeps_battery_but_charges_nothing(): void
    {
        $electricCar = Vehicle::factory()->electricCar(60)->for($this->tenant)->create();
        $garage = Garage::factory()->car()->create();

        $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($garage, [
            'vehicle_id' => $electricCar->id,
            'current_battery_kwh' => 20,
        ]));

        $rental = Rental::sole();
        $this->assertEquals(20, $rental->current_battery_kwh);
        $this->assertEquals(0, $rental->charging_cost);
    }

    public function test_store_car_in_bike_garage_returns_validation_error(): void
    {
        $bikeGarage = Garage::factory()->bike()->create();

        $response = $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($bikeGarage));

        $response->assertSessionHasErrors('garage_type');
        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_store_with_garage_of_another_type_than_chosen_returns_validation_error(): void
    {
        $electricCar = Vehicle::factory()->electricCar(60)->for($this->tenant)->create();
        $electricGarage = Garage::factory()->electric()->create();

        $response = $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($electricGarage, [
            'vehicle_id' => $electricCar->id,
            'current_battery_kwh' => 20,
            'garage_type' => GarageType::Car->value,
        ]));

        $response->assertSessionHasErrors('garage_id');
        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_store_garage_taken_in_overlapping_period_returns_validation_error(): void
    {
        $garage = Garage::factory()->car()->create();
        Rental::factory()->for($garage)->daily(2)->startingOn('2026-11-23')->create();

        $response = $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($garage, ['days' => 3]));

        $response->assertSessionHasErrors('garage_id');
        $this->assertDatabaseCount('rentals', 1);
    }

    public function test_store_same_vehicle_in_second_garage_at_same_time_returns_validation_error(): void
    {
        $first = Garage::factory()->car()->create();
        $second = Garage::factory()->car()->create();
        $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($first));

        $response = $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($second));

        $response->assertSessionHasErrors('vehicle_id');
        $this->assertDatabaseCount('rentals', 1);
    }

    public function test_store_tenant_can_rent_several_garages_at_once_with_different_vehicles(): void
    {
        $secondCar = Vehicle::factory()->for($this->tenant)->create();
        $first = Garage::factory()->car()->create();
        $second = Garage::factory()->car()->create();

        $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($first));
        $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($second, ['vehicle_id' => $secondCar->id]));

        $this->assertSame(2, $this->tenant->rentals()->activeOn(CarbonImmutable::today())->count());
    }

    public function test_store_failing_on_step_one_returns_to_preview_which_returns_to_step_one(): void
    {
        // Arrange: the car gets booked elsewhere between preview and save
        $garage = Garage::factory()->car()->create();
        $previewUrl = route('rentals.preview', $this->details());
        Rental::factory()->for($this->car, 'vehicle')->startingOn(self::TODAY)->create();

        // Act
        $storeResponse = $this->actingAs($this->tenant)->from($previewUrl)->post(route('rentals.store'), $this->storeData($garage));
        $previewResponse = $this->actingAs($this->tenant)->from($previewUrl)->get($previewUrl);

        // Assert: no redirect loop on the preview page
        $storeResponse->assertRedirect($previewUrl);
        $previewResponse->assertRedirect(route('rentals.create'));
        $previewResponse->assertSessionHasErrors('vehicle_id');
    }

    public function test_store_without_payment_proof_returns_validation_error(): void
    {
        $garage = Garage::factory()->car()->create();

        $response = $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($garage, ['payment_proof' => null]));

        $response->assertSessionHasErrors('payment_proof');
        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_store_with_non_image_payment_proof_returns_validation_error(): void
    {
        $garage = Garage::factory()->car()->create();
        $pdf = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($garage, ['payment_proof' => $pdf]));

        $response->assertSessionHasErrors('payment_proof');
    }

    public function test_store_with_payment_proof_over_max_size_returns_validation_error(): void
    {
        $garage = Garage::factory()->car()->create();
        $tooLarge = UploadedFile::fake()->image('bukti.jpg')->size(Rental::PAYMENT_PROOF_MAX_KB + 1);

        $response = $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($garage, ['payment_proof' => $tooLarge]));

        $response->assertSessionHasErrors('payment_proof');
    }

    public function test_store_is_forbidden_for_admin(): void
    {
        $garage = Garage::factory()->car()->create();

        $response = $this->actingAs(User::factory()->admin()->create())->post(route('rentals.store'), $this->storeData($garage));

        $response->assertForbidden();
        $this->assertDatabaseCount('rentals', 0);
    }

    // ---- My rentals: index ----------------------------------------------------------------

    public function test_index_shows_only_own_rentals_with_their_status(): void
    {
        // Arrange
        $active = Garage::factory()->car()->create(['code' => 'C001']);
        $upcoming = Garage::factory()->car()->create(['code' => 'C002']);
        $foreign = Garage::factory()->car()->create(['code' => 'C003']);
        Rental::factory()->for($this->car, 'vehicle')->for($active)->startingOn(self::TODAY)->create();
        $otherCar = Vehicle::factory()->for($this->tenant)->create();
        Rental::factory()->for($otherCar, 'vehicle')->for($upcoming)->startingOn('2026-12-05')->create();
        Rental::factory()->for($foreign)->create();

        // Act
        $response = $this->actingAs($this->tenant)->get(route('rentals.index'));

        // Assert
        $response->assertOk();
        $response->assertSee('C001');
        $response->assertSee('C002');
        $response->assertDontSee('C003');
        $response->assertSee('Aktif');
        $response->assertSee('Akan Datang');
    }

    public function test_index_without_rentals_shows_empty_state(): void
    {
        $this->actingAs($this->tenant)->get(route('rentals.index'))->assertSee('Anda belum pernah menyewa garasi.');
    }

    // ---- Helpers --------------------------------------------------------------------------

    /**
     * Value of the read-only end date shown on step 1.
     */
    private function shownEndDate(string $html): string
    {
        preg_match('/name="end_date_display"\s+type="date"\s+value="([^"]*)"/', $html, $match);

        return $match[1] ?? 'not rendered';
    }

    /**
     * Step 1 input for the tenant's petrol car: daily, 3 days, from today.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function details(array $overrides = []): array
    {
        return array_merge([
            'vehicle_id' => $this->car->id,
            'rental_type' => RentalType::Daily->value,
            'start_date' => self::TODAY,
            'days' => 3,
        ], $overrides);
    }

    /**
     * Step 1 input for a vehicle that is registered with this rental.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function newVehicleDetails(VehicleType $type, array $overrides = []): array
    {
        return $this->details(array_merge([
            'vehicle_id' => RentalService::NEW_VEHICLE,
            'vehicle_type' => $type->value,
            'plate_number' => 'B 9999 NEW',
            'brand_model' => 'Honda Beat',
        ], $overrides));
    }

    /**
     * Step 2 input: garage choice and a valid payment proof image.
     *
     * @return array<string, mixed>
     */
    private function garageChoice(Garage $garage): array
    {
        return [
            'garage_type' => $garage->type->value,
            'garage_id' => $garage->id,
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ];
    }

    /**
     * Full store request for the tenant's petrol car in the given garage.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeData(Garage $garage, array $overrides = []): array
    {
        return array_merge($this->details(), $this->garageChoice($garage), $overrides);
    }
}
