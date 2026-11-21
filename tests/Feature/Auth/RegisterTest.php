<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_with_nik_one_digit_short_returns_validation_error(): void
    {
        $nik = str_repeat('1', config('rules.user.nik_length') - 1);

        $response = $this->post(route('register.store'), $this->validData(['nik' => $nik]));

        $response->assertSessionHasErrors('nik');
        $this->assertDatabaseCount('users', 0);
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
