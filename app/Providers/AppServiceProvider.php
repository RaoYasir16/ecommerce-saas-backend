<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\CauserResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Automatically resolve causer across Sanctum guards (merchant user or super admin)
        if (class_exists(CauserResolver::class)) {
            app(CauserResolver::class)->resolveUsing(function () {
                return auth('user')->user() ?? auth('admin')->user() ?? auth()->user();
            });
        }
    }
}
