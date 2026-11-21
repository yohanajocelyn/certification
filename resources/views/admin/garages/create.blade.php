@php
    use App\Enums\GarageType;
    use App\Models\Garage;
    use App\Models\Rental;
@endphp

<x-layout title="Buat Garasi Baru">
    <div class="max-w-4xl">
        <a href="{{ route('admin.garages.index') }}"
           class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-secondary hover:text-primary-900 transition-colors mb-4">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Kembali ke daftar garasi
        </a>

        <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-2">Buat Garasi Baru</h1>
        <p class="text-sm text-primary-700 mb-6">
            Garasi dibuat sekaligus dalam satu kelompok. Kode dibuat otomatis berurutan per tipe.
        </p>

        <form method="POST" action="{{ route('admin.garages.store') }}" class="space-y-6">
            @csrf

            <fieldset>
                <legend class="block font-semibold text-sm text-primary-800 mb-2">
                    Tipe Garasi <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                </legend>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @foreach ($types as $type)
                        @php
                            $info = $codeInfo[$type->value];
                            $description = $info['remaining'] > 0
                                ? 'Kode berikutnya '.$info['next'].' · sisa '.$info['remaining'].' kode'
                                : 'Kode sudah habis (maksimal '.Garage::MAX_CODE_NUMBER.')';
                        @endphp
                        <x-choice-card name="type" :value="$type->value" :label="$type->label()" :description="$description"
                                       :checked="old('type') === $type->value" required />
                    @endforeach
                </div>
                @error('type')
                    <p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </fieldset>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input name="quantity" type="number" label="Jumlah Garasi" required
                             min="{{ Garage::MIN_BATCH_SIZE }}" max="{{ Garage::MAX_BATCH_SIZE }}" step="1"
                             placeholder="1 – {{ Garage::MAX_BATCH_SIZE }}" />
                    <p class="text-xs text-primary-600 mt-1.5">Maksimal {{ Garage::MAX_BATCH_SIZE }} garasi sekali buat.</p>
                </div>

                <div>
                    <x-input name="daily_price" type="number" label="Harga Sewa Harian (Rp)" required
                             min="1" max="{{ Garage::MAX_PRICE }}" step="0.01" placeholder="contoh 25000" />
                    <p class="text-xs text-primary-600 mt-1.5">
                        Harga bulanan otomatis {{ Rental::MONTHLY_PRICE_MULTIPLIER }} × harga harian.
                    </p>
                </div>
            </div>

            <div data-show-when="type:{{ GarageType::Electric->value }}" class="md:max-w-[calc(50%-0.75rem)]">
                <x-input name="kwh_price" type="number" label="Harga Isi Listrik per kWh (Rp)"
                         min="1" max="{{ Garage::MAX_PRICE }}" step="0.01" placeholder="contoh 2500" />
                <p class="text-xs text-primary-600 mt-1.5">Hanya untuk Garasi Mobil Listrik.</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-primary-200/60">
                <x-link-button :href="route('admin.garages.index')" variant="secondary">Batal</x-link-button>
                <x-button type="submit">Buat Garasi</x-button>
            </div>
        </form>
    </div>
</x-layout>
