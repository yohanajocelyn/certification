<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'password';

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

    public function test_login_with_wrong_password_returns_error(): void
    {
        $tenant = User::factory()->create();

        $response = $this->from(route('login'))
            ->post(route('login.store'), ['email' => $tenant->email, 'password' => 'wrong-password']);

        $response->assertRedirect(route('login'));
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
}
