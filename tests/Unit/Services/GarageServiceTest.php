<?php

namespace Tests\Unit\Services;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Services\GarageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LengthException;
use Tests\TestCase;

class GarageServiceTest extends TestCase
{
    use RefreshDatabase;

    private GarageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-21');
        $this->service = app(GarageService::class);
    }

    public function test_create_batch_starts_at_001_for_a_new_type(): void
    {
        $codes = $this->service->createBatch(GarageType::Bike, 3, 5_000, null);

        $this->assertSame(['S001', 'S002', 'S003'], $codes->all());
        $this->assertSame(3, Garage::where('daily_price', 5_000)->count());
    }

    public function test_create_batch_continues_after_the_highest_code_of_the_same_type(): void
    {
        Garage::factory()->electric()->create(['code' => 'E002']);
        Garage::factory()->car()->create(['code' => 'C010']);

        $codes = $this->service->createBatch(GarageType::Electric, 3, 30_000, 2_500);

        $this->assertSame(['E003', 'E004', 'E005'], $codes->all());
        $this->assertSame(3, Garage::where('kwh_price', 2_500)->whereIn('code', $codes)->count());
    }

    public function test_create_batch_drops_kwh_price_for_garages_that_do_not_charge(): void
    {
        $this->service->createBatch(GarageType::Car, 1, 25_000, 2_500);

        $this->assertNull(Garage::firstWhere('code', 'C001')->kwh_price);
    }

    public function test_create_batch_can_fill_the_last_code(): void
    {
        Garage::factory()->car()->create(['code' => 'C998']);

        $this->assertSame(['C999'], $this->service->createBatch(GarageType::Car, 1, 25_000, null)->all());
    }

    public function test_create_batch_with_zero_quantity_throws_and_creates_nothing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            $this->service->createBatch(GarageType::Car, 0, 25_000, null);
        } finally {
            $this->assertDatabaseCount('garages', 0);
        }
    }

    public function test_create_batch_past_code_999_throws_and_creates_nothing(): void
    {
        Garage::factory()->car()->create(['code' => 'C998']);
        $this->expectException(LengthException::class);

        try {
            $this->service->createBatch(GarageType::Car, 2, 25_000, null);
        } finally {
            $this->assertDatabaseCount('garages', 1);
        }
    }

    public function test_paginate_filters_by_type_and_flags_garages_used_today(): void
    {
        $used = Garage::factory()->car()->create(['code' => 'C001']);
        Garage::factory()->car()->create(['code' => 'C002']);
        Garage::factory()->bike()->create(['code' => 'S001']);
        Rental::factory()->for($used)->startingOn('2026-11-21')->create();

        $garages = $this->service->paginate(GarageType::Car);

        $this->assertSame(['C001', 'C002'], $garages->pluck('code')->all());
        $this->assertTrue($garages->firstWhere('code', 'C001')->is_used);
        $this->assertFalse($garages->firstWhere('code', 'C002')->is_used);
    }

    public function test_next_codes_gives_next_code_and_codes_left_per_type(): void
    {
        Garage::factory()->car()->create(['code' => 'C010']);

        $nextCodes = $this->service->nextCodes();

        $this->assertSame(['next' => 'C011', 'remaining' => 989], $nextCodes[GarageType::Car->value]);
        $this->assertSame(['next' => 'S001', 'remaining' => Garage::MAX_CODE_NUMBER], $nextCodes[GarageType::Bike->value]);
    }
}
