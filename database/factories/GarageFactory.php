<?php

namespace Database\Factories;

use App\Enums\GarageType;
use App\Models\Garage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds garages with a unique code matching their type; car garages by default.
 *
 * @extends Factory<Garage>
 */
class GarageFactory extends Factory
{
    private const MIN_DAILY_PRICE = 5_000;

    private const MAX_DAILY_PRICE = 50_000;

    private const PRICE_STEP = 1_000;

    private const MIN_KWH_PRICE = 1_500;

    private const MAX_KWH_PRICE = 3_000;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => GarageType::Car,
            // A number unique across all types also keeps the code unique within each type.
            'code' => fn (array $attributes) => Garage::formatCode(
                GarageType::from($this->typeValue($attributes['type'])),
                fake()->unique()->numberBetween(1, Garage::MAX_CODE_NUMBER),
            ),
            'daily_price' => fake()->numberBetween(
                self::MIN_DAILY_PRICE / self::PRICE_STEP,
                self::MAX_DAILY_PRICE / self::PRICE_STEP,
            ) * self::PRICE_STEP,
            'kwh_price' => null,
        ];
    }

    /**
     * A bike garage (code S...).
     */
    public function bike(): static
    {
        return $this->state(fn () => ['type' => GarageType::Bike, 'kwh_price' => null]);
    }

    /**
     * A car garage (code C...).
     */
    public function car(): static
    {
        return $this->state(fn () => ['type' => GarageType::Car, 'kwh_price' => null]);
    }

    /**
     * An electric garage (code E...) with a kWh charging price.
     */
    public function electric(): static
    {
        return $this->state(fn () => [
            'type' => GarageType::Electric,
            'kwh_price' => fake()->numberBetween(self::MIN_KWH_PRICE, self::MAX_KWH_PRICE),
        ]);
    }

    /**
     * Accept the type either as an enum case or as its stored value.
     */
    private function typeValue(GarageType|string $type): string
    {
        return $type instanceof GarageType ? $type->value : $type;
    }
}
