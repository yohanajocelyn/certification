<?php

namespace Tests\Feature\Admin;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarageControllerTest extends TestCase
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

    public function test_index_lists_garages_for_admin(): void
    {
        Garage::factory()->car()->create(['code' => 'C001']);
        Garage::factory()->bike()->create(['code' => 'S001']);

        $response = $this->actingAs($this->admin)->get(route('admin.garages.index'));

        $response->assertOk();
        $response->assertSee('C001');
        $response->assertSee('S001');
    }

    public function test_index_filtered_by_type_shows_only_that_type(): void
    {
        Garage::factory()->car()->create(['code' => 'C001']);
        Garage::factory()->bike()->create(['code' => 'S001']);

        $response = $this->actingAs($this->admin)->get(route('admin.garages.index', ['type' => GarageType::Bike->value]));

        $response->assertSee('S001');
        $response->assertDontSee('C001');
    }

    public function test_index_with_unknown_type_filter_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.garages.index', ['type' => 'truck']));

        $response->assertSessionHasErrors('type');
    }

    public function test_index_marks_garage_used_only_while_a_rental_covers_today(): void
    {
        // Arrange: C001 rented from today, C002 booked for next month only
        $usedToday = Garage::factory()->car()->create(['code' => 'C001']);
        $bookedLater = Garage::factory()->car()->create(['code' => 'C002']);
        Rental::factory()->for($usedToday)->daily(3)->startingOn(self::TODAY)->create();
        Rental::factory()->for($bookedLater)->monthly()->startingOn('2026-12-01')->create();

        // Act
        $garages = $this->actingAs($this->admin)->get(route('admin.garages.index'))->viewData('garages');

        // Assert
        $this->assertTrue($garages->firstWhere('code', 'C001')->is_used);
        $this->assertFalse($garages->firstWhere('code', 'C002')->is_used);
    }

    public function test_index_with_no_garages_shows_empty_state(): void
    {
        $this->actingAs($this->admin)->get(route('admin.garages.index'))
            ->assertOk()
            ->assertSee('Belum ada garasi untuk ditampilkan.');
    }

    public function test_index_paginates_after_fifteen_garages(): void
    {
        Garage::factory()->count(16)->car()->create();

        $garages = $this->actingAs($this->admin)->get(route('admin.garages.index'))->viewData('garages');

        $this->assertCount(15, $garages);
        $this->assertSame(16, $garages->total());
    }

    public function test_index_is_forbidden_for_tenant(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.garages.index'))->assertForbidden();
    }

    public function test_index_redirects_guest_to_login(): void
    {
        $this->get(route('admin.garages.index'))->assertRedirect(route('login'));
    }

    public function test_create_shows_next_code_for_each_type(): void
    {
        Garage::factory()->car()->create(['code' => 'C010']);

        $response = $this->actingAs($this->admin)->get(route('admin.garages.create'));

        $response->assertOk();
        $response->assertSee('Mulai dari S001');
        $response->assertSee('Mulai dari C011');
        $response->assertSee('Mulai dari E001');
    }

    public function test_store_first_bike_batch_creates_codes_from_s001(): void
    {
        // Act
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData([
            'type' => GarageType::Bike->value,
            'quantity' => 3,
        ]));

        // Assert
        $response->assertRedirect(route('admin.garages.index', ['type' => GarageType::Bike->value]));
        $response->assertSessionHas('success', '3 Garasi Sepeda baru dibuat: S001 – S003.');
        $this->assertSame(['S001', 'S002', 'S003'], Garage::orderBy('code')->pluck('code')->all());
    }

    public function test_store_continues_numbering_after_existing_codes(): void
    {
        Garage::factory()->car()->create(['code' => 'C010']);

        $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData(['quantity' => 2]));

        $this->assertDatabaseHas('garages', ['code' => 'C011']);
        $this->assertDatabaseHas('garages', ['code' => 'C012']);
        $this->assertDatabaseCount('garages', 3);
    }

    public function test_store_saves_daily_price_for_every_garage_in_batch(): void
    {
        $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData([
            'quantity' => 2,
            'daily_price' => 30000,
        ]));

        $this->assertSame(2, Garage::where('daily_price', 30000)->count());
    }

    public function test_store_electric_batch_saves_kwh_price(): void
    {
        $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData([
            'type' => GarageType::Electric->value,
            'kwh_price' => 2500,
        ]));

        $this->assertDatabaseHas('garages', ['code' => 'E001', 'kwh_price' => 2500]);
    }

    public function test_store_electric_without_kwh_price_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData([
            'type' => GarageType::Electric->value,
        ]));

        $response->assertSessionHasErrors('kwh_price');
        $this->assertDatabaseCount('garages', 0);
    }

    public function test_store_car_with_kwh_price_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData([
            'kwh_price' => 2500,
        ]));

        $response->assertSessionHasErrors('kwh_price');
        $this->assertDatabaseCount('garages', 0);
    }

    public function test_store_quantity_at_max_batch_size_creates_all_garages(): void
    {
        $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData([
            'quantity' => Garage::MAX_BATCH_SIZE,
        ]));

        $this->assertDatabaseCount('garages', Garage::MAX_BATCH_SIZE);
        $this->assertDatabaseHas('garages', ['code' => 'C999']);
    }

    public function test_store_quantity_over_max_batch_size_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData([
            'quantity' => Garage::MAX_BATCH_SIZE + 1,
        ]));

        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('garages', 0);
    }

    public function test_store_quantity_zero_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData(['quantity' => 0]));

        $response->assertSessionHasErrors('quantity');
    }

    public function test_store_quantity_filling_the_last_codes_succeeds(): void
    {
        Garage::factory()->car()->create(['code' => 'C998']);

        $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData(['quantity' => 1]));

        $this->assertDatabaseHas('garages', ['code' => 'C999']);
    }

    public function test_store_quantity_past_code_999_returns_validation_error(): void
    {
        Garage::factory()->car()->create(['code' => 'C998']);

        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData(['quantity' => 2]));

        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('garages', 1);
    }

    public function test_store_with_zero_daily_price_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData(['daily_price' => 0]));

        $response->assertSessionHasErrors('daily_price');
    }

    public function test_store_with_daily_price_over_max_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData([
            'daily_price' => Garage::MAX_PRICE + 1,
        ]));

        $response->assertSessionHasErrors('daily_price');
    }

    public function test_store_with_unknown_type_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData(['type' => 'truck']));

        $response->assertSessionHasErrors('type');
        $this->assertDatabaseCount('garages', 0);
    }

    public function test_store_is_forbidden_for_tenant(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('admin.garages.store'), $this->validData());

        $response->assertForbidden();
        $this->assertDatabaseCount('garages', 0);
    }

    public function test_index_has_an_edit_button_for_each_garage(): void
    {
        $garage = Garage::factory()->car()->create(['code' => 'C001']);

        $response = $this->actingAs($this->admin)->get(route('admin.garages.index'));

        $response->assertSee(route('admin.garages.edit', $garage), false);
        $response->assertSee('Ubah garasi C001');
    }

    public function test_edit_shows_code_type_and_current_prices(): void
    {
        $garage = Garage::factory()->electric()->create(['code' => 'E004', 'daily_price' => 30_000, 'kwh_price' => 2_500]);

        $response = $this->actingAs($this->admin)->get(route('admin.garages.edit', $garage));

        $response->assertOk();
        $response->assertSee('Ubah Garasi E004');
        $response->assertSee('value="30000"', false);
        $response->assertSee('value="2500"', false);
    }

    public function test_edit_hides_kwh_price_for_garage_without_charging(): void
    {
        $garage = Garage::factory()->car()->create();

        $this->actingAs($this->admin)->get(route('admin.garages.edit', $garage))->assertDontSee('name="kwh_price"', false);
    }

    public function test_edit_is_forbidden_for_tenant(): void
    {
        $garage = Garage::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('admin.garages.edit', $garage))->assertForbidden();
    }

    public function test_edit_unknown_garage_returns_not_found(): void
    {
        $this->actingAs($this->admin)->get(route('admin.garages.edit', 999_999))->assertNotFound();
    }

    public function test_update_changes_daily_price_and_keeps_code_and_type(): void
    {
        // Arrange
        $garage = Garage::factory()->car()->create(['code' => 'C001', 'daily_price' => 25_000]);

        // Act: type and code in the request must be ignored
        $response = $this->actingAs($this->admin)->put(route('admin.garages.update', $garage), [
            'daily_price' => 30_000,
            'type' => GarageType::Bike->value,
            'code' => 'S999',
        ]);

        // Assert
        $response->assertRedirect(route('admin.garages.index', ['type' => GarageType::Car->value]));
        $response->assertSessionHas('success', 'Harga garasi C001 diperbarui.');
        $garage->refresh();
        $this->assertEquals(30_000, $garage->daily_price);
        $this->assertSame('C001', $garage->code);
        $this->assertSame(GarageType::Car, $garage->type);
    }

    public function test_update_electric_garage_changes_kwh_price(): void
    {
        $garage = Garage::factory()->electric()->create(['kwh_price' => 2_500]);

        $this->actingAs($this->admin)->put(route('admin.garages.update', $garage), ['daily_price' => 30_000, 'kwh_price' => 3_000]);

        $this->assertEquals(3_000, $garage->fresh()->kwh_price);
    }

    public function test_update_electric_garage_without_kwh_price_returns_validation_error(): void
    {
        $garage = Garage::factory()->electric()->create(['kwh_price' => 2_500]);

        $response = $this->actingAs($this->admin)->put(route('admin.garages.update', $garage), ['daily_price' => 30_000]);

        $response->assertSessionHasErrors('kwh_price');
        $this->assertEquals(2_500, $garage->fresh()->kwh_price);
    }

    public function test_update_car_garage_with_kwh_price_returns_validation_error(): void
    {
        $garage = Garage::factory()->car()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.garages.update', $garage), ['daily_price' => 30_000, 'kwh_price' => 3_000])
            ->assertSessionHasErrors('kwh_price');
    }

    public function test_update_with_zero_daily_price_returns_validation_error(): void
    {
        $garage = Garage::factory()->car()->create(['daily_price' => 25_000]);

        $this->actingAs($this->admin)
            ->put(route('admin.garages.update', $garage), ['daily_price' => 0])
            ->assertSessionHasErrors('daily_price');
        $this->assertEquals(25_000, $garage->fresh()->daily_price);
    }

    public function test_update_does_not_change_prices_of_existing_rentals(): void
    {
        $garage = Garage::factory()->car()->create(['daily_price' => 10_000]);
        $rental = Rental::factory()->for($garage)->daily(3)->startingOn(self::TODAY)->create();

        $this->actingAs($this->admin)->put(route('admin.garages.update', $garage), ['daily_price' => 50_000]);

        $rental->refresh();
        $this->assertEquals(10_000, $rental->daily_price);
        $this->assertEquals(30_000, $rental->total_cost);
    }

    public function test_update_is_forbidden_for_tenant(): void
    {
        $garage = Garage::factory()->car()->create(['daily_price' => 25_000]);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.garages.update', $garage), ['daily_price' => 1])
            ->assertForbidden();
        $this->assertEquals(25_000, $garage->fresh()->daily_price);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'type' => GarageType::Car->value,
            'quantity' => 1,
            'daily_price' => 25000,
        ], $overrides);
    }
}
