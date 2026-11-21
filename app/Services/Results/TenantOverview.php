<?php

namespace App\Services\Results;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The tenant verification page: one page of tenants with the chosen status, and how many
 * tenants have each status.
 */
final readonly class TenantOverview
{
    /**
     * @param  LengthAwarePaginator<int, User>  $tenants
     * @param  array<string, int>  $countsByStatus  status value => number of tenants
     */
    public function __construct(
        public LengthAwarePaginator $tenants,
        public array $countsByStatus,
    ) {}
}
