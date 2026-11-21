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
                    <td>
                        <span class="block font-bold text-primary-900">{{ $rental->garage->code }}</span>
                        <span class="block text-xs text-primary-600">{{ $rental->garage->type->label() }}</span>
                    </td>
                    <td class="text-primary-700">{{ $rental->vehicle->displayName() }}</td>
                    <td>
                        <span class="block text-primary-900">
                            {{ $rental->start_date->translatedFormat('d M Y') }} – {{ $rental->end_date->translatedFormat('d M Y') }}
                        </span>
                        <span class="block text-xs text-primary-600">
                            {{ $rental->rental_type->label() }} · {{ $rental->durationInDays() }} hari
                            @if ($rental->extensions_count > 0)
                                · diperpanjang {{ $rental->extensions_count }}×
                            @endif
                        </span>
                    </td>
                    <td class="text-right font-semibold"><x-rupiah :amount="$rental->total_cost" /></td>
                    <td><x-status :tone="$periodStatus->tone()">{{ $periodStatus->label() }}</x-status></td>
                    <td>
                        <a href="{{ $rental->paymentProofUrl() }}" target="_blank" rel="noopener"
                           class="text-xs font-semibold text-secondary hover:text-primary-900 underline underline-offset-4">Lihat</a>
                    </td>
                </tr>
            @endforeach
        </x-table>

        {{ $rentals->links() }}
    @endif
</x-layout>
