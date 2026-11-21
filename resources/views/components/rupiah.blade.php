{{-- Rupiah amount in Indonesian format: Rp 25.000, or Rp 1.500,50 when there are cents. --}}
@props(['amount'])

@php
    $value = (float) $amount;
    $decimals = floor($value) === $value ? 0 : 2;
@endphp

<span {{ $attributes }}>Rp {{ number_format($value, $decimals, ',', '.') }}</span>
