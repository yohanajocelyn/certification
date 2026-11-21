<?php

namespace App\Services\Results;

use App\Enums\RentalType;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What a tenant sees before paying: the vehicle, the period and an unsaved rental (with its total)
 * for every free garage, grouped by garage type.
 */
final readonly class RentalQuote
{
    /**
     * @param  array<string, Collection<int, Rental>>  $offers  garage type value => draft rentals
     */
    public function __construct(
        public Vehicle $vehicle,
        public RentalType $rentalType,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public int $durationDays,
        public array $offers,
    ) {}
}
