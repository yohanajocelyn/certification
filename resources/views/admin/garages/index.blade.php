<x-layout title="Garasi">
    <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-6">Garasi</h1>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <x-link-button :href="route('admin.garages.create')" class="shrink-0">
            <x-heroicon-o-plus class="w-4 h-4 stroke-[2.5]" />
            Buat Garasi Baru
        </x-link-button>

        <nav aria-label="Saring tipe garasi" class="flex flex-wrap items-center gap-2">
            @php
                $chipClass = 'px-3.5 py-1.5 rounded-lg text-sm font-semibold border transition-colors';
                $activeChip = $chipClass.' bg-primary text-white border-primary';
                $idleChip = $chipClass.' bg-white text-primary-800 border-primary-200 hover:bg-primary-100';
            @endphp
            <a href="{{ route('admin.garages.index') }}" class="{{ $selectedType ? $idleChip : $activeChip }}"
               @if (! $selectedType) aria-current="page" @endif>Semua</a>
            @foreach ($types as $type)
                <a href="{{ route('admin.garages.index', ['type' => $type->value]) }}"
                   class="{{ $selectedType === $type ? $activeChip : $idleChip }}"
                   @if ($selectedType === $type) aria-current="page" @endif>{{ $type->label() }}</a>
            @endforeach
        </nav>
    </div>

    @if ($garages->isEmpty())
        <x-empty-state icon="home-modern" message="Belum ada garasi untuk ditampilkan.">
            <x-link-button :href="route('admin.garages.create')">
                <x-heroicon-o-plus class="w-4 h-4 stroke-[2.5]" />
                Buat Garasi Baru
            </x-link-button>
        </x-empty-state>
    @else
        <x-table>
            <x-slot:head>
                <th scope="col">Kode</th>
                <th scope="col">Tipe</th>
                <th scope="col" class="text-right">Harga Harian</th>
                <th scope="col" class="text-right">Harga Bulanan</th>
                <th scope="col" class="text-right">Isi Listrik / kWh</th>
                <th scope="col">Status Hari Ini</th>
            </x-slot:head>

            @foreach ($garages as $garage)
                <tr>
                    <td data-label="" class="font-bold text-primary-900">{{ $garage->code }}</td>
                    <td data-label="Tipe" class="text-primary-700">{{ $garage->type->label() }}</td>
                    <td data-label="Harga Harian" class="text-right"><x-rupiah :amount="$garage->daily_price" /></td>
                    <td data-label="Harga Bulanan" class="text-right"><x-rupiah :amount="$garage->monthlyPrice()" /></td>
                    <td data-label="Isi Listrik / kWh" class="text-right text-primary-700">
                        @if ($garage->kwh_price !== null)
                            <x-rupiah :amount="$garage->kwh_price" />
                        @else
                            <span class="text-primary-500">—</span>
                        @endif
                    </td>
                    <td data-label="Status Hari Ini">
                        <x-status :tone="$garage->is_used ? 'danger' : 'success'">
                            {{ $garage->is_used ? 'Dipakai' : 'Tersedia' }}
                        </x-status>
                    </td>
                </tr>
            @endforeach
        </x-table>

        {{ $garages->links() }}
    @endif
</x-layout>
