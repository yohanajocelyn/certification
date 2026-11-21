<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureUserHasRoleTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_URI = '/test-only/admin';

    private const TENANT_URI = '/test-only/tenant';

    protected function setUp(): void
    {
        parent::setUp();

        // Throwaway routes so the middleware is tested on its own, before real admin/tenant pages exist.
        Route::middleware(['web', 'auth', 'role:admin'])->get(self::ADMIN_URI, fn () => 'admin area');
        Route::middleware(['web', 'auth', 'role:tenant'])->get(self::TENANT_URI, fn () => 'tenant area');
    }

    public function test_admin_route_allows_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(self::ADMIN_URI)->assertOk();
    }

    public function test_admin_route_forbids_tenant(): void
    {
        $this->actingAs(User::factory()->create())->get(self::ADMIN_URI)->assertForbidden();
    }

    public function test_tenant_route_allows_tenant(): void
    {
        $this->actingAs(User::factory()->create())->get(self::TENANT_URI)->assertOk();
    }

    public function test_tenant_route_forbids_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(self::TENANT_URI)->assertForbidden();
    }

    public function test_role_route_redirects_guest_to_login(): void
    {
        $this->get(self::ADMIN_URI)->assertRedirect(route('login'));
    }
}
