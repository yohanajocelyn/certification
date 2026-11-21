<?php

namespace Database\Seeders;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Sample tenants, garages and rentals so every page has something to show.
 */
class DemoSeeder extends Seeder
{
    private const GARAGES_PER_TYPE = [
        GarageType::Bike->value => 5,
        GarageType::Car->value => 8,
        GarageType::Electric->value => 4,
    ];

    /**
     * Seed tenants in every status, garages with sequential codes, and a few rentals.
     */
    public function run(): void
    {
        $tenants = User::factory()->count(3)->create();
        User::factory()->count(2)->pending()->create();
        User::factory()->rejected()->create();

        $garages = $this->seedGarages();
        $this->seedRentals($tenants->all(), $garages);
    }

    /**
     * Create garages S001.., C001.., E001.. as an admin batch would.
     *
     * @return array<string, list<Garage>> garages keyed by type value
     */
    private function seedGarages(): array
    {
        $garages = [];

        foreach (self::GARAGES_PER_TYPE as $typeValue => $count) {
            $type = GarageType::from($typeValue);
            $factory = match ($type) {
                GarageType::Bike => Garage::factory()->bike(),
                GarageType::Car => Garage::factory()->car(),
                GarageType::Electric => Garage::factory()->electric(),
            };

            $garages[$typeValue] = $factory->count($count)
                ->sequence(fn ($sequence) => ['code' => Garage::formatCode($type, $sequence->index + 1)])
                ->create()
                ->all();
        }

        return $garages;
    }

    /**
     * One active, one upcoming and one finished rental, each in its own garage.
     *
     * @param  list<User>  $tenants
     * @param  array<string, list<Garage>>  $garages
     */
    private function seedRentals(array $tenants, array $garages): void
    {
        $today = CarbonImmutable::today();

        $car = Vehicle::factory()->for($tenants[0])->create();
        Rental::factory()->for($car)->for($tenants[0])->for($garages[GarageType::Car->value][0])
            ->daily(5)->startingOn($today->subDays(2))->create();

        $bicycle = Vehicle::factory()->bicycle()->for($tenants[0])->create();
        Rental::factory()->for($bicycle)->for($tenants[0])->for($garages[GarageType::Bike->value][0])
            ->monthly()->startingOn($today)->create();

        $electricCar = Vehicle::factory()->electricCar()->for($tenants[1])->create();
        Rental::factory()->for($electricCar)->for($tenants[1])->for($garages[GarageType::Car->value][1])
            ->daily(3)->startingOn($today->addWeek())->create();

        $motorcycle = Vehicle::factory()->motorcycle()->for($tenants[2])->create();
        Rental::factory()->for($motorcycle)->for($tenants[2])->for($garages[GarageType::Bike->value][1])
            ->daily(4)->startingOn($today->subMonth())->create();
    }
}
