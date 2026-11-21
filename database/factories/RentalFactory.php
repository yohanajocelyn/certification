<?php

namespace Database\Factories;

use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a consistent rental: the tenant owns the vehicle, and dates and costs follow the
 * Rental rules. Default: a 3-day daily rental of a car garage starting today.
 *
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    private const DEFAULT_DAYS = 3;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'user_id' => fn (array $attributes) => Vehicle::findOrFail($attributes['vehicle_id'])->user_id,
            'garage_id' => Garage::factory(),
            'current_battery_kwh' => null,
            'rental_type' => RentalType::Daily,
            'days' => self::DEFAULT_DAYS,
            'start_date' => CarbonImmutable::today(),
            'end_date' => fn (array $attributes) => Rental::calculateEndDate(
                $this->rentalType($attributes['rental_type']),
                CarbonImmutable::parse($attributes['start_date']),
                $attributes['days'],
            ),
            'daily_price' => fn (array $attributes) => Garage::findOrFail($attributes['garage_id'])->daily_price,
            'kwh_price' => fn (array $attributes) => Garage::findOrFail($attributes['garage_id'])->kwh_price,
            'rental_cost' => fn (array $attributes) => Rental::calculateRentalCost(
                $this->rentalType($attributes['rental_type']),
                (float) $attributes['daily_price'],
                $attributes['days'],
            ),
            'charging_kwh' => 0,
            'charging_cost' => 0,
            'total_cost' => fn (array $attributes) => (float) $attributes['rental_cost'] + (float) $attributes['charging_cost'],
            'payment_proof' => 'payment-proofs/example.jpg',
        ];
    }

    /**
     * A daily rental of the given number of days.
     */
    public function daily(int $days = self::DEFAULT_DAYS): static
    {
        return $this->state(fn () => ['rental_type' => RentalType::Daily, 'days' => $days]);
    }

    /**
     * A monthly rental.
     */
    public function monthly(): static
    {
        return $this->state(fn () => ['rental_type' => RentalType::Monthly, 'days' => null]);
    }

    /**
     * Start on a given date; the end date is recalculated from it.
     */
    public function startingOn(CarbonInterface|string $date): static
    {
        return $this->state(fn () => ['start_date' => CarbonImmutable::parse($date)]);
    }

    /**
     * Accept the type either as an enum case or as its stored value.
     */
    private function rentalType(RentalType|string $type): RentalType
    {
        return $type instanceof RentalType ? $type : RentalType::from($type);
    }
}
