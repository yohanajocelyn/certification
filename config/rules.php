<?php

/*
|--------------------------------------------------------------------------
| Garage rental rules
|--------------------------------------------------------------------------
|
| The fixed numbers of the garage rental: limits from the brief and the sizes of the database
| columns. Validation, Blade forms, migrations and tests all read them from here.
|
| The column sizes (precision, scale, *_length, max_days) are read by the migrations when the
| tables are built: change them only together with `php artisan migrate:fresh`, or validation
| would accept values the columns cannot store.
|
*/

$codeNumberDigits = 3;
$kwhPrecision = 6;
$kwhScale = 2;

return [

    'user' => [
        'nik_length' => 16,
        'name_max_length' => 100,
        'phone_max_length' => 20,
        'email_max_length' => 255,
        'password_min_length' => 8,
    ],

    'garage' => [
        'code_number_digits' => $codeNumberDigits,
        // One type letter (S, C, E) plus the number, e.g. "C010".
        'code_length' => 1 + $codeNumberDigits,
        'max_code_number' => 999,
        'min_batch_size' => 1,
        'max_batch_size' => 999,
        // Highest daily or kWh price; keeps a 25-day month plus charging well inside decimal(12,2).
        'max_price' => 100_000_000,
        'price_precision' => 12,
        'price_scale' => 2,
    ],

    'vehicle' => [
        'plate_max_length' => 15,
        'brand_model_max_length' => 100,
        'kwh_precision' => $kwhPrecision,
        'kwh_scale' => $kwhScale,
        // Largest value a decimal(kwh_precision, kwh_scale) column holds: 9999.99.
        'max_battery_kwh' => round(10 ** ($kwhPrecision - $kwhScale) - 10 ** -$kwhScale, $kwhScale),
    ],

    'rental' => [
        // Monthly rent = 25 × the daily price.
        'monthly_price_multiplier' => 25,
        'min_daily_days' => 1,
        'payment_proof_max_kb' => 2048,
    ],

    'rental_extension' => [
        // Column limit of `days` (unsigned smallint), not a business limit: extensions have no day limit.
        'max_days' => 65_535,
    ],

];
