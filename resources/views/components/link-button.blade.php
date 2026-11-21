@props([
    'href',
    'variant' => 'primary',
])

<x-button :href="$href" :variant="$variant" {{ $attributes }}>{{ $slot }}</x-button>
