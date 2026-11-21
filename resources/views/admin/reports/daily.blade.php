<x-layout title="Laporan per Hari">
    @include('admin.reports._heading', ['active' => 'daily'])

    <form method="GET" action="{{ route('admin.reports.daily') }}" class="flex flex-wrap items-end gap-4 mb-6">
        <div class="w-48">
            <x-select name="month" label="Bulan" required :options="$monthOptions" :value="$filters['month']" />
        </div>
        <div class="w-36">
            <x-input name="year" type="number" label="Tahun" required step="1" :value="$filters['year']" />
        </div>
        <x-button type="submit" variant="secondary">Tampilkan</x-button>
    </form>

    <x-report-table :rows="$rows" :totals="$totals" :bucket-heading="$bucketHeading" />
</x-layout>
