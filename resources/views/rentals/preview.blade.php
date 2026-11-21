@php
    use App\Enums\GarageType;
    use App\Models\Rental;

    $singleGarageType = count($offers) === 1 ? array_key_first($offers) : null;
    $selectedGarageType = old('garage_type', $singleGarageType);
    $sectionTitle = 'text-lg font-black text-primary-900 tracking-tight';
    $termClass = 'text-xs font-semibold text-primary-600 uppercase tracking-wide';
    $valueClass = 'mt-1 text-sm font-bold text-primary-900';
@endphp

<x-layout title="Pilih Garasi">
    <div class="max-w-4xl">
        <a href="{{ route('rentals.create', $details) }}"
           class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-secondary hover:text-primary-900 transition-colors mb-4">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Ubah kendaraan atau waktu sewa
        </a>

        <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-2">Pilih Garasi</h1>
        <p class="text-sm text-primary-700 mb-6">Langkah 2 dari 2: pilih garasi, cek total biaya, lalu unggah bukti pelunasan.</p>

        <dl class="grid grid-cols-2 md:grid-cols-4 gap-5 p-5 sm:p-6 mb-10 rounded-2xl border border-primary-200 bg-white/70 shadow-2xs">
            <div class="col-span-2">
                <dt class="{{ $termClass }}">Kendaraan</dt>
                <dd class="{{ $valueClass }}">{{ $vehicle->displayName() }}</dd>
            </div>
            <div>
                <dt class="{{ $termClass }}">Tipe Sewa</dt>
                <dd class="{{ $valueClass }}">{{ $rentalType->label() }} · {{ $durationDays }} hari</dd>
            </div>
            @if ($vehicle->type->hasBattery())
                <div>
                    <dt class="{{ $termClass }}">Sisa Baterai</dt>
                    <dd class="{{ $valueClass }}">{{ $details['current_battery_kwh'] + 0 }} / {{ $vehicle->battery_capacity_kwh + 0 }} kWh</dd>
                </div>
            @endif
            <div>
                <dt class="{{ $termClass }}">Mulai Sewa</dt>
                <dd class="{{ $valueClass }}">{{ $start->translatedFormat('d M Y') }}</dd>
            </div>
            <div>
                <dt class="{{ $termClass }}">Selesai Sewa</dt>
                <dd class="{{ $valueClass }}">{{ $end->translatedFormat('d M Y') }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('rentals.store') }}" enctype="multipart/form-data" class="space-y-10">
            @csrf
            @foreach ($details as $field => $value)
                @if ($value !== null)
                    <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                @endif
            @endforeach

            <section class="space-y-5" aria-labelledby="garage-heading">
                <h2 id="garage-heading" class="{{ $sectionTitle }}">Garasi</h2>

                <fieldset>
                    <legend class="block font-semibold text-sm text-primary-800 mb-2">
                        Tipe Garasi <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                    </legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach ($offers as $garageTypeValue => $drafts)
                            @php $garageType = GarageType::from($garageTypeValue); @endphp
                            <x-choice-card name="garage_type" :value="$garageTypeValue" :label="$garageType->label()"
                                           :description="$drafts->count().' garasi tersedia'.($garageType->hasCharging() ? ' · termasuk isi baterai penuh' : '')"
                                           :checked="$selectedGarageType === $garageTypeValue" required />
                        @endforeach
                    </div>
                    @error('garage_type')
                        <p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>
                    @enderror
                </fieldset>

                @error('garage_id')
                    <x-alert type="error" :message="$message" />
                @enderror

                @foreach ($offers as $garageTypeValue => $drafts)
                    <div data-show-when="garage_type:{{ $garageTypeValue }}">
                        @if ($drafts->isEmpty())
                            <x-empty-state icon="home-modern"
                                           message="Tidak ada {{ GarageType::from($garageTypeValue)->label() }} yang tersedia untuk tanggal ini." />
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[28rem] overflow-y-auto p-1">
                                @foreach ($drafts as $draft)
                                    <x-choice-card name="garage_id" :value="$draft->garage->id" :label="$draft->garage->code"
                                                   :checked="(string) old('garage_id') === (string) $draft->garage->id" required>
                                        <span class="block text-xs text-primary-600 mt-1">
                                            <x-rupiah :amount="$draft->daily_price" /> / hari
                                        </span>
                                        <span class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-xs text-primary-700">
                                            <span>Sewa</span>
                                            <x-rupiah :amount="$draft->rental_cost" class="text-right" />
                                            @if ($draft->charging_cost > 0)
                                                <span>Isi {{ $draft->charging_kwh + 0 }} kWh</span>
                                                <x-rupiah :amount="$draft->charging_cost" class="text-right" />
                                            @endif
                                            <span class="font-bold text-primary-900 pt-1">Total</span>
                                            <x-rupiah :amount="$draft->total_cost" class="text-right font-bold text-primary-900 pt-1" />
                                        </span>
                                    </x-choice-card>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </section>

            <section class="space-y-5" aria-labelledby="payment-heading">
                <h2 id="payment-heading" class="{{ $sectionTitle }}">Pembayaran</h2>
                <div class="md:max-w-md">
                    <x-input name="payment_proof" type="file" label="Bukti Pelunasan Biaya" required accept="image/*" class="py-2" />
                    <p class="text-xs text-primary-600 mt-1.5">
                        Transfer sesuai total garasi yang dipilih. Gambar {{ strtoupper(implode(', ', Rental::PAYMENT_PROOF_MIMES)) }},
                        maksimal {{ Rental::PAYMENT_PROOF_MAX_KB / 1024 }} MB.
                    </p>
                </div>
            </section>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-primary-200/60">
                <x-link-button :href="route('rentals.index')" variant="secondary">Batal</x-link-button>
                <x-button type="submit">Simpan Sewa</x-button>
            </div>
        </form>
    </div>
</x-layout>
