<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Exceptions\TenantAlreadyDecidedException;
use App\Models\User;
use App\Services\Results\TenantOverview;

/**
 * Tenant use cases: registration and the admin's verification (accept or reject, once).
 */
class TenantService
{
    private const TENANTS_PER_PAGE = 15;

    /**
     * Register a new tenant. Role and status always come from the model defaults (pending tenant).
     *
     * @param  array{nik: string, name: string, phone: string, email: string, password: string}  $data  validated input
     */
    public function register(array $data): User
    {
        return User::create([
            'nik' => $data['nik'],
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }

    /**
     * Tenants with one status (newest registration first) and the number of tenants per status.
     */
    public function overview(UserStatus $status): TenantOverview
    {
        $countsByStatus = [];
        foreach (UserStatus::cases() as $eachStatus) {
            $countsByStatus[$eachStatus->value] = User::tenants()->where('status', $eachStatus)->count();
        }

        $tenants = User::tenants()->where('status', $status)->latest()->paginate(self::TENANTS_PER_PAGE);

        return new TenantOverview($tenants, $countsByStatus);
    }

    /**
     * Accept a pending registration so the tenant can log in.
     *
     * @throws TenantAlreadyDecidedException when the registration was already accepted or rejected
     */
    public function approve(User $tenant): User
    {
        return $this->decide($tenant, UserStatus::Approved);
    }

    /**
     * Reject a pending registration.
     *
     * @throws TenantAlreadyDecidedException when the registration was already accepted or rejected
     */
    public function reject(User $tenant): User
    {
        return $this->decide($tenant, UserStatus::Rejected);
    }

    /**
     * A registration is decided once: only pending tenants change status.
     *
     * @throws TenantAlreadyDecidedException
     */
    private function decide(User $tenant, UserStatus $decision): User
    {
        if (! $tenant->isPending()) {
            throw TenantAlreadyDecidedException::for($tenant);
        }

        $tenant->update(['status' => $decision]);

        return $tenant;
    }
}
