<?php

use App\Http\Controllers\MetricsController;
use App\Http\Controllers\Webhooks\GoogleCalendarWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes — shell único da SPA Vue 3 (T016)
|--------------------------------------------------------------------------
|
| Todas as rotas públicas e autenticadas do tenant devolvem o mesmo
| shell `resources/views/app.blade.php`. O Vue Router (HTML5 mode,
| ver `resources/js/router/index.js`) é responsável por renderizar
| a tela correta no client. As rotas backend são definidas em
| `routes/api.php` (entram nas próximas fases).
|
| Slugs reservados (NÃO declarar Route::view aqui — outros pacotes
| montam):
|   - /admin       → Filament (T118+).
|   - /telescope   → Telescope (T013, dev/staging only).
|   - /horizon     → Horizon (entra com a configuração de queues).
|   - /stripe/webhook → Cashier::routes()/HandleStripeWebhooks (T072).
|   - /up          → health-check do framework (bootstrap/app.php).
|
*/

// Raiz: o Vue Router faz redirect / -> /panel.
Route::view('/', 'app');

// US-1.1 — cadastro público de tenant (tela renderizada pela SPA).
// Aliases: ambos resolvem para a mesma rota Vue (/register-clinic).
Route::view('/cadastro', 'app');
Route::view('/register-clinic', 'app');
Route::view('/login', 'app');
Route::view('/forgot-password', 'app');
Route::view('/reset-password/{token}', 'app');
Route::view('/accept-invitation', 'app');

// SPA autenticada. /panel sem sufixo + catch-all para HTML5 mode.
Route::view('/panel', 'app');
Route::view('/panel/{any}', 'app')->where('any', '.*');

// T184 — Webhook do Stripe. Fora do CSRF e ResolveTenant.
// O Cashier injeta VerifyWebhookSignature automaticamente quando
// STRIPE_WEBHOOK_SECRET está configurado.
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook'])
    ->name('webhooks.stripe')
    ->withoutMiddleware([VerifyCsrfToken::class, ResolveTenant::class]);

// T164 (Fase 5) — Webhook Google Calendar Watch (push notifications, R3).
// Sempre retorna 200 (evita retry storms). Validado por HMAC do header
// X-Goog-Channel-Token via middleware ValidateGoogleChannelToken.
Route::post('/webhooks/google-calendar/{channelId}', GoogleCalendarWebhookController::class)
    ->middleware('validate.google.channel.token')
    ->name('webhooks.google-calendar')
    ->withoutMiddleware([VerifyCsrfToken::class, ResolveTenant::class]);

// T271 — Endpoint Prometheus /metrics.
// Protegido por rede privada em produção (firewall/nginx allow_list).
// Sem autenticação Sanctum — scrapers Prometheus não enviam cookies.
// Content-Type: text/plain; version=0.0.4 (formato Prometheus text).
Route::get('/metrics', MetricsController::class)
    ->name('metrics')
    ->withoutMiddleware([VerifyCsrfToken::class, ResolveTenant::class]);
