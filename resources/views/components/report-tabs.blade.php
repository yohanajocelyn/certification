{{-- Switch between the four rental reports. --}}
@props(['active'])

@php
    $reports = [
        'monthly' => 'Per Bulan',
        'daily' => 'Per Hari',
        'garage-types' => 'Per Tipe Garasi',
        'tenants' => 'Per Penyewa',
    ];
    $chipClass = 'px-3.5 py-1.5 rounded-lg text-sm font-semibold border transition-colors';
@endphp

<nav aria-label="Jenis laporan" {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
    @foreach ($reports as $report => $label)
        <a href="{{ route('admin.reports.'.$report) }}"
           @class([
               $chipClass,
               'bg-primary text-white border-primary' => $active === $report,
               'bg-white text-primary-800 border-primary-200 hover:bg-primary-100' => $active !== $report,
           ])
           @if ($active === $report) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
