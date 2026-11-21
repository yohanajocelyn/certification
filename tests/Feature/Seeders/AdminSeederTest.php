<?php

namespace Tests\Feature\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('admin', [
            'name' => 'Admin Garasi',
            'email' => 'admin@garasi.test',
            'password' => 'admin-password',
            'nik' => '3100000000000001',
            'phone' => '081100000000',
        ]);
    }

    public function test_seeder_creates_approved_admin_from_config(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::firstWhere('email', 'admin@garasi.test');
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame(UserStatus::Approved, $admin->status);
        $this->assertTrue(Hash::check('admin-password', $admin->password));
    }

    public function test_seeder_run_twice_keeps_a_single_admin(): void
    {
        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_seeder_without_admin_email_creates_nothing(): void
    {
        config()->set('admin.email', null);

        $this->seed(AdminSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_seeded_admin_can_log_in(): void
    {
        $this->seed(AdminSeeder::class);

        $this->post(route('login.store'), ['email' => 'admin@garasi.test', 'password' => 'admin-password']);

        $this->assertAuthenticated();
    }
}
