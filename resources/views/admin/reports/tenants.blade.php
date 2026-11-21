<x-layout title="Laporan per Penyewa">
    @include('admin.reports._heading', ['active' => 'tenants'])

    <form method="GET" action="{{ route('admin.reports.tenants') }}" class="flex flex-wrap items-end gap-4 mb-6">
        <div class="w-48">
            <x-input name="from" type="date" label="Dari Tanggal" required :value="$filters['from']" />
        </div>
        <div class="w-48">
            <x-input name="to" type="date" label="Sampai Tanggal" required :value="$filters['to']" />
        </div>
        <x-button type="submit" variant="secondary">Tampilkan</x-button>
    </form>

    @if ($rows === [])
        <x-empty-state icon="user-group" message="Belum ada penyewa yang menyewa atau memperpanjang pada periode ini." />
    @else
        <x-report-table :rows="$rows" :totals="$totals" :bucket-heading="$bucketHeading" />
    @endif
</x-layout>
