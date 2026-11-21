{{--
    A section shown only while another field of the form has one of the given values.
    The server already renders it hidden when the condition is false, so it never flashes on page load;
    app.js then shows/hides it live and disables hidden inputs. Display only: the server validates everything.
--}}
@props([
    'field',
    'values',
    'current' => null,
])

@php
    $values = array_map('strval', (array) $values);
    $isShown = in_array((string) $current, $values, true);
@endphp

<div data-show-when="{{ $field }}:{{ implode(',', $values) }}" @unless ($isShown) hidden @endunless {{ $attributes }}>
    {{ $slot }}
</div>
