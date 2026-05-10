<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\RouteServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Sentry\Laravel\ServiceProvider as SentryServiceProvider;

$providers = [
    AppServiceProvider::class,
    EventServiceProvider::class,
    AdminPanelProvider::class,
    RouteServiceProvider::class,
];

if (class_exists(TelescopeApplicationServiceProvider::class)) {
    $providers[] = TelescopeServiceProvider::class;
}

if (env('SENTRY_LARAVEL_DSN') || env('SENTRY_DSN')) {
    $providers[] = SentryServiceProvider::class;
}

return $providers;
