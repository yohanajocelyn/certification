<?php

namespace Tests\Unit\Models;

use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_owner(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();

        $this->assertTrue($vehicle->user->is($owner));
    }

    public function test_plate_number_is_stored_in_capitals_with_single_spaces(): void
    {
        $vehicle = Vehicle::factory()->create(['plate_number' => '  b   1234  xyz ']);

        $this->assertSame('B 1234 XYZ', $vehicle->fresh()->plate_number);
    }

    public function test_normalize_plate_turns_blank_into_null(): void
    {
        $this->assertNull(Vehicle::normalizePlate('   '));
        $this->assertNull(Vehicle::normalizePlate(null));
    }

    public function test_display_name_skips_missing_plate(): void
    {
        $bicycle = Vehicle::factory()->bicycle()->make(['brand_model' => 'Polygon Xtrada']);

        $this->assertSame('Sepeda · Polygon Xtrada', $bicycle->displayName());
    }

    public function test_is_parked_between_is_true_when_a_rental_overlaps(): void
    {
        $vehicle = Vehicle::factory()->create();
        Rental::factory()->for($vehicle, 'vehicle')->daily(5)->startingOn('2026-11-21')->create();

        $this->assertTrue($vehicle->isParkedBetween(CarbonImmutable::parse('2026-11-25'), CarbonImmutable::parse('2026-11-30')));
    }

    public function test_is_parked_between_is_false_when_no_rental_overlaps(): void
    {
        $vehicle = Vehicle::factory()->create();
        Rental::factory()->for($vehicle, 'vehicle')->daily(5)->startingOn('2026-11-21')->create();

        $this->assertFalse($vehicle->isParkedBetween(CarbonImmutable::parse('2026-11-26'), CarbonImmutable::parse('2026-11-30')));
    }

    public function test_is_parked_between_ignores_the_given_rental(): void
    {
        $vehicle = Vehicle::factory()->create();
        $rental = Rental::factory()->for($vehicle, 'vehicle')->daily(5)->startingOn('2026-11-21')->create();

        $isParked = $vehicle->isParkedBetween(CarbonImmutable::parse('2026-11-25'), CarbonImmutable::parse('2026-11-30'), $rental);

        $this->assertFalse($isParked);
    }
}
