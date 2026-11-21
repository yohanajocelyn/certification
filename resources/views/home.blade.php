<x-layout title="Beranda">
    <h1 class="text-2xl sm:text-3xl font-black text-primary-900 tracking-tight mb-2">
        Selamat datang, {{ $user->name }}
    </h1>
    <p class="text-sm sm:text-base text-primary-700">
        Anda masuk sebagai {{ $user->role->label() }}.
    </p>
</x-layout>
