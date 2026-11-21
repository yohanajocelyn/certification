{{-- Key facts of one rental for admin pages: garage, tenant, vehicle, period and prices. --}}
@props(['rental'])

@php
    $termClass = 'text-sm font-semibold text-primary-600 uppercase tracking-wide';
    $valueClass = 'mt-1 text-base font-bold text-primary-900';
@endphp

<dl {{ $attributes->merge(['class' => 'grid grid-cols-2 md:grid-cols-4 gap-5 p-5 sm:p-6 rounded-2xl border border-primary-200 bg-white/70 shadow-2xs']) }}>
    <div>
        <dt class="{{ $termClass }}">Garasi</dt>
        <dd class="{{ $valueClass }}">{{ $rental->garage->code }}
            <span class="block text-sm font-medium text-primary-600">{{ $rental->garage->type->label() }}</span>
        </dd>
    </div>
    <div>
        <dt class="{{ $termClass }}">Penyewa</dt>
        <dd class="{{ $valueClass }}">{{ $rental->user->name }}
            <span class="block text-sm font-medium text-primary-600">{{ $rental->user->phone }}</span>
        </dd>
    </div>
    <div class="col-span-2">
        <dt class="{{ $termClass }}">Kendaraan</dt>
        <dd class="{{ $valueClass }}">{{ $rental->vehicle->displayName() }}</dd>
    </div>
    <div class="col-span-2">
        <dt class="{{ $termClass }}">Periode Sekarang</dt>
        <dd class="{{ $valueClass }}">
            {{ $rental->start_date->translatedFormat('d M Y') }} – {{ $rental->end_date->translatedFormat('d M Y') }}
            <span class="block text-sm font-medium text-primary-600">{{ $rental->rental_type->label() }} · {{ $rental->durationInDays() }} hari</span>
        </dd>
    </div>
    <div>
        <dt class="{{ $termClass }}">Harga Harian</dt>
        <dd class="{{ $valueClass }}"><x-rupiah :amount="$rental->daily_price" /></dd>
    </div>
    <div>
        <dt class="{{ $termClass }}">Harga Bulanan</dt>
        <dd class="{{ $valueClass }}"><x-rupiah :amount="(float) $rental->daily_price * \App\Models\Rental::MONTHLY_PRICE_MULTIPLIER" /></dd>
    </div>
</dl>
