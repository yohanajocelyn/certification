<?php

namespace Tests\Unit\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\TenantAlreadyDecidedException;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TenantService::class);
    }

    public function test_register_creates_a_pending_tenant_even_when_role_and_status_are_passed(): void
    {
        $tenant = $this->service->register([
            'nik' => '3174012345678901',
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'password' => 'secret-password',
            'role' => UserRole::Admin->value,
            'status' => UserStatus::Approved->value,
        ]);

        $this->assertSame(UserRole::Tenant, $tenant->fresh()->role);
        $this->assertSame(UserStatus::Pending, $tenant->fresh()->status);
    }

    public function test_approve_accepts_a_pending_tenant(): void
    {
        $tenant = User::factory()->pending()->create();

        $this->service->approve($tenant);

        $this->assertSame(UserStatus::Approved, $tenant->fresh()->status);
    }

    public function test_reject_rejects_a_pending_tenant(): void
    {
        $tenant = User::factory()->pending()->create();

        $this->service->reject($tenant);

        $this->assertSame(UserStatus::Rejected, $tenant->fresh()->status);
    }

    public function test_deciding_an_already_decided_tenant_throws_and_keeps_the_status(): void
    {
        $tenant = User::factory()->rejected()->create();
        $this->expectException(TenantAlreadyDecidedException::class);

        try {
            $this->service->approve($tenant);
        } finally {
            $this->assertSame(UserStatus::Rejected, $tenant->fresh()->status);
        }
    }

    public function test_overview_lists_one_status_and_counts_every_status_without_admins(): void
    {
        User::factory()->admin()->create();
        User::factory()->count(2)->pending()->create();
        User::factory()->rejected()->create();

        $overview = $this->service->overview(UserStatus::Pending);

        $this->assertSame(2, $overview['tenants']->total());
        $this->assertSame([
            UserStatus::Pending->value => 2,
            UserStatus::Approved->value => 0,
            UserStatus::Rejected->value => 1,
        ], $overview['countsByStatus']);
    }
}
