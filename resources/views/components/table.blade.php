{{--
    Tabular list: header cells in the "head" slot, <tr> rows in the default slot, optional totals <tr> in "foot".
    Never scrolls sideways: cells wrap, and below the lg breakpoint every row becomes a card where each cell
    shows its data-label on the left (give the first cell data-label="" to use it as the card title).
--}}
<div {{ $attributes->merge(['class' => 'border-t border-b border-primary-200/60']) }}>
    <table class="w-full text-base text-left
                  [&_th]:px-4 [&_th]:py-3 [&_th]:text-sm [&_th]:font-semibold [&_th]:text-primary-600 [&_th]:uppercase [&_th]:tracking-wide [&_th]:align-bottom
                  [&_td]:px-4 [&_td]:py-3.5 [&_td]:align-top
                  [&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-primary-100/50
                  max-lg:block max-lg:[&_thead]:hidden max-lg:[&_tbody]:block max-lg:[&_tfoot]:block
                  max-lg:[&_tr]:block max-lg:[&_tr]:py-3
                  max-lg:[&_td]:flow-root max-lg:[&_td]:py-1 max-lg:[&_td]:text-right max-lg:[&_td:first-child]:text-left
                  max-lg:[&_td]:before:float-left max-lg:[&_td]:before:mr-4 max-lg:[&_td]:before:text-left
                  max-lg:[&_td]:before:text-sm max-lg:[&_td]:before:font-semibold max-lg:[&_td]:before:text-primary-600
                  max-lg:[&_td]:before:content-[attr(data-label)]">
        <thead>
            <tr class="border-b border-primary-200/60">{{ $head }}</tr>
        </thead>
        <tbody class="divide-y divide-primary-200/60">
            {{ $slot }}
        </tbody>
        @isset($foot)
            <tfoot class="border-t-2 border-primary-300 bg-primary-100/60 font-bold text-primary-900">
                {{ $foot }}
            </tfoot>
        @endisset
    </table>
</div>
