<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_renders_for_guest(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Daftar sebagai Penyewa');
    }

    public function test_register_with_valid_data_creates_pending_tenant(): void
    {
        // Act
        $response = $this->post(route('register.store'), $this->validData());

        // Assert
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'nik' => '3174012345678901',
            'email' => 'budi@example.com',
            'role' => UserRole::Tenant->value,
            'status' => UserStatus::Pending->value,
        ]);
        $this->assertGuest();
    }

    public function test_register_ignores_role_and_status_sent_in_the_request(): void
    {
        $this->post(route('register.store'), $this->validData(['role' => 'admin', 'status' => 'approved']));

        $user = User::firstWhere('email', 'budi@example.com');
        $this->assertSame(UserRole::Tenant, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
    }

    public function test_register_with_nik_one_digit_short_returns_validation_error(): void
    {
        $nik = str_repeat('1', User::NIK_LENGTH - 1);

        $response = $this->post(route('register.store'), $this->validData(['nik' => $nik]));

        $response->assertSessionHasErrors('nik');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_with_nik_one_digit_long_returns_validation_error(): void
    {
        $nik = str_repeat('1', User::NIK_LENGTH + 1);

        $response = $this->post(route('register.store'), $this->validData(['nik' => $nik]));

        $response->assertSessionHasErrors('nik');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_with_letters_in_nik_returns_validation_error(): void
    {
        $response = $this->post(route('register.store'), $this->validData(['nik' => '31740123456789AB']));

        $response->assertSessionHasErrors('nik');
    }

    public function test_register_with_existing_nik_returns_validation_error(): void
    {
        User::factory()->create(['nik' => '3174012345678901']);

        $response = $this->post(route('register.store'), $this->validData());

        $response->assertSessionHasErrors('nik');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_register_with_existing_email_returns_validation_error(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $response = $this->post(route('register.store'), $this->validData());

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_register_with_name_at_max_length_succeeds(): void
    {
        $name = str_repeat('a', User::NAME_MAX_LENGTH);

        $response = $this->post(route('register.store'), $this->validData(['name' => $name]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['name' => $name]);
    }

    public function test_register_with_name_over_max_length_returns_validation_error(): void
    {
        $name = str_repeat('a', User::NAME_MAX_LENGTH + 1);

        $response = $this->post(route('register.store'), $this->validData(['name' => $name]));

        $response->assertSessionHasErrors('name');
    }

    public function test_register_with_letters_in_phone_returns_validation_error(): void
    {
        $response = $this->post(route('register.store'), $this->validData(['phone' => '08abc']));

        $response->assertSessionHasErrors('phone');
    }

    public function test_register_with_unconfirmed_password_returns_validation_error(): void
    {
        $response = $this->post(route('register.store'), $this->validData(['password_confirmation' => 'different-password']));

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_with_password_below_min_length_returns_validation_error(): void
    {
        $password = str_repeat('a', User::PASSWORD_MIN_LENGTH - 1);

        $response = $this->post(route('register.store'), $this->validData([
            'password' => $password,
            'password_confirmation' => $password,
        ]));

        $response->assertSessionHasErrors('password');
    }

    public function test_register_with_missing_fields_returns_errors_for_each(): void
    {
        $response = $this->post(route('register.store'), []);

        $response->assertSessionHasErrors(['nik', 'name', 'phone', 'email', 'password']);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'nik' => '3174012345678901',
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ], $overrides);
    }
}
