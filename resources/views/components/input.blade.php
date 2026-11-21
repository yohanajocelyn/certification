{{-- Label + input + validation message. Passwords are never refilled. --}}
@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
])

@php
    $id = $attributes->get('id', $name);
    $errorId = $id.'-error';
    $hasError = $errors->has($name);
@endphp

<div>
    <label for="{{ $id }}" class="block font-semibold text-sm text-primary-800 mb-2">
        {{ $label }}
        @if ($required)
            <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
        @endif
    </label>

    <input id="{{ $id }}"
           name="{{ $name }}"
           type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
           @required($required)
           @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
           {{ $attributes->except('id')->class([
               'w-full border rounded-xl shadow-sm bg-white/80 backdrop-blur-sm px-4 py-3 text-primary-900 placeholder-primary-400 hover:bg-white focus:bg-white focus:border-secondary focus:outline-none focus:ring-2 focus:ring-secondary/50 transition-all duration-300',
               'border-primary-300' => ! $hasError,
               'border-rose-400' => $hasError,
           ]) }}>

    @error($name)
        <p id="{{ $errorId }}" class="text-sm text-red-600 mt-1.5">{{ $message }}</p>
    @enderror
</div>
