@props([
    'type' => 'success',
    'message' => null,
])

@if ($message)
    @php
        $isSuccess = $type === 'success';
    @endphp

    <div role="{{ $isSuccess ? 'status' : 'alert' }}"
         {{ $attributes->class([
             'p-4 rounded-2xl border text-base shadow-2xs flex items-start gap-3',
             'bg-emerald-50 border-emerald-200 text-emerald-800' => $isSuccess,
             'bg-rose-50 border-rose-200 text-rose-800' => ! $isSuccess,
         ]) }}>
        @if ($isSuccess)
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0 text-emerald-600" />
        @else
            <x-heroicon-o-exclamation-circle class="w-5 h-5 shrink-0 text-rose-600" />
        @endif
        <p @class(['font-semibold' => $isSuccess, 'font-bold' => ! $isSuccess])>{{ $message }}</p>
    </div>
@endif
