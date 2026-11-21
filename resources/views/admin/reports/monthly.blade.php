<x-layout title="Laporan per Bulan">
    @include('admin.reports._heading', ['active' => 'monthly'])

    <form method="GET" action="{{ route('admin.reports.monthly') }}" class="flex flex-wrap items-end gap-4 mb-6">
        <div class="w-36">
            <x-input name="year" type="number" label="Tahun" required step="1" :value="$filters['year']" />
        </div>
        <x-button type="submit" variant="secondary">Tampilkan</x-button>
    </form>

    <x-report-table :rows="$rows" :totals="$totals" :bucket-heading="$bucketHeading" />
</x-layout>
