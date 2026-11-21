<?php

namespace Database\Factories;

use App\Enums\RentalType;
use App\Models\Rental;
use App\Models\RentalExtension;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a daily extension that adds a few days after the rental's current end date.
 *
 * @extends Factory<RentalExtension>
 */
class RentalExtensionFactory extends Factory
{
    private const DEFAULT_EXTRA_DAYS = 2;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rental_id' => Rental::factory(),
            'extension_type' => RentalType::Daily,
            'months' => null,
            'days' => self::DEFAULT_EXTRA_DAYS,
            'old_end_date' => fn (array $attributes) => Rental::findOrFail($attributes['rental_id'])->end_date,
            'new_end_date' => fn (array $attributes) => CarbonImmutable::parse($attributes['old_end_date'])->addDays($attributes['days']),
            'additional_cost' => fn (array $attributes) => RentalExtension::calculateAdditionalCost(
                RentalType::Daily,
                (float) Rental::findOrFail($attributes['rental_id'])->garage->daily_price,
                $attributes['days'],
            ),
            'payment_proof' => 'payment-proofs/example.jpg',
        ];
    }
}
