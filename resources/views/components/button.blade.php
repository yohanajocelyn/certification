{{-- Renders a <button>, or an <a> when href is given; x-link-button uses this too. --}}
@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
    $variants = [
        'primary' => 'px-6 py-2.5 rounded-xl bg-primary text-white font-bold shadow-md hover:bg-secondary hover:shadow-lg transform hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary focus-visible:ring-offset-2 focus-visible:ring-offset-primary-50 disabled:opacity-50 disabled:cursor-not-allowed',
        'secondary' => 'px-5 py-2.5 rounded-xl font-semibold text-primary-700 bg-white hover:bg-primary-100/80 border border-primary-300 shadow-2xs hover:shadow-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary',
        'row' => 'px-3.5 py-1.5 rounded-lg text-sm font-bold text-primary-900 bg-white hover:bg-primary-100 border border-primary-200 shadow-2xs hover:shadow-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary',
        'row-danger' => 'px-3.5 py-1.5 rounded-lg text-sm font-bold text-rose-700 bg-white hover:bg-rose-50 border border-rose-200 shadow-2xs hover:shadow-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500',
    ];

    $classes = 'inline-flex items-center justify-center gap-1.5 text-base transition-all duration-200 cursor-pointer '
        .($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => $type, 'class' => $classes]) }}>{{ $slot }}</button>
@endif
