@php
    use App\Enums\RentalType;
    use App\Models\RentalExtension;

    $selectedType = old('extension_type', $defaults['extension_type'] ?? RentalType::Daily->value);
    $firstExtraDay = $rental->end_date->addDay();
    $monthDays = $rental->end_date->daysInMonth;
@endphp

<x-layout title="Perpanjang Sewa {{ $rental->garage->code }}">
    <div class="max-w-5xl">
        <a href="{{ route('admin.rentals.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-secondary hover:text-primary-900 transition-colors mb-4">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Kembali ke daftar sewa hari ini
        </a>

        <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-6">Perpanjang Sewa {{ $rental->garage->code }}</h1>

        <x-rental-summary :rental="$rental" class="mb-8" />

        @if ($rental->extensions->isNotEmpty())
            <section class="mb-8" aria-labelledby="history-heading">
                <h2 id="history-heading" class="text-lg font-black text-primary-900 tracking-tight mb-3">Riwayat Perpanjangan</h2>
                <x-table>
                    <x-slot:head>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Selesai Lama → Baru</th>
                        <th scope="col">Tambahan</th>
                        <th scope="col" class="text-right">Biaya</th>
                        <th scope="col">Bukti</th>
                    </x-slot:head>
                    @foreach ($rental->extensions as $pastExtension)
                        <tr>
                            <td data-label="" class="text-primary-700 whitespace-nowrap">{{ $pastExtension->created_at->translatedFormat('d M Y') }}</td>
                            <td data-label="Selesai Lama → Baru">
                                <span class="whitespace-nowrap">{{ $pastExtension->old_end_date->translatedFormat('d M Y') }}</span> →
                                <span class="whitespace-nowrap">{{ $pastExtension->new_end_date->translatedFormat('d M Y') }}</span>
                            </td>
                            <td data-label="Tambahan" class="text-primary-700">
                                {{ $pastExtension->extension_type->label() }} · {{ $pastExtension->days }} hari
                            </td>
                            <td data-label="Biaya" class="text-right"><x-rupiah :amount="$pastExtension->additional_cost" /></td>
                            <td data-label="Bukti">
                                <a href="{{ $pastExtension->paymentProofUrl() }}" target="_blank" rel="noopener"
                                   class="text-sm font-semibold text-secondary hover:text-primary-900 underline underline-offset-4">Lihat</a>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </section>
        @endif

        <form method="GET" action="{{ route('admin.rentals.extensions.preview', $rental) }}" class="space-y-6">
            <fieldset>
                <legend class="block font-semibold text-base text-primary-800 mb-2">
                    Tipe Perpanjangan <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                </legend>
                <div class="grid grid-cols-2 gap-3 md:max-w-md">
                    @foreach ($rentalTypes as $rentalType)
                        <x-choice-card name="extension_type" :value="$rentalType->value" :label="$rentalType->label()"
                                       :checked="$selectedType === $rentalType->value" required />
                    @endforeach
                </div>
                @error('extension_type')
                    <p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </fieldset>

            <x-show-when field="extension_type" :values="[RentalType::Daily->value]" :current="$selectedType" class="md:max-w-xs">
                <x-input name="new_end_date" type="date" label="Tanggal Selesai Baru" required
                         min="{{ $firstExtraDay->toDateString() }}" :value="$defaults['new_end_date'] ?? $firstExtraDay->toDateString()" />
            </x-show-when>

            <x-show-when field="extension_type" :values="[RentalType::Monthly->value]" :current="$selectedType" class="md:max-w-xs">
                <x-input name="months" type="number" label="Jumlah Bulan" required step="1"
                         min="{{ RentalExtension::MIN_MONTHS }}" max="{{ RentalExtension::MAX_MONTHS }}"
                         :value="$defaults['months'] ?? RentalExtension::MIN_MONTHS" />
                <p class="text-sm text-primary-600 mt-1.5">1 bulan = {{ $monthDays }} hari</p>
            </x-show-when>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-primary-200/60">
                <x-link-button :href="route('admin.rentals.index')" variant="secondary">Batal</x-link-button>
                <x-button type="submit">
                    Hitung Sisa Biaya
                    <x-heroicon-o-arrow-right class="w-4 h-4" />
                </x-button>
            </div>
        </form>
    </div>
</x-layout>
