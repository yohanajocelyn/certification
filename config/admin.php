<?php

/*
|--------------------------------------------------------------------------
| Seeded admin account
|--------------------------------------------------------------------------
|
| Admins cannot register through the app, so the first admin is created by
| AdminSeeder from these values. Set them in .env; read them through
| config('admin.*') so they still work when the config is cached.
|
*/

return [

    'name' => env('ADMIN_NAME', 'Administrator'),

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

    'nik' => env('ADMIN_NIK'),

    'phone' => env('ADMIN_PHONE'),

];
