<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

class ShowWhenComponentTest extends TestCase
{
    public function test_section_is_rendered_hidden_when_current_value_does_not_match(): void
    {
        $view = $this->blade('<x-show-when field="vehicle_id" :values="[\'new\']" current="3">box</x-show-when>');

        $this->assertMatchesRegularExpression('/data-show-when="vehicle_id:new"\s+hidden/', (string) $view);
    }

    public function test_section_is_rendered_visible_when_current_value_matches(): void
    {
        $view = $this->blade('<x-show-when field="vehicle_type" :values="[\'car\', \'electric_car\']" current="electric_car">plate</x-show-when>');

        $view->assertSee('data-show-when="vehicle_type:car,electric_car"', false);
        $view->assertDontSee('hidden', false);
    }
}
