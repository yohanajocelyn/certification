<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\GarageController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::middleware('role:'.UserRole::Admin->value)->prefix('admin')->name('admin.')->group(function () {
        Route::resource('garages', GarageController::class)->only(['index', 'create', 'store']);
        Route::resource('tenants', TenantController::class)->only(['index', 'update']);
    });
});
