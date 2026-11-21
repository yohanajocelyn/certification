<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_defaults_to_pending_tenant(): void
    {
        $user = User::create([
            'nik' => '3174012345678901',
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'password' => 'secret-password',
        ]);

        $this->assertSame(UserRole::Tenant, $user->fresh()->role);
        $this->assertSame(UserStatus::Pending, $user->fresh()->status);
    }

    public function test_password_is_stored_hashed(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->assertNotSame('secret-password', $user->getAttributes()['password']);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_is_admin_is_true_only_for_admin(): void
    {
        $this->assertTrue(User::factory()->admin()->make()->isAdmin());
        $this->assertFalse(User::factory()->make()->isAdmin());
    }

    public function test_is_approved_is_true_only_for_approved_status(): void
    {
        $this->assertTrue(User::factory()->make()->isApproved());
        $this->assertFalse(User::factory()->pending()->make()->isApproved());
        $this->assertFalse(User::factory()->rejected()->make()->isApproved());
    }

    public function test_vehicles_relation_returns_owned_vehicles(): void
    {
        $user = User::factory()->has(Vehicle::factory()->count(2))->create();

        $this->assertCount(2, $user->vehicles);
    }
}
