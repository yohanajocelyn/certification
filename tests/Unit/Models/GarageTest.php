<?php

namespace Tests\Unit\Models;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarageTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_code_number_is_one_when_type_has_no_garages(): void
    {
        $this->assertSame(1, Garage::nextCodeNumber(GarageType::Electric));
    }

    public function test_next_code_number_continues_per_type(): void
    {
        // Arrange
        Garage::factory()->car()->create(['code' => 'C010']);
        Garage::factory()->bike()->create(['code' => 'S003']);

        // Act + Assert
        $this->assertSame(11, Garage::nextCodeNumber(GarageType::Car));
        $this->assertSame(4, Garage::nextCodeNumber(GarageType::Bike));
        $this->assertSame(1, Garage::nextCodeNumber(GarageType::Electric));
    }

    public function test_format_code_pads_number_to_three_digits(): void
    {
        $this->assertSame('S001', Garage::formatCode(GarageType::Bike, 1));
        $this->assertSame('C010', Garage::formatCode(GarageType::Car, 10));
        $this->assertSame('E999', Garage::formatCode(GarageType::Electric, Garage::MAX_CODE_NUMBER));
    }

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

    public function test_available_between_excludes_garage_with_overlapping_rental(): void
    {
        // Arrange
        $taken = Garage::factory()->create();
        $free = Garage::factory()->create();
        Rental::factory()->for($taken)->daily(5)->startingOn('2026-11-21')->create();

        // Act
        $available = Garage::availableBetween(CarbonImmutable::parse('2026-11-25'), CarbonImmutable::parse('2026-11-28'))->get();

        // Assert
        $this->assertCount(1, $available);
        $this->assertTrue($available->first()->is($free));
    }

    public function test_remaining_code_numbers_counts_codes_left_up_to_999(): void
    {
        Garage::factory()->car()->create(['code' => 'C010']);

        $this->assertSame(989, Garage::remainingCodeNumbers(GarageType::Car));
        $this->assertSame(Garage::MAX_CODE_NUMBER, Garage::remainingCodeNumbers(GarageType::Bike));
    }

    public function test_code_number_reads_the_number_inside_a_code(): void
    {
        $this->assertSame(1, Garage::codeNumber('S001'));
        $this->assertSame(10, Garage::codeNumber('C010'));
        $this->assertSame(Garage::MAX_CODE_NUMBER, Garage::codeNumber('E999'));
    }

    public function test_with_usage_on_flags_each_garage_in_one_query(): void
    {
        $used = Garage::factory()->create();
        $free = Garage::factory()->create();
        Rental::factory()->for($used)->startingOn('2026-11-21')->create();

        $garages = Garage::withUsageOn(CarbonImmutable::parse('2026-11-21'))->get()->keyBy('id');

        $this->assertTrue($garages[$used->id]->is_used);
        $this->assertFalse($garages[$free->id]->is_used);
    }

    public function test_monthly_price_is_25_times_daily_price(): void
    {
        $garage = Garage::factory()->make(['daily_price' => 10_000]);

        $this->assertSame(250_000.0, $garage->monthlyPrice());
    }

    /**
     * The garage's `is_used` flag on the date, read the way the garage list reads it.
     */
    private function isUsedOn(Garage $garage, string $date): bool
    {
        return Garage::withUsageOn(CarbonImmutable::parse($date))->findOrFail($garage->id)->is_used;
    }
}
