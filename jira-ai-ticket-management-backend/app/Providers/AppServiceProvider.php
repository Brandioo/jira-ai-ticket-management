<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Services are auto-resolved by Laravel for this small education project.
    }

    public function boot(): void
    {
        // No global boot logic required.
    }
}
