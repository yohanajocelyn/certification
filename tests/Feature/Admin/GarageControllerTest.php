<?php

namespace Tests\Feature\Admin;

use App\Enums\GarageType;
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

    public function test_store_electric_without_kwh_price_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->validData([
            'type' => GarageType::Electric->value,
        ]));

        $response->assertSessionHasErrors('kwh_price');
        $this->assertDatabaseCount('garages', 0);
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
