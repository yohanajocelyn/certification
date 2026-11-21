@php
    use App\Enums\RentalType;
    use App\Models\Rental;

    $isMonthly = $extension->extension_type === RentalType::Monthly;
    $termClass = 'text-sm font-semibold text-primary-600 uppercase tracking-wide';
    $valueClass = 'mt-1 text-base font-bold text-primary-900';
@endphp

<x-layout title="Sisa Biaya Perpanjangan {{ $rental->garage->code }}">
    <div class="max-w-5xl">
        <a href="{{ route('admin.rentals.extensions.create', [$rental, ...$input]) }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-secondary hover:text-primary-900 transition-colors mb-4">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Ubah perpanjangan
        </a>

        <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-6">Sisa Biaya Perpanjangan {{ $rental->garage->code }}</h1>

        <x-rental-summary :rental="$rental" class="mb-6" />

        <dl class="grid grid-cols-2 md:grid-cols-4 gap-5 p-5 sm:p-6 mb-8 rounded-2xl border-2 border-secondary/40 bg-primary-100/50">
            <div>
                <dt class="{{ $termClass }}">Selesai Lama</dt>
                <dd class="{{ $valueClass }}">{{ $extension->old_end_date->translatedFormat('d M Y') }}</dd>
            </div>
            <div>
                <dt class="{{ $termClass }}">Selesai Baru</dt>
                <dd class="{{ $valueClass }}">{{ $extension->new_end_date->translatedFormat('d M Y') }}</dd>
            </div>
            <div>
                <dt class="{{ $termClass }}">Tambahan</dt>
                <dd class="{{ $valueClass }}">
                    {{ $extension->days }} hari
                    @if ($isMonthly)
                        <span class="block text-sm font-medium text-primary-600">{{ $extension->months }} bulan × {{ $extension->old_end_date->daysInMonth }} hari</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="{{ $termClass }}">Sisa Biaya</dt>
                <dd class="mt-1 text-lg font-black text-primary-900"><x-rupiah :amount="$extension->additional_cost" /></dd>
                <span class="block text-sm font-medium text-primary-600">
                    @if ($isMonthly)
                        {{ $extension->months }} × <x-rupiah :amount="$rental->garage->monthlyPrice()" />
                    @else
                        {{ $extension->days }} × <x-rupiah :amount="$rental->garage->daily_price" />
                    @endif
                </span>
            </div>
        </dl>

        <form method="POST" action="{{ route('admin.rentals.extensions.store', $rental) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @foreach ($input as $field => $value)
                <input type="hidden" name="{{ $field }}" value="{{ $value }}">
            @endforeach

            <div class="md:max-w-md">
                <x-input name="payment_proof" type="file" label="Bukti Pembayaran" required accept="image/*" class="py-2" />
                <p class="text-sm text-primary-600 mt-1.5">JPG, PNG atau WEBP, maks. {{ Rental::PAYMENT_PROOF_MAX_KB / 1024 }} MB.</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-primary-200/60">
                <x-link-button :href="route('admin.rentals.index')" variant="secondary">Batal</x-link-button>
                <x-button type="submit">Simpan Perpanjangan</x-button>
            </div>
        </form>
    </div>
</x-layout>
