{{-- Coloured dot + label. tone: success (green), danger (red), warning (amber), neutral (grey-brown). --}}
@props(['tone' => 'neutral'])

@php
    $dotColors = [
        'success' => 'bg-emerald-500',
        'danger' => 'bg-rose-500',
        'warning' => 'bg-amber-500',
        'neutral' => 'bg-primary-400',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 font-semibold']) }}>
    <span class="inline-block w-2.5 h-2.5 rounded-full shadow-2xs {{ $dotColors[$tone] ?? $dotColors['neutral'] }}"></span>
    {{ $slot }}
</span>
