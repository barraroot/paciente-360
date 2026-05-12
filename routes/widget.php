<?php

use App\Http\Controllers\Widget\WidgetPublicController;
use App\Http\Middleware\ValidateWidgetOrigin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Widget Public Routes — `/widget/v1/`
|--------------------------------------------------------------------------
|
| Rotas públicas do widget embutível. Sem auth Sanctum.
|
| Protegidas por:
|  - throttle:widget-public (30 req/min/IP — Lote A T005)
|  - ValidateWidgetOrigin (exceto bundle .js — pode ser cross-origin)
|
| O bundle JS (.js) usa Access-Control-Allow-Origin: * no response.
| Os demais endpoints validam Origin contra `allowed_origins` do config.
|
*/

Route::middleware(['throttle:widget-public'])->group(function (): void {
    // Bundle JS — sem ValidateWidgetOrigin (served cross-origin para todos)
    Route::get('/widget/v1/{publicKey}.js', [WidgetPublicController::class, 'bundle'])
        ->name('widget.bundle');

    // Config, sessions, messages — com Origin validation
    Route::middleware(ValidateWidgetOrigin::class)->group(function (): void {
        Route::get('/widget/v1/{publicKey}/config', [WidgetPublicController::class, 'config'])
            ->name('widget.config');

        Route::post('/widget/v1/{publicKey}/sessions', [WidgetPublicController::class, 'startSession'])
            ->name('widget.sessions.start');

        Route::post('/widget/v1/{publicKey}/messages', [WidgetPublicController::class, 'sendMessage'])
            ->name('widget.messages.send');

        Route::get('/widget/v1/{publicKey}/messages/stream', [WidgetPublicController::class, 'messagesStream'])
            ->name('widget.messages.stream');
    });
});
