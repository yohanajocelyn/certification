<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Vehicle use cases for tenants. Vehicles are registered while renting and never edited.
 */
class VehicleService
{
    private const VEHICLES_PER_PAGE = 10;

    /**
     * One page of the tenant's vehicles, each with the rental (and garage) covering today.
     *
     * @return LengthAwarePaginator<int, Vehicle>
     */
    public function paginateForTenant(User $tenant): LengthAwarePaginator
    {
        return $tenant->vehicles()
            ->with('rentalsToday.garage')
            ->orderBy('brand_model')
            ->paginate(self::VEHICLES_PER_PAGE);
    }

    /**
     * All of the tenant's vehicles by name, to choose from when renting.
     *
     * @return Collection<int, Vehicle>
     */
    public function allForTenant(User $tenant): Collection
    {
        return $tenant->vehicles()->orderBy('brand_model')->get();
    }
}
