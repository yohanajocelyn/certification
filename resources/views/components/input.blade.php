{{--
    Label + input + validation message. Passwords are never refilled.
    Read-only fields are styled on the readonly attribute, not :read-only (file inputs match :read-only too),
    and keep the same look on hover and focus.
--}}
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
    <label for="{{ $id }}" class="block font-semibold text-base text-primary-800 mb-2">
        {{ $label }}
        @if ($required)
            <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
        @endif
    </label>

    <input id="{{ $id }}"
           name="{{ $name }}"
           type="{{ $type }}"
           @unless (in_array($type, ['password', 'file'], true)) value="{{ old($name, $value) }}" @endunless
           @required($required)
           @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
           {{ $attributes->except('id')->class([
               'w-full border rounded-xl shadow-sm bg-white/80 backdrop-blur-sm px-4 py-3 text-primary-900 placeholder-primary-400 hover:bg-white focus:bg-white focus:border-secondary focus:outline-none focus:ring-2 focus:ring-secondary/50 transition-all duration-300',
               '[&[readonly]]:bg-primary-100/70 [&[readonly]]:text-primary-700 [&[readonly]]:cursor-not-allowed',
               '[&[readonly]]:hover:bg-primary-100/70 [&[readonly]]:focus:bg-primary-100/70',
               '[&[readonly]]:focus:border-primary-300 [&[readonly]]:focus:ring-0',
               'file:mr-4 file:rounded-lg file:border-0 file:bg-primary-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-800 hover:file:bg-primary-200' => $type === 'file',
               'border-primary-300' => ! $hasError,
               'border-rose-400' => $hasError,
           ]) }}>

    @error($name)
        <p id="{{ $errorId }}" class="text-sm text-red-600 mt-1.5">{{ $message }}</p>
    @enderror
</div>
