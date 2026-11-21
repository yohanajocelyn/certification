<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TableComponentTest extends TestCase
{
    public function test_table_never_scrolls_sideways_and_turns_rows_into_cards_below_lg(): void
    {
        $view = $this->blade('<x-table><x-slot:head><th>Kode</th></x-slot:head><tr><td data-label="Kode">C001</td></tr></x-table>');

        $view->assertDontSee('overflow-x-auto', false);
        $view->assertDontSee('whitespace-nowrap', false);
        $view->assertSee('max-lg:[&_td]:before:content-[attr(data-label)]', false);
    }

    public function test_every_table_cell_in_the_views_has_a_data_label_for_the_card_layout(): void
    {
        $cellsWithoutLabel = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            preg_match_all('/<td(?![^>]*data-label)[^>]*>/', $file->getContents(), $matches);

            foreach ($matches[0] as $cell) {
                $cellsWithoutLabel[] = $file->getRelativePathname().': '.$cell;
            }
        }

        $this->assertSame([], $cellsWithoutLabel);
    }
}
