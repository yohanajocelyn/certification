@php
    $firstExtraDay = $rental->end_date->addDay();
    $monthDays = $rental->end_date->daysInMonth;
@endphp

<x-layout title="Perpanjang Sewa {{ $rental->garage->code }}">
    <div class="max-w-5xl">
        <a href="{{ route('admin.rentals.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-secondary hover:text-primary-900 transition-colors mb-4">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Kembali ke daftar sewa hari ini
        </a>

        <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-6">Perpanjang Sewa {{ $rental->garage->code }}</h1>

        <x-rental-summary :rental="$rental" class="mb-8" />

        @if ($rental->extensions->isNotEmpty())
            <section class="mb-8" aria-labelledby="history-heading">
                <h2 id="history-heading" class="text-lg font-black text-primary-900 tracking-tight mb-3">Riwayat Perpanjangan</h2>
                <x-table>
                    <x-slot:head>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Selesai Lama → Baru</th>
                        <th scope="col">Tambahan</th>
                        <th scope="col" class="text-right">Biaya</th>
                        <th scope="col">Bukti</th>
                    </x-slot:head>
                    @foreach ($rental->extensions as $pastExtension)
                        <tr>
                            <td data-label="" class="text-primary-700 whitespace-nowrap">{{ $pastExtension->created_at->translatedFormat('d M Y') }}</td>
                            <td data-label="Selesai Lama → Baru">
                                <span class="whitespace-nowrap">{{ $pastExtension->old_end_date->translatedFormat('d M Y') }}</span> →
                                <span class="whitespace-nowrap">{{ $pastExtension->new_end_date->translatedFormat('d M Y') }}</span>
                            </td>
                            <td data-label="Tambahan" class="text-primary-700">
                                {{ $pastExtension->days }} hari
                                <span class="block text-sm text-primary-600">{{ $pastExtension->months }} bulan + {{ $pastExtension->leftoverDays() }} hari</span>
                            </td>
                            <td data-label="Biaya" class="text-right"><x-rupiah :amount="$pastExtension->additional_cost" /></td>
                            <td data-label="Bukti">
                                <a href="{{ $pastExtension->paymentProofUrl() }}" target="_blank" rel="noopener"
                                   class="text-sm font-semibold text-secondary hover:text-primary-900 underline underline-offset-4">Lihat</a>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </section>
        @endif

        <form method="GET" action="{{ route('admin.rentals.extensions.preview', $rental) }}" class="space-y-6">
            <div class="md:max-w-xs">
                <x-input name="new_end_date" type="date" label="Tanggal Selesai Baru" required
                         min="{{ $firstExtraDay->toDateString() }}" :value="$defaults['new_end_date'] ?? $firstExtraDay->toDateString()" />
                <p class="text-sm text-primary-600 mt-1.5">
                    Dihitung otomatis: tiap {{ $monthDays }} hari = 1 bulan, sisa hari dihitung harian.
                </p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-primary-200/60">
                <x-link-button :href="route('admin.rentals.index')" variant="secondary">Batal</x-link-button>
                <x-button type="submit">
                    Hitung Sisa Biaya
                    <x-heroicon-o-arrow-right class="w-4 h-4" />
                </x-button>
            </div>
        </form>
    </div>
</x-layout>
