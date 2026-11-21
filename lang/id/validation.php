<?php

/*
|--------------------------------------------------------------------------
| Validation messages (Indonesian)
|--------------------------------------------------------------------------
|
| Covers the rules this app uses. Any rule missing here falls back to the
| framework's English message (APP_FALLBACK_LOCALE=en).
|
*/

return [

    'accepted' => ':attribute harus disetujui.',
    'after' => ':attribute harus tanggal setelah :date.',
    'after_or_equal' => ':attribute harus tanggal :date atau setelahnya.',
    'array' => ':attribute harus berupa daftar.',
    'before' => ':attribute harus tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus tanggal :date atau sebelumnya.',
    'between' => [
        'array' => ':attribute harus berisi :min sampai :max item.',
        'file' => ':attribute harus berukuran :min sampai :max kilobyte.',
        'numeric' => ':attribute harus bernilai :min sampai :max.',
        'string' => ':attribute harus berisi :min sampai :max karakter.',
    ],
    'boolean' => ':attribute harus bernilai benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':attribute harus tanggal yang valid.',
    'date_format' => ':attribute harus sesuai format :format.',
    'decimal' => ':attribute harus memiliki :decimal angka desimal.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus terdiri dari :digits digit.',
    'digits_between' => ':attribute harus terdiri dari :min sampai :max digit.',
    'email' => ':attribute harus alamat email yang valid.',
    'enum' => ':attribute yang dipilih tidak valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'file' => ':attribute harus berupa file.',
    'gt' => [
        'array' => ':attribute harus berisi lebih dari :value item.',
        'file' => ':attribute harus lebih besar dari :value kilobyte.',
        'numeric' => ':attribute harus lebih besar dari :value.',
        'string' => ':attribute harus lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => ':attribute harus berisi :value item atau lebih.',
        'file' => ':attribute harus berukuran :value kilobyte atau lebih.',
        'numeric' => ':attribute harus bernilai :value atau lebih.',
        'string' => ':attribute harus berisi :value karakter atau lebih.',
    ],
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus bilangan bulat.',
    'lt' => [
        'array' => ':attribute harus berisi kurang dari :value item.',
        'file' => ':attribute harus lebih kecil dari :value kilobyte.',
        'numeric' => ':attribute harus lebih kecil dari :value.',
        'string' => ':attribute harus kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => ':attribute tidak boleh berisi lebih dari :value item.',
        'file' => ':attribute harus berukuran :value kilobyte atau kurang.',
        'numeric' => ':attribute harus bernilai :value atau kurang.',
        'string' => ':attribute harus berisi :value karakter atau kurang.',
    ],
    'max' => [
        'array' => ':attribute tidak boleh berisi lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih besar dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh lebih besar dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'mimes' => ':attribute harus file bertipe: :values.',
    'mimetypes' => ':attribute harus file bertipe: :values.',
    'min' => [
        'array' => ':attribute harus berisi minimal :min item.',
        'file' => ':attribute minimal berukuran :min kilobyte.',
        'numeric' => ':attribute minimal bernilai :min.',
        'string' => ':attribute minimal berisi :min karakter.',
    ],
    'numeric' => ':attribute harus berupa angka.',
    'prohibited' => ':attribute tidak boleh diisi.',
    'prohibited_if' => ':attribute tidak boleh diisi jika :other adalah :value.',
    'prohibited_unless' => ':attribute tidak boleh diisi kecuali :other adalah :values.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi jika :other adalah :value.',
    'required_unless' => ':attribute wajib diisi kecuali :other adalah :values.',
    'required_with' => ':attribute wajib diisi jika :values diisi.',
    'same' => ':attribute dan :other harus sama.',
    'size' => [
        'array' => ':attribute harus berisi :size item.',
        'file' => ':attribute harus berukuran :size kilobyte.',
        'numeric' => ':attribute harus bernilai :size.',
        'string' => ':attribute harus berisi :size karakter.',
    ],
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah terdaftar.',
    'uploaded' => ':attribute gagal diunggah.',

    'custom' => [],

    /*
    | Field names shown in messages instead of the raw input name.
    */
    'attributes' => [
        'nik' => 'NIK',
        'name' => 'nama lengkap',
        'phone' => 'nomor telepon',
        'email' => 'email',
        'password' => 'kata sandi',
        'password_confirmation' => 'konfirmasi kata sandi',
        'type' => 'tipe',
        'garage_type' => 'tipe garasi',
        'garage_id' => 'garasi',
        'quantity' => 'jumlah garasi',
        'daily_price' => 'harga sewa harian',
        'kwh_price' => 'harga isi listrik per kWh',
        'vehicle_id' => 'kendaraan',
        'vehicle_type' => 'tipe kendaraan',
        'plate_number' => 'nomor plat',
        'brand_model' => 'merek dan tipe',
        'battery_capacity_kwh' => 'kapasitas baterai',
        'current_battery_kwh' => 'sisa baterai saat ini',
        'rental_type' => 'tipe sewa',
        'days' => 'jumlah hari',
        'start_date' => 'tanggal mulai sewa',
        'end_date' => 'tanggal selesai sewa',
        'new_end_date' => 'tanggal selesai sewa baru',
        'extension_type' => 'tipe perpanjangan',
        'months' => 'jumlah bulan',
        'payment_proof' => 'bukti pembayaran',
        'status' => 'status',
        'year' => 'tahun',
        'month' => 'bulan',
    ],

];
