<x-layout title="Kendaraan Saya">
    <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-6">Kendaraan Saya</h1>

    @if ($vehicles->isEmpty())
        <x-empty-state icon="truck" message="Belum ada kendaraan. Kendaraan didaftarkan saat menyewa garasi.">
            <x-link-button :href="route('rentals.create')">
                <x-heroicon-o-plus class="w-4 h-4 stroke-[2.5]" />
                Sewa Garasi Baru
            </x-link-button>
        </x-empty-state>
    @else
        <x-table>
            <x-slot:head>
                <th scope="col">Kendaraan</th>
                <th scope="col">Nomor Plat</th>
                <th scope="col">Kapasitas Baterai</th>
                <th scope="col">Hari Ini</th>
                <th scope="col" class="text-right">Jumlah Sewa</th>
                <th scope="col" class="text-right">Aksi</th>
            </x-slot:head>

            @foreach ($vehicles as $vehicle)
                @php $rentalToday = $vehicle->rentalsToday->first(); @endphp
                <tr>
                    <td data-label="">
                        <span class="block font-bold text-primary-900">{{ $vehicle->brand_model }}</span>
                        <span class="block text-sm text-primary-600">{{ $vehicle->type->label() }}</span>
                    </td>
                    <td data-label="Nomor Plat" class="font-mono tracking-wide text-primary-800 whitespace-nowrap">
                        {{ $vehicle->plate_number ?? '—' }}
                    </td>
                    <td data-label="Kapasitas Baterai" class="text-primary-700 whitespace-nowrap">
                        {{ $vehicle->hasBattery() ? ($vehicle->battery_capacity_kwh + 0).' kWh' : '—' }}
                    </td>
                    <td data-label="Hari Ini">
                        @if ($rentalToday)
                            <x-status tone="success">
                                Di garasi {{ $rentalToday->garage->code }}
                                <span class="font-normal text-primary-600 whitespace-nowrap">s.d. {{ $rentalToday->end_date->translatedFormat('d M Y') }}</span>
                            </x-status>
                        @else
                            <x-status tone="neutral">Tidak di garasi</x-status>
                        @endif
                    </td>
                    <td data-label="Jumlah Sewa" class="text-right">{{ $vehicle->rentals_count }}</td>
                    <td data-label="" class="text-right">
                        <x-link-button :href="route('rentals.create', ['vehicle_id' => $vehicle->id])" variant="row">
                            <x-heroicon-o-plus class="w-3.5 h-3.5 text-secondary" />
                            Sewa Garasi
                        </x-link-button>
                    </td>
                </tr>
            @endforeach
        </x-table>

        {{ $vehicles->links() }}
    @endif
</x-layout>
