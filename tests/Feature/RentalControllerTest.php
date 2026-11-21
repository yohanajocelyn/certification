<?php

namespace Tests\Feature;

use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
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

    // ---- Step 2: preview ------------------------------------------------------------------

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

    // ---- Step 2 submit: store -------------------------------------------------------------

    public function test_store_without_payment_proof_returns_validation_error(): void
    {
        $garage = Garage::factory()->car()->create();

        $response = $this->actingAs($this->tenant)->post(route('rentals.store'), $this->storeData($garage, ['payment_proof' => null]));

        $response->assertSessionHasErrors('payment_proof');
        $this->assertDatabaseCount('rentals', 0);
    }

    // ---- Helpers --------------------------------------------------------------------------

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
