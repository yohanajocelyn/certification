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
                <th scope="col">Tipe</th>
                <th scope="col">Nomor Plat</th>
                <th scope="col">Kapasitas Baterai</th>
                <th scope="col">Garasi</th>
            </x-slot:head>

            @foreach ($vehicles as $vehicle)
                @php $rentalToday = $vehicle->rentalsToday->first(); @endphp
                <tr>
                    <td data-label="" class="font-bold text-primary-900">{{ $vehicle->brand_model }}</td>
                    <td data-label="Tipe" class="text-primary-700 whitespace-nowrap">{{ $vehicle->type->label() }}</td>
                    <td data-label="Nomor Plat" class="font-mono tracking-wide text-primary-800 whitespace-nowrap">
                        {{ $vehicle->plate_number ?? '—' }}
                    </td>
                    <td data-label="Kapasitas Baterai" class="text-primary-700 whitespace-nowrap">
                        {{ $vehicle->hasBattery() ? ($vehicle->battery_capacity_kwh + 0).' kWh' : '—' }}
                    </td>
                    <td data-label="Garasi">
                        @if ($rentalToday)
                            <b>Garasi {{ $rentalToday->garage->code }}</b>
                            <span class="font-normal text-primary-600 whitespace-nowrap">s.d. {{ $rentalToday->end_date->translatedFormat('d M Y') }}</span>
                        @else
                            <span class="text-primary-600">Tidak di garasi</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>

        {{ $vehicles->links() }}
    @endif
</x-layout>
