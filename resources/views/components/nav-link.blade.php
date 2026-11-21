@props([
    'href',
    'active' => false,
])

<a href="{{ $href }}"
   @if ($active) aria-current="page" @endif
   {{ $attributes->class([
       'text-base tracking-wide py-1 transition-all duration-200',
       'text-primary-900 font-bold underline decoration-2 underline-offset-8' => $active,
       'text-primary-800/80 font-medium hover:text-primary-900 hover:underline hover:decoration-primary-400 hover:underline-offset-8' => ! $active,
   ]) }}>
    {{ $slot }}
</a>
