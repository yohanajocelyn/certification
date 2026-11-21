{{-- Shared title, report switcher and period line; included by every report page. --}}
<h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-2">Laporan Sewa Garasi</h1>
<p class="text-base text-primary-700 mb-6">{{ $periodLabel }}</p>
<x-report-tabs :active="$active" class="mb-6" />
