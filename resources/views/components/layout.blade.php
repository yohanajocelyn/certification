@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="view-transition" content="same-origin">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800,900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-primary-50 text-primary-900 selection:bg-primary-300 selection:text-primary-900">
    <div class="min-h-screen flex flex-col relative overflow-x-clip">
        <div class="absolute inset-0 z-0 pointer-events-none opacity-50 bg-[radial-gradient(ellipse_at_top,_var(--color-primary-200),_transparent_70%)]"></div>

        @auth
            <header class="sticky top-0 z-20 border-b border-primary-900 bg-primary-50/85 backdrop-blur-md">
                <div class="max-w-6xl mx-auto px-6 sm:px-10 py-4 md:py-0 md:h-16 flex flex-wrap md:flex-nowrap items-center justify-between gap-x-8 gap-y-3">
                    <a href="{{ route('home') }}" class="text-lg font-black tracking-tight text-primary-900">
                        {{ config('app.name') }}
                    </a>

                    <nav aria-label="Menu utama"
                         class="order-last md:order-none w-full md:w-auto flex items-center gap-6 md:gap-8 overflow-x-auto whitespace-nowrap md:flex-1">
                        @if (auth()->user()->isAdmin())
                            <x-nav-link :href="route('admin.garages.index')" :active="request()->routeIs('admin.garages.*')">Garasi</x-nav-link>
                            <x-nav-link :href="route('admin.tenants.index')" :active="request()->routeIs('admin.tenants.*')">Penyewa</x-nav-link>
                            <x-nav-link :href="route('admin.rentals.index')" :active="request()->routeIs('admin.rentals.*')">Perpanjang Sewa</x-nav-link>
                        @else
                            <x-nav-link :href="route('rentals.index')" :active="request()->routeIs('rentals.index')">Sewa Saya</x-nav-link>
                            <x-nav-link :href="route('rentals.create')" :active="request()->routeIs('rentals.create', 'rentals.preview')">Sewa Baru</x-nav-link>
                        @endif
                    </nav>

                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-full bg-primary-200/70 border border-primary-300/60 flex items-center justify-center shrink-0">
                            <x-heroicon-s-user class="w-5 h-5 text-primary-700" />
                        </div>
                        <div class="hidden sm:block min-w-0">
                            <p class="text-sm font-semibold text-primary-900 truncate max-w-[10rem]">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-primary-600">{{ auth()->user()->role->label() }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="p-1.5 text-primary-500 hover:text-primary-900 hover:bg-primary-100 rounded-lg transition-colors cursor-pointer"
                                    title="Keluar"
                                    aria-label="Keluar">
                                <x-heroicon-o-arrow-right-start-on-rectangle class="w-5 h-5" />
                            </button>
                        </form>
                    </div>
                </div>
            </header>
        @endauth

        <main @class([
            'relative z-10 flex-1 w-full',
            'max-w-6xl mx-auto px-6 sm:px-10 py-8 md:py-12' => auth()->check(),
            'flex flex-col items-center justify-center px-4 py-8 sm:py-12' => auth()->guest(),
        ])>
            @if (session('success') || session('error'))
                <div @class(['space-y-3', 'mb-6' => auth()->check(), 'w-full sm:max-w-md mb-4' => auth()->guest()])>
                    <x-alert type="success" :message="session('success')" />
                    <x-alert type="error" :message="session('error')" />
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
