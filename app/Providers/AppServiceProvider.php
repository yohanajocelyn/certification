<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

/**
 * Registers app-wide settings.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Use the app's own styled pagination links everywhere.
     */
    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');
    }
}
