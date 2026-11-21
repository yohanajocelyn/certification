<x-layout title="Perpanjang Sewa">
    <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-1">Perpanjang Sewa</h1>
    <p class="text-base text-primary-600 mb-6">Sedang disewa hari ini, {{ $today->translatedFormat('d F Y') }}</p>

    @if ($rentals->isEmpty())
        <x-empty-state icon="calendar-days" message="Tidak ada garasi yang sedang disewa hari ini." />
    @else
        <x-table>
            <x-slot:head>
                <th scope="col">Garasi</th>
                <th scope="col">Penyewa</th>
                <th scope="col">Kendaraan</th>
                <th scope="col">Periode</th>
                <th scope="col" class="text-right">Aksi</th>
            </x-slot:head>

            @foreach ($rentals as $rental)
                @php $daysLeft = \App\Models\Rental::countDays($today, $rental->end_date); @endphp
                <tr>
                    <td data-label="">
                        <span class="block font-bold text-primary-900">{{ $rental->garage->code }}</span>
                        <span class="block text-sm text-primary-600">{{ $rental->garage->type->label() }}</span>
                    </td>
                    <td data-label="Penyewa">
                        <span class="block text-primary-900">{{ $rental->vehicle->user->name }}</span>
                        <span class="block text-sm text-primary-600">{{ $rental->vehicle->user->phone }}</span>
                    </td>
                    <td data-label="Kendaraan" class="text-primary-700">{{ $rental->vehicle->displayName() }}</td>
                    <td data-label="Periode">
                        <span class="block text-primary-900">
                            <span class="whitespace-nowrap">{{ $rental->start_date->translatedFormat('d M Y') }}</span> –
                            <span class="whitespace-nowrap">{{ $rental->end_date->translatedFormat('d M Y') }}</span>
                        </span>
                        <span class="block text-sm text-primary-600">
                            Sisa {{ $daysLeft }} hari
                            @if ($rental->extensions_count > 0)
                                · diperpanjang {{ $rental->extensions_count }}×
                            @endif
                        </span>
                    </td>
                    <td data-label="" class="text-right">
                        <x-link-button :href="route('admin.rentals.extensions.create', $rental)" variant="row">
                            <x-heroicon-o-calendar-days class="w-3.5 h-3.5 text-secondary" />
                            Perpanjang
                        </x-link-button>
                    </td>
                </tr>
            @endforeach
        </x-table>

        {{ $rentals->links() }}
    @endif
</x-layout>
