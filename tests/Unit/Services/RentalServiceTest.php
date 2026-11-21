<?php

namespace Tests\Unit\Services;

use App\Enums\RentalType;
use App\Exceptions\RentalNotExtendableException;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use App\Models\Vehicle;
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

    // ---- extensions -----------------------------------------------------------------------

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
