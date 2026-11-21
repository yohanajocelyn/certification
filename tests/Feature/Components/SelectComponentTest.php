<?php

namespace Tests\Feature\Components;

use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class SelectComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        view()->share('errors', new ViewErrorBag);
    }

    public function test_select_hides_browser_arrow_and_pads_both_sides_evenly(): void
    {
        $view = $this->blade('<x-select name="vehicle_id" label="Kendaraan" :options="$options" />', [
            'options' => [3 => 'Mobil · Toyota Avanza'],
        ]);

        // Text starts 16px from the left; the chevron sits 16px from the right with room before it.
        $view->assertSee('appearance-none', false);
        $view->assertSee('pl-4 pr-11', false);
        $view->assertSee('absolute right-4', false);
    }

    public function test_select_marks_the_given_value_as_selected(): void
    {
        $view = $this->blade('<x-select name="month" label="Bulan" :options="$options" value="2" />', [
            'options' => [1 => 'Januari', 2 => 'Februari'],
        ]);

        $view->assertSee('<option value="2" selected>Februari</option>', false);
    }
}
