<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * An account: either an admin or a tenant who rents garages.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Digits with an optional leading "+", e.g. 081234567890 or +6281234567890. */
    public const PHONE_PATTERN = '/^\+?[0-9]+$/';

    protected $fillable = ['nik', 'name', 'phone', 'email', 'password', 'role', 'status'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * New accounts are pending tenants until an admin verifies them.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'role' => UserRole::Tenant->value,
        'status' => UserStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Vehicles registered by this tenant.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Garage rentals of this tenant, found through their vehicles (rentals have no user_id).
     */
    public function rentals(): HasManyThrough
    {
        return $this->hasManyThrough(Rental::class, Vehicle::class);
    }

    /**
     * Whether this account manages the app.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Only tenant accounts (no admins).
     *
     * @param  Builder<User>  $query
     */
    public function scopeTenants(Builder $query): void
    {
        $query->where('role', UserRole::Tenant);
    }

    /**
     * Whether this registration still waits for an admin's decision.
     */
    public function isPending(): bool
    {
        return $this->status === UserStatus::Pending;
    }

    /**
     * Whether an admin has accepted this account, so it may log in.
     */
    public function isApproved(): bool
    {
        return $this->status === UserStatus::Approved;
    }
}
