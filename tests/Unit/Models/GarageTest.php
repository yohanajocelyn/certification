<?php

namespace Tests\Unit\Models;

use App\Models\Garage;
use App\Models\Rental;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarageTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_usage_on_is_used_while_a_rental_covers_the_date(): void
    {
        $garage = Garage::factory()->create();
        Rental::factory()->for($garage)->daily(5)->startingOn('2026-11-21')->create();

        $this->assertTrue($this->isUsedOn($garage, '2026-11-23'));
    }

    public function test_with_usage_on_is_not_used_before_a_future_rental_starts(): void
    {
        // A December rental booked in November must not mark the garage used yet.
        $garage = Garage::factory()->create();
        Rental::factory()->for($garage)->monthly()->startingOn('2026-12-01')->create();

        $this->assertFalse($this->isUsedOn($garage, '2026-11-21'));
    }

    public function test_with_usage_on_is_not_used_after_the_rental_ended(): void
    {
        $garage = Garage::factory()->create();
        Rental::factory()->for($garage)->daily(5)->startingOn('2026-11-21')->create();

        $this->assertFalse($this->isUsedOn($garage, '2026-11-26'));
    }

    /**
     * The garage's `is_used` flag on the date, read the way the garage list reads it.
     */
    private function isUsedOn(Garage $garage, string $date): bool
    {
        return Garage::withUsageOn(CarbonImmutable::parse($date))->findOrFail($garage->id)->is_used;
    }
}
