@props([
    'message',
    'icon' => 'inbox',
])

<div {{ $attributes->merge(['class' => 'py-16 px-4 text-center border-t border-b border-primary-200/60']) }}>
    <x-dynamic-component :component="'heroicon-o-'.$icon" class="w-8 h-8 mx-auto mb-3 text-secondary" />
    <p class="text-lg text-primary-600 max-w-md mx-auto mb-6 leading-relaxed">{{ $message }}</p>
    {{ $slot }}
</div>
