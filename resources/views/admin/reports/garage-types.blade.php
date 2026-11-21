<x-layout title="Laporan per Tipe Garasi">
    @include('admin.reports._heading', ['active' => 'garage-types'])

    <form method="GET" action="{{ route('admin.reports.garage-types') }}" class="flex flex-wrap items-end gap-4 mb-6">
        <div class="w-48">
            <x-input name="from" type="date" label="Dari Tanggal" required :value="$filters['from']" />
        </div>
        <div class="w-48">
            <x-input name="to" type="date" label="Sampai Tanggal" required :value="$filters['to']" />
        </div>
        <x-button type="submit" variant="secondary">Tampilkan</x-button>
    </form>

    <x-report-table :rows="$rows" :totals="$totals" :bucket-heading="$bucketHeading" />
</x-layout>
