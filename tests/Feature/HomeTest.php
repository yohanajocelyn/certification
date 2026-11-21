<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_visiting_home_is_redirected_to_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_home_greets_logged_in_tenant_with_name_and_role(): void
    {
        $tenant = User::factory()->create(['name' => 'Budi Santoso']);

        $response = $this->actingAs($tenant)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Selamat datang, Budi Santoso');
        $response->assertSee('Penyewa');
    }

    public function test_home_redirects_admin_to_garage_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('home'))->assertRedirect(route('admin.garages.index'));
    }
}
