@php
    use App\Models\Garage;
    use App\Models\Rental;

    $termClass = 'text-sm font-semibold text-primary-600 uppercase tracking-wide';
    $valueClass = 'mt-1 text-base font-bold text-primary-900';
@endphp

<x-layout title="Ubah Garasi {{ $garage->code }}">
    <div class="max-w-5xl">
        <a href="{{ route('admin.garages.index', ['type' => $garage->type->value]) }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-secondary hover:text-primary-900 transition-colors mb-4">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Kembali ke daftar garasi
        </a>

        <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-6">Ubah Garasi {{ $garage->code }}</h1>

        <dl class="grid grid-cols-2 gap-5 p-5 sm:p-6 mb-8 rounded-2xl border border-primary-200 bg-white/70 shadow-2xs md:max-w-md">
            <div>
                <dt class="{{ $termClass }}">Kode</dt>
                <dd class="{{ $valueClass }}">{{ $garage->code }}</dd>
            </div>
            <div>
                <dt class="{{ $termClass }}">Tipe</dt>
                <dd class="{{ $valueClass }}">{{ $garage->type->label() }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('admin.garages.update', $garage) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input name="daily_price" type="number" label="Harga Sewa Harian (Rp)" required
                             min="1" max="{{ Garage::MAX_PRICE }}" step="0.01" :value="$garage->daily_price + 0" />
                    <p class="text-sm text-primary-600 mt-1.5">Harga bulanan = {{ Rental::MONTHLY_PRICE_MULTIPLIER }} × harga harian. Sewa yang sudah ada tetap memakai harga lama.</p>
                </div>

                @if ($garage->type->hasCharging())
                    <div>
                        <x-input name="kwh_price" type="number" label="Harga Isi Listrik per kWh (Rp)" required
                                 min="1" max="{{ Garage::MAX_PRICE }}" step="0.01" :value="$garage->kwh_price + 0" />
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-primary-200/60">
                <x-link-button :href="route('admin.garages.index', ['type' => $garage->type->value])" variant="secondary">Batal</x-link-button>
                <x-button type="submit">Simpan</x-button>
            </div>
        </form>
    </div>
</x-layout>
