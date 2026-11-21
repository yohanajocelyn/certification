<?php

namespace Tests\Feature\Admin;

use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RentalExtensionControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-21';

    private User $admin;

    private Garage $garage;

    /** Daily rental from 21 to 25 Nov 2026 at 10.000 per day. */
    private Rental $rental;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::TODAY);
        Storage::fake(Rental::PAYMENT_PROOF_DISK);
        $this->admin = User::factory()->admin()->create();
        $this->garage = Garage::factory()->car()->create(['code' => 'C001', 'daily_price' => 10_000]);
        $this->rental = Rental::factory()->for($this->garage)->daily(5)->startingOn(self::TODAY)->create();
    }

    public function test_preview_with_new_end_date_on_current_end_date_returns_to_step_one_with_error(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.rentals.extensions.preview', [
            $this->rental, 'extension_type' => RentalType::Daily->value, 'new_end_date' => '2026-11-25',
        ]));

        $response->assertRedirect(route('admin.rentals.extensions.create', $this->rental));
        $response->assertSessionHasErrors('new_end_date');
    }

    public function test_preview_ending_the_day_before_next_booking_succeeds(): void
    {
        Rental::factory()->for($this->garage)->daily(3)->startingOn('2026-11-28')->create();

        $this->actingAs($this->admin)->get(route('admin.rentals.extensions.preview', [
            $this->rental, 'extension_type' => RentalType::Daily->value, 'new_end_date' => '2026-11-27',
        ]))->assertOk();
    }
}
