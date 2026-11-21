<?php

namespace Tests\Feature\Components;

use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class InputComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        view()->share('errors', new ViewErrorBag);
    }

    public function test_read_only_style_keeps_its_colour_on_hover_and_focus(): void
    {
        $view = $this->blade('<x-input name="end_date_display" label="Tanggal Selesai Sewa" readonly />');

        // Blade escapes the class attribute (& becomes &amp;), so compare the escaped form.
        $view->assertSee('[&[readonly]]:hover:bg-primary-100/70');
        $view->assertSee('[&[readonly]]:focus:bg-primary-100/70');
    }

    public function test_read_only_style_targets_the_attribute_so_file_inputs_look_normal(): void
    {
        // :read-only also matches file inputs, which made the upload field look disabled.
        $view = $this->blade('<x-input name="payment_proof" type="file" label="Bukti" />');

        $view->assertDontSee('read-only:', false);
    }
}
