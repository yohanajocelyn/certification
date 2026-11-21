<?php

namespace Database\Factories;

use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds vehicles owned by a tenant; a petrol car by default.
 *
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    private const MIN_BATTERY_KWH = 40;

    private const MAX_BATTERY_KWH = 100;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => VehicleType::Car,
            'plate_number' => strtoupper(fake()->unique()->bothify('? #### ??')),
            'brand_model' => fake()->randomElement(['Toyota Avanza', 'Honda Brio', 'Suzuki Ertiga']),
            'battery_capacity_kwh' => null,
        ];
    }

    /**
     * A bicycle: no plate, no battery.
     */
    public function bicycle(): static
    {
        return $this->state(fn () => [
            'type' => VehicleType::Bicycle,
            'plate_number' => null,
            'brand_model' => fake()->randomElement(['Polygon Xtrada', 'United Detroit']),
            'battery_capacity_kwh' => null,
        ]);
    }

    /**
     * A motorcycle: plate, no battery.
     */
    public function motorcycle(): static
    {
        return $this->state(fn () => [
            'type' => VehicleType::Motorcycle,
            'brand_model' => fake()->randomElement(['Honda Beat', 'Yamaha NMAX']),
            'battery_capacity_kwh' => null,
        ]);
    }

    /**
     * An electric car with a full-charge battery capacity.
     *
     * @param  float|null  $capacityKwh  fixed capacity, random when null
     */
    public function electricCar(?float $capacityKwh = null): static
    {
        return $this->state(fn () => [
            'type' => VehicleType::ElectricCar,
            'brand_model' => fake()->randomElement(['Hyundai Ioniq 5', 'Wuling Air ev']),
            'battery_capacity_kwh' => $capacityKwh ?? fake()->numberBetween(self::MIN_BATTERY_KWH, self::MAX_BATTERY_KWH),
        ]);
    }
}
