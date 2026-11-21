{{-- Tabular list: pass header cells in the "head" slot and <tr> rows in the default slot. --}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto border-t border-b border-primary-200/60']) }}>
    <table class="w-full text-sm text-left
                  [&_th]:px-4 [&_th]:py-3 [&_th]:text-xs [&_th]:font-semibold [&_th]:text-primary-600 [&_th]:uppercase [&_th]:tracking-wide [&_th]:whitespace-nowrap
                  [&_td]:px-4 [&_td]:py-3.5 [&_td]:whitespace-nowrap
                  [&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-primary-100/50">
        <thead>
            <tr class="border-b border-primary-200/60">{{ $head }}</tr>
        </thead>
        <tbody class="divide-y divide-primary-200/60">
            {{ $slot }}
        </tbody>
    </table>
</div>
