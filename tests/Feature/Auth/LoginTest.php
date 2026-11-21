<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'password';

    public function test_login_page_renders_for_guest(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Masuk ke akun Anda');
    }

    public function test_login_with_valid_credentials_for_approved_tenant_redirects_home(): void
    {
        // Arrange
        $tenant = User::factory()->create();

        // Act
        $response = $this->post(route('login.store'), ['email' => $tenant->email, 'password' => self::PASSWORD]);

        // Assert
        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($tenant);
    }

    public function test_login_for_admin_redirects_home(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post(route('login.store'), ['email' => $admin->email, 'password' => self::PASSWORD]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_with_wrong_password_returns_error(): void
    {
        $tenant = User::factory()->create();

        $response = $this->from(route('login'))
            ->post(route('login.store'), ['email' => $tenant->email, 'password' => 'wrong-password']);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_login_with_unknown_email_returns_error(): void
    {
        $response = $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => self::PASSWORD]);

        $response->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_login_for_pending_tenant_is_refused_with_pending_message(): void
    {
        $tenant = User::factory()->pending()->create();

        $response = $this->post(route('login.store'), ['email' => $tenant->email, 'password' => self::PASSWORD]);

        $response->assertSessionHasErrors(['email' => __('auth.status.pending')]);
        $this->assertGuest();
    }

    public function test_login_for_rejected_tenant_is_refused_with_rejected_message(): void
    {
        $tenant = User::factory()->rejected()->create();

        $response = $this->post(route('login.store'), ['email' => $tenant->email, 'password' => self::PASSWORD]);

        $response->assertSessionHasErrors(['email' => __('auth.status.rejected')]);
        $this->assertGuest();
    }

    public function test_login_with_wrong_password_for_pending_tenant_does_not_reveal_status(): void
    {
        $tenant = User::factory()->pending()->create();

        $response = $this->post(route('login.store'), ['email' => $tenant->email, 'password' => 'wrong-password']);

        $response->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_login_with_blank_fields_returns_validation_errors(): void
    {
        $response = $this->post(route('login.store'), ['email' => '', 'password' => '']);

        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_logged_in_user_visiting_login_is_redirected_home(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }
}
