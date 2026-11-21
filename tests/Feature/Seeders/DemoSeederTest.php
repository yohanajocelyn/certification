<?php

namespace Tests\Feature\Seeders;

use App\Enums\UserStatus;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_tenants_in_every_status(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (UserStatus::cases() as $status) {
            $this->assertTrue(User::where('status', $status)->exists(), "No {$status->value} tenant seeded");
        }
    }

    public function test_demo_seeder_numbers_garage_codes_from_one(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertDatabaseHas('garages', ['code' => 'S001']);
        $this->assertDatabaseHas('garages', ['code' => 'C001']);
        $this->assertDatabaseHas('garages', ['code' => 'E001']);
    }

    public function test_demo_rentals_belong_to_the_vehicle_owner_and_fit_the_garage(): void
    {
        $this->seed(DemoSeeder::class);

        $rentals = Rental::with(['vehicle', 'garage'])->get();

        $this->assertNotEmpty($rentals);
        foreach ($rentals as $rental) {
            $this->assertSame($rental->user_id, $rental->vehicle->user_id);
            $this->assertTrue($rental->vehicle->type->canUseGarage($rental->garage->type));
        }
        $this->assertGreaterThan(0, Garage::count());
    }
}
