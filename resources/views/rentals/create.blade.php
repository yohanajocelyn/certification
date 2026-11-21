@php
    use App\Enums\RentalType;
    use App\Services\RentalService;

    $selectedVehicle = (string) ($defaults['vehicle_id']
        ?? ($vehicles->isEmpty() ? RentalService::NEW_VEHICLE : $vehicles->first()->id));
    $selectedRentalType = old('rental_type', $defaults['rental_type'] ?? RentalType::Daily->value);
    $isMonthly = $selectedRentalType === RentalType::Monthly->value;
    $monthDays = $startDate->daysInMonth;
    $vehicleOptions = $vehicles->mapWithKeys(fn ($vehicle) => [$vehicle->id => $vehicle->displayName()])->all()
        + [RentalService::NEW_VEHICLE => '+ Kendaraan baru'];
    $sectionTitle = 'text-lg font-black text-primary-900 tracking-tight';
@endphp

<x-layout title="Sewa Garasi Baru">
    <div class="max-w-5xl">
        <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-8">Sewa Garasi Baru</h1>

        <form method="GET" action="{{ route('rentals.preview') }}" class="space-y-10">
            <section class="space-y-5" aria-labelledby="vehicle-heading">
                <h2 id="vehicle-heading" class="{{ $sectionTitle }}">Kendaraan</h2>

                <div class="md:max-w-[calc(50%-0.625rem)]">
                    <x-select name="vehicle_id" label="Pilih Kendaraan" required
                              :options="$vehicleOptions" :value="$selectedVehicle" />
                </div>

                <x-show-when field="vehicle_id" :values="[RentalService::NEW_VEHICLE]" :current="old('vehicle_id', $selectedVehicle)"
                     class="grid grid-cols-1 md:grid-cols-2 gap-5 p-5 sm:p-6 rounded-2xl border border-primary-200 bg-white/60">
                    <x-select name="vehicle_type" label="Tipe Kendaraan" required placeholder="Pilih tipe"
                              :options="collect($vehicleTypes)->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all()"
                              :value="$defaults['vehicle_type'] ?? null" />

                    <x-input name="brand_model" label="Merek dan Tipe" required maxlength="{{ config('rules.vehicle.brand_model_max_length') }}"
                             placeholder="contoh Toyota Avanza" :value="$defaults['brand_model'] ?? null" />

                    <x-show-when field="vehicle_type" :values="$plateVehicleTypes" :current="old('vehicle_type', $defaults['vehicle_type'] ?? '')">
                        <x-input name="plate_number" label="Nomor Plat" required maxlength="{{ config('rules.vehicle.plate_max_length') }}"
                                 placeholder="contoh B 1234 XYZ" class="uppercase" :value="$defaults['plate_number'] ?? null" />
                    </x-show-when>

                    <x-show-when field="vehicle_type" :values="$batteryVehicleTypes" :current="old('vehicle_type', $defaults['vehicle_type'] ?? '')">
                        <x-input name="battery_capacity_kwh" type="number" label="Kapasitas Baterai Penuh (kWh)" required
                                 min="0.01" max="{{ config('rules.vehicle.max_battery_kwh') }}" step="0.01"
                                 :value="$defaults['battery_capacity_kwh'] ?? null" />
                    </x-show-when>

                    <x-show-when field="vehicle_type" :values="$batteryVehicleTypes" :current="old('vehicle_type', $defaults['vehicle_type'] ?? '')">
                        <x-input name="current_battery_kwh" type="number" label="Sisa Baterai Saat Ini (kWh)" required
                                 min="0" step="0.01" :value="$defaults['current_battery_kwh'] ?? null" />
                    </x-show-when>
                </x-show-when>

                @if ($electricVehicleIds !== [])
                    <x-show-when field="vehicle_id" :values="$electricVehicleIds" :current="old('vehicle_id', $selectedVehicle)" class="md:max-w-[calc(50%-0.375rem)]">
                        <x-input name="current_battery_kwh" id="current_battery_kwh_existing" type="number"
                                 label="Sisa Baterai Saat Ini (kWh)" required
                                 min="0" step="0.01" :value="$defaults['current_battery_kwh'] ?? null" />
                    </x-show-when>
                @endif
            </section>

            <section class="space-y-5" aria-labelledby="period-heading">
                <h2 id="period-heading" class="{{ $sectionTitle }}">Waktu Sewa</h2>

                <fieldset>
                    <legend class="block font-semibold text-base text-primary-800 mb-2">
                        Tipe Sewa <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                    </legend>
                    <div class="grid grid-cols-2 gap-3 md:max-w-md">
                        @foreach ($rentalTypes as $rentalType)
                            <x-choice-card name="rental_type" :value="$rentalType->value" :label="$rentalType->label()"
                                           :checked="$selectedRentalType === $rentalType->value" required />
                        @endforeach
                    </div>
                    @error('rental_type')
                        <p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <x-input name="start_date" type="date" label="Tanggal Mulai Sewa" required
                                 min="{{ $today->toDateString() }}" :value="$startDate->toDateString()" />
                    </div>

                    <div>
                        <x-input name="days" type="number" label="Jumlah Hari" required step="1"
                                 min="{{ config('rules.rental.min_daily_days') }}"
                                 :max="$isMonthly ? null : $monthDays - 1"
                                 :readonly="$isMonthly"
                                 :value="$isMonthly ? $monthDays : ($defaults['days'] ?? null)"
                                 data-rental-days
                                 data-monthly-value="{{ RentalType::Monthly->value }}"
                                 data-daily-hint="Maksimal :max hari."
                                 data-monthly-hint="Otomatis :days hari (1 bulan)." />
                        <p class="text-sm text-primary-600 mt-1.5" data-rental-days-hint>
                            @if ($isMonthly)
                                Otomatis {{ $monthDays }} hari (1 bulan).
                            @else
                                Maksimal {{ $monthDays - 1 }} hari.
                            @endif
                        </p>
                    </div>

                    <div>
                        {{-- Display only, never submitted: the server works out the end date itself. --}}
                        <x-input name="end_date_display" id="end_date" type="date" label="Tanggal Selesai Sewa" readonly
                                 form="" :value="$initialEndDate?->toDateString()" data-rental-end-date />
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-primary-200/60">
                <x-link-button :href="route('rentals.index')" variant="secondary">Batal</x-link-button>
                <x-button type="submit">
                    Lanjut
                    <x-heroicon-o-arrow-right class="w-4 h-4" />
                </x-button>
            </div>
        </form>
    </div>
</x-layout>
