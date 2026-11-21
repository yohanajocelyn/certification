<?php

namespace Tests\Unit\Enums;

use App\Enums\GarageType;
use PHPUnit\Framework\TestCase;

class GarageTypeTest extends TestCase
{
    public function test_prefix_returns_code_letter_for_each_type(): void
    {
        $this->assertSame('S', GarageType::Bike->prefix());
        $this->assertSame('C', GarageType::Car->prefix());
        $this->assertSame('E', GarageType::Electric->prefix());
    }

    public function test_has_charging_is_true_only_for_electric_garage(): void
    {
        $this->assertTrue(GarageType::Electric->hasCharging());
        $this->assertFalse(GarageType::Car->hasCharging());
        $this->assertFalse(GarageType::Bike->hasCharging());
    }
}
