<x-layout title="Sewa Saya">
    <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-6">Sewa Saya</h1>

    <div class="mb-6">
        <x-link-button :href="route('rentals.create')">
            <x-heroicon-o-plus class="w-4 h-4 stroke-[2.5]" />
            Sewa Garasi Baru
        </x-link-button>
    </div>

    @if ($rentals->isEmpty())
        <x-empty-state icon="home-modern" message="Anda belum pernah menyewa garasi." />
    @else
        <x-table>
            <x-slot:head>
                <th scope="col">Garasi</th>
                <th scope="col">Kendaraan</th>
                <th scope="col">Periode</th>
                <th scope="col" class="text-right">Total</th>
                <th scope="col">Status</th>
                <th scope="col">Bukti</th>
            </x-slot:head>

            @foreach ($rentals as $rental)
                @php $periodStatus = $rental->periodStatusOn($today); @endphp
                <tr>
                    <td data-label="">
                        <span class="block font-bold text-primary-900">{{ $rental->garage->code }}</span>
                        <span class="block text-sm text-primary-600">{{ $rental->garage->type->label() }}</span>
                    </td>
                    <td data-label="Kendaraan" class="text-primary-700">{{ $rental->vehicle->displayName() }}</td>
                    <td data-label="Periode">
                        <span class="block text-primary-900">
                            <span class="whitespace-nowrap">{{ $rental->start_date->translatedFormat('d M Y') }}</span> –
                            <span class="whitespace-nowrap">{{ $rental->end_date->translatedFormat('d M Y') }}</span>
                        </span>
                        <span class="block text-sm text-primary-600">
                            {{ $rental->rental_type->label() }} · {{ $rental->durationInDays() }} hari
                            @if ($rental->extensions_count > 0)
                                · diperpanjang {{ $rental->extensions_count }}×
                            @endif
                        </span>
                    </td>
                    <td data-label="Total" class="text-right">
                        <x-rupiah :amount="$rental->total_cost" class="block font-semibold" />
                        @if ($rental->extensions_count > 0)
                            <span class="block text-sm text-primary-600">
                                + <x-rupiah :amount="$rental->extensions_sum_additional_cost" /> perpanjangan
                            </span>
                        @endif
                    </td>
                    <td data-label="Status"><x-status :tone="$periodStatus->tone()">{{ $periodStatus->label() }}</x-status></td>
                    <td data-label="Bukti">
                        <a href="{{ $rental->paymentProofUrl() }}" target="_blank" rel="noopener"
                           class="text-sm font-semibold text-secondary hover:text-primary-900 underline underline-offset-4">Lihat</a>
                    </td>
                </tr>
            @endforeach
        </x-table>

        {{ $rentals->links() }}
    @endif
</x-layout>
