<?php

namespace Tests\Unit\Services;

use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Exceptions\RentalNotExtendableException;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vehicles\Motorcycle;
use App\Services\RentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RentalServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-21';

    private RentalService $service;

    private User $tenant;

    private Vehicle $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::TODAY);
        Storage::fake(Rental::PAYMENT_PROOF_DISK);
        $this->service = app(RentalService::class);
        $this->tenant = User::factory()->create();
        $this->car = Vehicle::factory()->for($this->tenant)->create();
    }

    // ---- quote ----------------------------------------------------------------------------

    public function test_quote_gives_the_period_and_free_garages_of_each_allowed_type(): void
    {
        // Arrange
        $taken = Garage::factory()->car()->create(['code' => 'C001']);
        Garage::factory()->car()->create(['code' => 'C002', 'daily_price' => 10_000]);
        Garage::factory()->bike()->create(['code' => 'S001']);
        Rental::factory()->for($taken)->daily(2)->startingOn('2026-11-24')->create();

        // Act
        $quote = $this->service->quote($this->tenant, $this->details(['days' => 5]));

        // Assert
        $this->assertSame('2026-11-25', $quote['end']->toDateString());
        $this->assertSame(5, $quote['durationDays']);
        $this->assertSame([GarageType::Car->value], array_keys($quote['offers']));
        $this->assertSame(['C002'], $quote['offers'][GarageType::Car->value]->map(fn ($draft) => $draft->garage->code)->all());
        $this->assertEquals(50_000, $quote['offers'][GarageType::Car->value]->first()->total_cost);
    }

    public function test_quote_for_electric_car_adds_charging_only_in_electric_garages(): void
    {
        $electricCar = Vehicle::factory()->electricCar(60)->for($this->tenant)->create();
        Garage::factory()->car()->create(['daily_price' => 10_000]);
        Garage::factory()->electric()->create(['daily_price' => 10_000, 'kwh_price' => 2_500]);

        $quote = $this->service->quote($this->tenant, $this->details(['vehicle_id' => $electricCar->id, 'current_battery_kwh' => 20]));

        $this->assertEquals(30_000, $quote['offers'][GarageType::Car->value]->first()->total_cost);
        $this->assertEquals(130_000, $quote['offers'][GarageType::Electric->value]->first()->total_cost);
    }

    public function test_quote_for_a_vehicle_already_parked_in_the_period_throws_on_vehicle_id(): void
    {
        Rental::factory()->for($this->car, 'vehicle')->startingOn(self::TODAY)->create();

        $this->assertValidationErrorOn('vehicle_id', fn () => $this->service->quote($this->tenant, $this->details()));
    }

    // ---- book -----------------------------------------------------------------------------

    public function test_book_saves_the_rental_with_costs_and_payment_proof(): void
    {
        $garage = Garage::factory()->car()->create(['daily_price' => 10_000]);

        $rental = $this->service->book($this->tenant, $this->details(['days' => 5]), $garage->id, $this->proof());

        $this->assertTrue($rental->exists);
        $this->assertTrue($rental->vehicle->user->is($this->tenant));
        $this->assertEquals(50_000, $rental->total_cost);
        Storage::disk(Rental::PAYMENT_PROOF_DISK)->assertExists($rental->payment_proof);
    }

    public function test_book_with_a_new_vehicle_registers_it_as_the_right_class_for_the_tenant(): void
    {
        $garage = Garage::factory()->bike()->create();

        $rental = $this->service->book($this->tenant, $this->details([
            'vehicle_id' => RentalService::NEW_VEHICLE,
            'vehicle_type' => VehicleType::Motorcycle->value,
            'plate_number' => 'D 4321 ABC',
            'brand_model' => 'Honda Beat',
        ]), $garage->id, $this->proof());

        $this->assertInstanceOf(Motorcycle::class, $rental->vehicle->fresh());
        $this->assertTrue($rental->vehicle->user->is($this->tenant));
    }

    public function test_book_a_garage_the_vehicle_does_not_fit_throws_on_garage_type_and_saves_nothing(): void
    {
        $bikeGarage = Garage::factory()->bike()->create();

        $this->assertValidationErrorOn('garage_type', fn () => $this->service->book($this->tenant, $this->details(), $bikeGarage->id, $this->proof()));
        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_book_a_garage_taken_in_the_period_throws_on_garage_id_and_saves_nothing(): void
    {
        $garage = Garage::factory()->car()->create();
        Rental::factory()->for($garage)->daily(2)->startingOn('2026-11-22')->create();

        $this->assertValidationErrorOn('garage_id', fn () => $this->service->book($this->tenant, $this->details(), $garage->id, $this->proof()));
        $this->assertDatabaseCount('rentals', 1);
    }

    public function test_book_a_vehicle_already_in_another_garage_throws_on_vehicle_id(): void
    {
        Rental::factory()->for($this->car, 'vehicle')->startingOn(self::TODAY)->create();
        $garage = Garage::factory()->car()->create();

        $this->assertValidationErrorOn('vehicle_id', fn () => $this->service->book($this->tenant, $this->details(), $garage->id, $this->proof()));
        $this->assertDatabaseCount('rentals', 1);
    }

    public function test_book_failing_for_a_new_vehicle_does_not_register_it(): void
    {
        $bikeGarage = Garage::factory()->bike()->create();

        try {
            $this->service->book($this->tenant, $this->details([
                'vehicle_id' => RentalService::NEW_VEHICLE,
                'vehicle_type' => VehicleType::Car->value,
                'plate_number' => 'B 9999 NEW',
                'brand_model' => 'Honda Brio',
            ]), $bikeGarage->id, $this->proof());
        } catch (ValidationException) {
            // expected: a car does not fit a bike garage
        }

        $this->assertDatabaseMissing('vehicles', ['plate_number' => 'B 9999 NEW']);
    }

    // ---- lists ----------------------------------------------------------------------------

    public function test_rentals_of_returns_only_the_tenants_rentals_newest_first(): void
    {
        $older = Rental::factory()->for($this->car, 'vehicle')->startingOn('2026-11-01')->create();
        $newer = Rental::factory()->for($this->car, 'vehicle')->startingOn('2026-11-21')->create();
        Rental::factory()->create();

        $this->assertSame([$newer->id, $older->id], $this->service->rentalsOf($this->tenant)->pluck('id')->all());
    }

    public function test_active_today_lists_rentals_covering_today_ending_soonest_first(): void
    {
        $endsLater = Rental::factory()->daily(10)->startingOn('2026-11-20')->create();
        $endsSooner = Rental::factory()->daily(3)->startingOn('2026-11-21')->create();
        Rental::factory()->startingOn('2026-12-01')->create();

        $this->assertSame([$endsSooner->id, $endsLater->id], $this->service->activeToday()->pluck('id')->all());
    }

    // ---- extensions -----------------------------------------------------------------------

    public function test_prepare_extension_of_a_finished_rental_throws(): void
    {
        $finished = Rental::factory()->daily(2)->startingOn('2026-11-01')->create();
        $this->expectException(RentalNotExtendableException::class);

        $this->service->prepareExtension($finished);
    }

    public function test_quote_extension_gives_the_days_added_and_remaining_cost(): void
    {
        $rental = $this->runningRental();

        $extension = $this->service->quoteExtension($rental, $this->dailyExtension('2026-11-30'));

        $this->assertFalse($extension->exists);
        $this->assertSame(5, $extension->days);
        $this->assertEquals(50_000, $extension->additional_cost);
    }

    public function test_quote_extension_clashing_with_the_next_booking_throws_on_new_end_date(): void
    {
        $rental = $this->runningRental();
        Rental::factory()->for($rental->garage)->daily(2)->startingOn('2026-11-28')->create();

        $this->assertValidationErrorOn('new_end_date', fn () => $this->service->quoteExtension($rental, $this->dailyExtension('2026-11-28')));
    }

    public function test_extend_saves_the_extension_and_moves_the_rental_end_date(): void
    {
        $rental = $this->runningRental();

        $extension = $this->service->extend($rental, $this->dailyExtension('2026-11-30'), $this->proof());

        $this->assertTrue($extension->exists);
        $this->assertSame('2026-11-30', $rental->fresh()->end_date->toDateString());
        Storage::disk(Rental::PAYMENT_PROOF_DISK)->assertExists($extension->payment_proof);
    }

    public function test_extend_monthly_adds_the_end_month_days_per_month(): void
    {
        $rental = $this->runningRental();

        $this->service->extend($rental, ['extension_type' => RentalType::Monthly->value, 'months' => 1], $this->proof());

        // Ends in November (30 days): 25 Nov + 30 = 25 Dec.
        $this->assertSame('2026-12-25', $rental->fresh()->end_date->toDateString());
    }

    public function test_extend_a_finished_rental_throws_and_saves_nothing(): void
    {
        $finished = Rental::factory()->daily(2)->startingOn('2026-11-01')->create();

        try {
            $this->service->extend($finished, $this->dailyExtension('2026-11-30'), $this->proof());
            $this->fail('A finished rental was extended.');
        } catch (RentalNotExtendableException) {
            $this->assertSame(0, RentalExtension::count());
        }
    }

    // ---- helpers --------------------------------------------------------------------------

    /**
     * Validated step 1 for the tenant's petrol car: daily, 3 days, from today.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function details(array $overrides = []): array
    {
        return array_merge([
            'vehicle_id' => (string) $this->car->id,
            'rental_type' => RentalType::Daily->value,
            'start_date' => self::TODAY,
            'days' => 3,
        ], $overrides);
    }

    /**
     * @return array<string, string>
     */
    private function dailyExtension(string $newEndDate): array
    {
        return ['extension_type' => RentalType::Daily->value, 'new_end_date' => $newEndDate];
    }

    /**
     * A 5-day rental from today (21 to 25 Nov) at 10.000 per day.
     */
    private function runningRental(): Rental
    {
        return Rental::factory()
            ->for(Garage::factory()->car()->create(['daily_price' => 10_000]))
            ->daily(5)
            ->startingOn(self::TODAY)
            ->create();
    }

    private function proof(): UploadedFile
    {
        return UploadedFile::fake()->image('bukti.jpg');
    }

    /**
     * Assert the action throws a ValidationException with an error on the given field.
     */
    private function assertValidationErrorOn(string $field, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected a validation error on {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
