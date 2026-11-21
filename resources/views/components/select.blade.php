{{-- Label + select + validation message. options: value => label. --}}
@props([
    'name',
    'label',
    'options' => [],
    'value' => null,
    'required' => false,
    'placeholder' => null,
])

@php
    $id = $attributes->get('id', $name);
    $errorId = $id.'-error';
    $hasError = $errors->has($name);
    $selected = (string) old($name, $value);
@endphp

<div>
    <label for="{{ $id }}" class="block font-semibold text-base text-primary-800 mb-2">
        {{ $label }}
        @if ($required)
            <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
        @endif
    </label>

    {{-- appearance-none hides the browser arrow (it sat inside the right padding, so the sides looked uneven);
         the chevron is drawn 16px from the right edge to match the 16px left padding. --}}
    <div class="relative">
        <select id="{{ $id }}"
                name="{{ $name }}"
                @required($required)
                @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
                {{ $attributes->except('id')->class([
                    'w-full appearance-none border rounded-xl shadow-sm bg-white/80 backdrop-blur-sm pl-4 pr-11 py-3 text-primary-900 truncate hover:bg-white focus:bg-white focus:border-secondary focus:outline-none focus:ring-2 focus:ring-secondary/50 transition-all duration-300 cursor-pointer',
                    'border-primary-300' => ! $hasError,
                    'border-rose-400' => $hasError,
                ]) }}>
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
        <x-heroicon-o-chevron-down class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-primary-500" />
    </div>

    @error($name)
        <p id="{{ $errorId }}" class="text-sm text-red-600 mt-1.5">{{ $message }}</p>
    @enderror
</div>
