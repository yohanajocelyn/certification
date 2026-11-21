@php
    use App\Models\User;
@endphp

<x-layout title="Daftar Penyewa">
    <div class="w-full sm:max-w-lg px-6 py-8 sm:px-8 sm:py-10 bg-white/80 backdrop-blur-md shadow-2xl sm:rounded-3xl border border-white/40">
        <div class="mb-6 sm:mb-8 text-center">
            <h1 class="text-2xl sm:text-3xl font-black text-primary-900 mb-2 leading-tight">Daftar sebagai Penyewa</h1>
            <p class="text-sm sm:text-base text-primary-600 font-medium">Akun aktif setelah admin memverifikasi KTP Anda.</p>
        </div>

        <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
            @csrf

            <x-input name="nik" label="NIK (Nomor KTP)" required inputmode="numeric" autofocus
                     minlength="{{ User::NIK_LENGTH }}" maxlength="{{ User::NIK_LENGTH }}"
                     placeholder="{{ User::NIK_LENGTH }} digit" />

            <x-input name="name" label="Nama Lengkap" required autocomplete="name"
                     maxlength="{{ User::NAME_MAX_LENGTH }}" />

            <x-input name="phone" type="tel" label="Nomor Telepon" required autocomplete="tel"
                     maxlength="{{ User::PHONE_MAX_LENGTH }}" placeholder="08xxxxxxxxxx" />

            <x-input name="email" type="email" label="Email" required autocomplete="email"
                     maxlength="{{ User::EMAIL_MAX_LENGTH }}" placeholder="anda@contoh.com" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-input name="password" type="password" label="Kata Sandi" required autocomplete="new-password"
                         minlength="{{ User::PASSWORD_MIN_LENGTH }}" />

                <x-input name="password_confirmation" type="password" label="Ulangi Kata Sandi" required
                         autocomplete="new-password" />
            </div>

            <x-button type="submit" class="w-full">
                <x-heroicon-o-user-plus class="w-4 h-4" />
                Daftar
            </x-button>
        </form>

        <div class="mt-6 sm:mt-8 pt-6 border-t border-primary-200/50 text-center">
            <p class="text-xs sm:text-sm text-primary-600 font-medium">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-semibold text-secondary hover:text-primary-900 transition-colors">Masuk</a>
            </p>
        </div>
    </div>
</x-layout>
