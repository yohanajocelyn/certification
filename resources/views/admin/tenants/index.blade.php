@php
    use App\Enums\UserStatus;

    $chipClass = 'px-3.5 py-1.5 rounded-lg text-sm font-semibold border transition-colors';
    $activeChip = $chipClass.' bg-primary text-white border-primary';
    $idleChip = $chipClass.' bg-white text-primary-800 border-primary-200 hover:bg-primary-100';
@endphp

<x-layout title="Verifikasi Penyewa">
    <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-6">Verifikasi Penyewa</h1>

    <nav aria-label="Saring status pendaftaran" class="flex flex-wrap items-center gap-2 mb-6">
        @foreach ($statuses as $status)
            <a href="{{ route('admin.tenants.index', ['status' => $status->value]) }}"
               class="{{ $selectedStatus === $status ? $activeChip : $idleChip }}"
               @if ($selectedStatus === $status) aria-current="page" @endif>
                {{ $status->label() }} ({{ $countsByStatus[$status->value] ?? 0 }})
            </a>
        @endforeach
    </nav>

    @if ($tenants->isEmpty())
        <x-empty-state icon="user-group" message="Tidak ada penyewa dengan status {{ mb_strtolower($selectedStatus->label()) }}." />
    @else
        <x-table>
            <x-slot:head>
                <th scope="col">Penyewa</th>
                <th scope="col">NIK</th>
                <th scope="col">Telepon</th>
                <th scope="col">Tanggal Daftar</th>
                @if ($selectedStatus === UserStatus::Pending)
                    <th scope="col" class="text-right">Aksi</th>
                @endif
            </x-slot:head>

            @foreach ($tenants as $tenant)
                <tr>
                    <td data-label="">
                        <span class="block font-bold text-primary-900">{{ $tenant->name }}</span>
                        <span class="block text-sm text-primary-600 wrap-anywhere">{{ $tenant->email }}</span>
                    </td>
                    <td data-label="NIK" class="font-mono tracking-wide text-primary-800 whitespace-nowrap">{{ $tenant->nik }}</td>
                    <td data-label="Telepon" class="text-primary-700 whitespace-nowrap">{{ $tenant->phone }}</td>
                    <td data-label="Tanggal Daftar" class="text-primary-700 whitespace-nowrap">{{ $tenant->created_at->translatedFormat('d M Y') }}</td>
                    @if ($selectedStatus === UserStatus::Pending)
                        <td data-label="">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('admin.tenants.update', $tenant) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ UserStatus::Approved->value }}">
                                    <x-button type="submit" variant="row" aria-label="Terima pendaftaran {{ $tenant->name }}">
                                        <x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-600" />
                                        Terima
                                    </x-button>
                                </form>
                                <form method="POST" action="{{ route('admin.tenants.update', $tenant) }}"
                                      data-confirm="Tolak pendaftaran {{ $tenant->name }}? Keputusan ini tidak bisa diubah.">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ UserStatus::Rejected->value }}">
                                    <x-button type="submit" variant="row-danger" aria-label="Tolak pendaftaran {{ $tenant->name }}">
                                        <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                                        Tolak
                                    </x-button>
                                </form>
                            </div>
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-table>

        {{ $tenants->links() }}
    @endif
</x-layout>
