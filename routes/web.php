<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\GarageController;
use App\Http\Controllers\Admin\RentalController as AdminRentalController;
use App\Http\Controllers\Admin\RentalExtensionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\VehicleController;
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
        Route::resource('garages', GarageController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::resource('tenants', TenantController::class)->only(['index', 'update']);
        Route::resource('rentals', AdminRentalController::class)->only(['index']);
        Route::get('/rentals/{rental}/extensions/preview', [RentalExtensionController::class, 'preview'])
            ->name('rentals.extensions.preview');
        Route::resource('rentals.extensions', RentalExtensionController::class)->only(['create', 'store']);

        Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
            Route::get('/monthly', 'monthly')->name('monthly');
            Route::get('/daily', 'daily')->name('daily');
            Route::get('/garage-types', 'garageTypes')->name('garage-types');
            Route::get('/tenants', 'tenants')->name('tenants');
        });
    });

    Route::middleware('role:'.UserRole::Tenant->value)->group(function () {
        Route::get('/rentals/preview', [RentalController::class, 'preview'])->name('rentals.preview');
        Route::resource('rentals', RentalController::class)->only(['index', 'create', 'store']);
        Route::resource('vehicles', VehicleController::class)->only(['index']);
    });
});
