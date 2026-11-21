<?php

namespace Tests\Feature\Admin;

use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_create_shows_rental_details_and_both_extension_types(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.rentals.extensions.create', $this->rental));

        $response->assertOk();
        $response->assertSee('Perpanjang Sewa C001');
        $response->assertSee('25 Nov 2026');
        $response->assertSee('Harian');
        $response->assertSee('Bulanan');
    }

    public function test_create_for_finished_rental_redirects_with_error(): void
    {
        $finished = Rental::factory()->daily(2)->startingOn('2026-11-01')->create();

        $response = $this->actingAs($this->admin)->get(route('admin.rentals.extensions.create', $finished));

        $response->assertRedirect(route('admin.rentals.index'));
        $response->assertSessionHas('error');
    }

    public function test_create_for_future_rental_redirects_with_error(): void
    {
        $upcoming = Rental::factory()->startingOn('2026-12-10')->create();

        $this->actingAs($this->admin)
            ->get(route('admin.rentals.extensions.create', $upcoming))
            ->assertRedirect(route('admin.rentals.index'));
    }

    public function test_preview_daily_shows_new_end_date_days_and_remaining_cost(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.rentals.extensions.preview', [
            $this->rental, 'extension_type' => RentalType::Daily->value, 'new_end_date' => '2026-11-30',
        ]));

        $response->assertOk();
        $response->assertSee('30 Nov 2026');
        $response->assertSee('5 hari');
        $response->assertSee('Rp 50.000');
    }

    public function test_preview_monthly_adds_end_month_days_per_month_at_monthly_price(): void
    {
        // Arrange: on 5 Jan 2027, a rental running 1 to 20 Jan 2027
        $this->travelTo('2027-01-05');
        $rental = Rental::factory()->for(Garage::factory()->car()->create(['daily_price' => 10_000]))
            ->daily(20)->startingOn('2027-01-01')->create();

        // Act
        $extension = $this->actingAs($this->admin)->get(route('admin.rentals.extensions.preview', [
            $rental, 'extension_type' => RentalType::Monthly->value, 'months' => 2,
        ]))->viewData('extension');

        // Assert: 2 x 31 days (January), 2 x 25 x 10.000
        $this->assertSame(62, $extension->days);
        $this->assertSame('2027-03-23', $extension->new_end_date->toDateString());
        $this->assertEquals(500_000, $extension->additional_cost);
    }

    public function test_preview_with_new_end_date_on_current_end_date_returns_to_step_one_with_error(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.rentals.extensions.preview', [
            $this->rental, 'extension_type' => RentalType::Daily->value, 'new_end_date' => '2026-11-25',
        ]));

        $response->assertRedirect(route('admin.rentals.extensions.create', $this->rental));
        $response->assertSessionHasErrors('new_end_date');
    }

    public function test_preview_with_zero_months_returns_validation_error(): void
    {
        $this->actingAs($this->admin)->get(route('admin.rentals.extensions.preview', [
            $this->rental, 'extension_type' => RentalType::Monthly->value, 'months' => 0,
        ]))->assertSessionHasErrors('months');
    }

    public function test_preview_has_no_upper_day_limit(): void
    {
        $this->actingAs($this->admin)->get(route('admin.rentals.extensions.preview', [
            $this->rental, 'extension_type' => RentalType::Daily->value, 'new_end_date' => '2027-11-25',
        ]))->assertOk()->assertSessionHasNoErrors();
    }

    public function test_preview_overlapping_next_booking_of_garage_returns_validation_error(): void
    {
        Rental::factory()->for($this->garage)->daily(3)->startingOn('2026-11-28')->create();

        $response = $this->actingAs($this->admin)->get(route('admin.rentals.extensions.preview', [
            $this->rental, 'extension_type' => RentalType::Daily->value, 'new_end_date' => '2026-11-28',
        ]));

        $response->assertSessionHasErrors(['new_end_date' => 'Garasi C001 sudah dipesan mulai 28 Nov 2026. Perpanjangan paling lama sampai 27 Nov 2026.']);
    }

    public function test_preview_ending_the_day_before_next_booking_succeeds(): void
    {
        Rental::factory()->for($this->garage)->daily(3)->startingOn('2026-11-28')->create();

        $this->actingAs($this->admin)->get(route('admin.rentals.extensions.preview', [
            $this->rental, 'extension_type' => RentalType::Daily->value, 'new_end_date' => '2026-11-27',
        ]))->assertOk();
    }

    public function test_preview_when_vehicle_is_booked_in_another_garage_returns_validation_error(): void
    {
        Rental::factory()->for($this->rental->vehicle, 'vehicle')->daily(2)->startingOn('2026-11-27')->create();

        $this->actingAs($this->admin)->get(route('admin.rentals.extensions.preview', [
            $this->rental, 'extension_type' => RentalType::Daily->value, 'new_end_date' => '2026-11-30',
        ]))->assertSessionHasErrors('new_end_date');
    }

    public function test_store_daily_saves_extension_and_moves_rental_end_date(): void
    {
        // Act
        $response = $this->actingAs($this->admin)->post(route('admin.rentals.extensions.store', $this->rental), [
            'extension_type' => RentalType::Daily->value,
            'new_end_date' => '2026-11-30',
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        // Assert
        $response->assertRedirect(route('admin.rentals.index'));
        $response->assertSessionHas('success', 'Sewa garasi C001 diperpanjang sampai 30 Nov 2026.');
        $extension = RentalExtension::sole();
        $this->assertSame('2026-11-25', $extension->old_end_date->toDateString());
        $this->assertSame(5, $extension->days);
        $this->assertEquals(50_000, $extension->additional_cost);
        Storage::disk(Rental::PAYMENT_PROOF_DISK)->assertExists($extension->payment_proof);
        $this->assertSame('2026-11-30', $this->rental->fresh()->end_date->toDateString());
        $this->assertTrue($this->garage->isUsedOn(CarbonImmutable::parse('2026-11-30')));
    }

    public function test_store_monthly_saves_months_and_days(): void
    {
        $this->actingAs($this->admin)->post(route('admin.rentals.extensions.store', $this->rental), [
            'extension_type' => RentalType::Monthly->value,
            'months' => 1,
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        // Ends in November (30 days): 25 Nov + 30 = 25 Dec, 25 x 10.000
        $extension = RentalExtension::sole();
        $this->assertSame(1, $extension->months);
        $this->assertSame(30, $extension->days);
        $this->assertSame('2026-12-25', $this->rental->fresh()->end_date->toDateString());
        $this->assertEquals(250_000, $extension->additional_cost);
    }

    public function test_store_without_payment_proof_keeps_rental_unchanged(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.rentals.extensions.store', $this->rental), [
            'extension_type' => RentalType::Daily->value,
            'new_end_date' => '2026-11-30',
        ]);

        $response->assertSessionHasErrors('payment_proof');
        $this->assertDatabaseCount('rental_extensions', 0);
        $this->assertSame('2026-11-25', $this->rental->fresh()->end_date->toDateString());
    }

    public function test_store_overlapping_next_booking_saves_nothing(): void
    {
        Rental::factory()->for($this->garage)->daily(3)->startingOn('2026-11-28')->create();

        $response = $this->actingAs($this->admin)->post(route('admin.rentals.extensions.store', $this->rental), [
            'extension_type' => RentalType::Daily->value,
            'new_end_date' => '2026-11-29',
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $response->assertSessionHasErrors('new_end_date');
        $this->assertDatabaseCount('rental_extensions', 0);
    }

    public function test_store_for_finished_rental_saves_nothing(): void
    {
        $finished = Rental::factory()->daily(2)->startingOn('2026-11-01')->create();

        $response = $this->actingAs($this->admin)->post(route('admin.rentals.extensions.store', $finished), [
            'extension_type' => RentalType::Daily->value,
            'new_end_date' => '2026-11-30',
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $response->assertRedirect(route('admin.rentals.index'));
        $this->assertDatabaseCount('rental_extensions', 0);
    }

    public function test_store_is_forbidden_for_tenant(): void
    {
        $response = $this->actingAs($this->rental->vehicle->user)->post(route('admin.rentals.extensions.store', $this->rental), [
            'extension_type' => RentalType::Daily->value,
            'new_end_date' => '2026-11-30',
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('rental_extensions', 0);
    }

    public function test_extension_shows_in_history_and_in_tenant_rental_list(): void
    {
        $this->actingAs($this->admin)->post(route('admin.rentals.extensions.store', $this->rental), [
            'extension_type' => RentalType::Daily->value,
            'new_end_date' => '2026-11-30',
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $this->actingAs($this->admin)->get(route('admin.rentals.extensions.create', $this->rental))
            ->assertSee('Riwayat Perpanjangan');
        $this->actingAs($this->rental->vehicle->user)->get(route('rentals.index'))
            ->assertSee('30 Nov 2026')
            ->assertSee('diperpanjang 1×')
            ->assertSeeTextInOrder(['+', 'Rp 50.000', 'perpanjangan']);
    }
}
