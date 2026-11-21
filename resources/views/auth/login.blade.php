<x-layout title="Masuk">
    <div class="w-full sm:max-w-md px-6 py-8 sm:px-8 sm:py-10 bg-white/80 backdrop-blur-md shadow-2xl sm:rounded-3xl border border-white/40">
        <div class="mb-6 sm:mb-8 text-center">
            <h1 class="text-2xl sm:text-3xl font-black text-primary-900 leading-tight">{{ config('app.name') }}</h1>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf

            <x-input name="email" type="email" label="Email" required autofocus autocomplete="username"
                     placeholder="anda@contoh.com" />

            <x-input name="password" type="password" label="Kata Sandi" required autocomplete="current-password" />

            <x-button type="submit" class="w-full">Masuk</x-button>
        </form>

        <div class="mt-6 sm:mt-8 pt-6 border-t border-primary-200/50 text-center">
            <p class="text-sm text-primary-600 font-medium">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-semibold text-secondary hover:text-primary-900 transition-colors">
                    Daftar sebagai penyewa
                </a>
            </p>
        </div>
    </div>
</x-layout>
