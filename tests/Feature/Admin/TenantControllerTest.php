<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Admin Garasi']);
    }

    public function test_index_shows_pending_tenants_by_default(): void
    {
        User::factory()->pending()->create(['name' => 'Budi Pending']);
        User::factory()->create(['name' => 'Sari Approved']);

        $response = $this->actingAs($this->admin)->get(route('admin.tenants.index'));

        $response->assertOk();
        $response->assertSee('Budi Pending');
        $response->assertDontSee('Sari Approved');
    }

    public function test_index_filtered_by_approved_shows_only_approved_tenants(): void
    {
        User::factory()->pending()->create(['name' => 'Budi Pending']);
        User::factory()->create(['name' => 'Sari Approved']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.tenants.index', ['status' => UserStatus::Approved->value]));

        $response->assertSee('Sari Approved');
        $response->assertDontSee('Budi Pending');
    }

    public function test_index_never_lists_admin_accounts(): void
    {
        $tenants = $this->actingAs($this->admin)
            ->get(route('admin.tenants.index', ['status' => UserStatus::Approved->value]))
            ->viewData('tenants');

        $this->assertFalse($tenants->contains($this->admin));
    }

    public function test_index_shows_count_per_status(): void
    {
        User::factory()->count(2)->pending()->create();
        User::factory()->rejected()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.tenants.index'));

        $response->assertSee('Menunggu Verifikasi (2)');
        $response->assertSee('Diterima (0)');
        $response->assertSee('Ditolak (1)');
    }

    public function test_index_with_no_pending_tenants_shows_empty_state(): void
    {
        $this->actingAs($this->admin)->get(route('admin.tenants.index'))
            ->assertOk()
            ->assertSee('Tidak ada penyewa dengan status menunggu verifikasi.');
    }

    public function test_index_with_unknown_status_filter_returns_validation_error(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.tenants.index', ['status' => 'banned']))
            ->assertSessionHasErrors('status');
    }

    public function test_index_is_forbidden_for_tenant(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.tenants.index'))->assertForbidden();
    }

    public function test_update_approves_pending_tenant(): void
    {
        // Arrange
        $tenant = User::factory()->pending()->create(['name' => 'Budi Santoso']);

        // Act
        $response = $this->actingAs($this->admin)
            ->from(route('admin.tenants.index'))
            ->patch(route('admin.tenants.update', $tenant), ['status' => UserStatus::Approved->value]);

        // Assert
        $response->assertRedirect(route('admin.tenants.index'));
        $response->assertSessionHas('success', 'Pendaftaran Budi Santoso diterima.');
        $this->assertSame(UserStatus::Approved, $tenant->fresh()->status);
    }

    public function test_update_rejects_pending_tenant(): void
    {
        $tenant = User::factory()->pending()->create(['name' => 'Budi Santoso']);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tenants.update', $tenant), ['status' => UserStatus::Rejected->value]);

        $response->assertSessionHas('success', 'Pendaftaran Budi Santoso ditolak.');
        $this->assertSame(UserStatus::Rejected, $tenant->fresh()->status);
    }

    public function test_approved_tenant_can_log_in_afterwards(): void
    {
        $tenant = User::factory()->pending()->create();
        $this->actingAs($this->admin)
            ->patch(route('admin.tenants.update', $tenant), ['status' => UserStatus::Approved->value]);
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => $tenant->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($tenant);
    }

    public function test_update_on_already_decided_tenant_keeps_status_and_shows_error(): void
    {
        $tenant = User::factory()->rejected()->create();

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tenants.update', $tenant), ['status' => UserStatus::Approved->value]);

        $response->assertSessionHas('error');
        $this->assertSame(UserStatus::Rejected, $tenant->fresh()->status);
    }

    public function test_update_back_to_pending_returns_validation_error(): void
    {
        $tenant = User::factory()->pending()->create();

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.tenants.update', $tenant), ['status' => UserStatus::Pending->value]);

        $response->assertSessionHasErrors('status');
    }

    public function test_update_without_status_returns_validation_error(): void
    {
        $tenant = User::factory()->pending()->create();

        $response = $this->actingAs($this->admin)->patch(route('admin.tenants.update', $tenant), []);

        $response->assertSessionHasErrors('status');
        $this->assertSame(UserStatus::Pending, $tenant->fresh()->status);
    }

    public function test_update_targeting_an_admin_returns_not_found(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.tenants.update', $otherAdmin), ['status' => UserStatus::Rejected->value])
            ->assertNotFound();
    }

    public function test_update_unknown_tenant_returns_not_found(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.tenants.update', 999_999), ['status' => UserStatus::Approved->value])
            ->assertNotFound();
    }

    public function test_update_is_forbidden_for_tenant(): void
    {
        $pending = User::factory()->pending()->create();

        $response = $this->actingAs(User::factory()->create())
            ->patch(route('admin.tenants.update', $pending), ['status' => UserStatus::Approved->value]);

        $response->assertForbidden();
        $this->assertSame(UserStatus::Pending, $pending->fresh()->status);
    }
}
