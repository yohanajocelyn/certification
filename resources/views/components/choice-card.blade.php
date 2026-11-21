{{-- One radio option drawn as a card; group several inside a <fieldset>. --}}
@props([
    'name',
    'value',
    'label',
    'description' => null,
    'checked' => false,
])

<label class="relative flex items-start justify-between gap-3 p-4 min-h-[48px] rounded-xl border border-primary-300/80 bg-white/70 cursor-pointer transition-all hover:bg-white has-checked:border-secondary has-checked:bg-primary-100/50 has-checked:ring-2 has-checked:ring-secondary/40">
    <span class="min-w-0">
        <span class="block text-base font-bold text-primary-900">{{ $label }}</span>
        @if ($description)
            <span class="block text-sm text-primary-600 mt-1">{{ $description }}</span>
        @endif
        {{ $slot }}
    </span>
    <input type="radio"
           name="{{ $name }}"
           value="{{ $value }}"
           @checked($checked)
           {{ $attributes->merge(['class' => 'mt-0.5 w-4 h-4 shrink-0 accent-secondary cursor-pointer']) }}>
</label>
