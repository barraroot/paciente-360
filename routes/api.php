<?php

use App\Http\Controllers\Api\V1\Audit\AuditLogsController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Billing\AiUsageController;
use App\Http\Controllers\Api\V1\Billing\CheckoutController;
use App\Http\Controllers\Api\V1\Billing\PlansController;
use App\Http\Controllers\Api\V1\Billing\SubscriptionController;
use App\Http\Controllers\Api\V1\Onboarding\OnboardingController;
use App\Http\Controllers\Api\V1\Tenant\CurrentTenantController;
use App\Http\Controllers\Api\V1\Tenant\RegisterController as TenantRegisterController;
use App\Http\Controllers\Api\V1\Users\InvitationsController;
use App\Http\Controllers\Api\V1\Users\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — `/api/v1`
|--------------------------------------------------------------------------
|
| Todas as rotas API ficam atrás do prefix `api/v1` (ver
| `bootstrap/app.php`). O middleware `ResolveTenant` (T042) é aplicado
| globalmente ao grupo: o tenant é resolvido pelo subdomínio do request
| antes de qualquer Controller rodar.
|
| Slugs reservados (`crm`, `admin`, `www`, etc.) bypassam o middleware
| — rotas públicas (cadastro, super admin) ficam aqui também.
|
*/

// Rota dummy de smoke-test para o middleware `ResolveTenant`. Será
// substituída por endpoints reais (login, me, register) nos próximos
// lotes. Mantida para o gate `TenantIsolationTest`.
Route::get('/_ping', function () {
    return app()->bound('tenant')
        ? response(app('tenant')->slug, 200)
        : response()->json(['tenant' => null], 200);
});

// T050 — Rota dummy autenticada para Sanctum::actingAs (SanctumStatefulTest).
// Retorna id, email e tenant_id do usuário autenticado.
Route::middleware('auth:sanctum')->get('/_me', fn (Request $request) => response()->json(
    $request->user()->only(['id', 'email', 'tenant_id'])
));

// US-2.1 — Login de usuário interno (T103).
// Substituiu a rota dummy do Lote G. Rate limit por IP+host (throttle:login)
// e rejeição de tenant suspenso (tenant.not-suspended) aplicados aqui.
Route::post('/auth/login', LoginController::class)
    ->middleware(['throttle:login', 'tenant.not-suspended'])
    ->name('auth.login');

// US-2.1 — Logout e endpoint /me (T105). Exigem autenticação Sanctum.
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', LogoutController::class)->name('auth.logout');
    Route::get('/auth/me', MeController::class)->name('auth.me');
});

// US-1.1 — Cadastro público de tenant (T143). Servido apenas em hosts
// públicos (`crm.lvh.me` em dev, `crm.com.br` em prod) — o middleware
// `ResolveTenant` ignora `public_hosts` e segue direto.
Route::post('/tenants/register', TenantRegisterController::class)
    ->middleware('throttle:tenant-register')
    ->name('tenants.register');

// US-1.1 — Leitura do tenant atual (T144). Resolvido pelo subdomínio via
// `ResolveTenant`. Sem auth (usado pelo SPA na inicialização).
Route::get('/tenant', CurrentTenantController::class)->name('tenant.current');

// US-2.3 — Recuperação de senha (T122).
Route::middleware('throttle:password-forgot')->group(function (): void {
    Route::post('/auth/password/forgot', [PasswordController::class, 'forgot'])
        ->name('auth.password.forgot');
});

Route::middleware('throttle:api')->group(function (): void {
    Route::post('/auth/password/reset', [PasswordController::class, 'reset'])
        ->name('auth.password.reset');
});

// US-1.2 — Wizard de onboarding do tenant (T162). Exige auth Sanctum e tenant
// não suspenso. Autorização por role (`admin-clinica`) feita via OnboardingPolicy.
Route::middleware(['auth:sanctum', 'tenant.not-suspended'])->group(function (): void {
    Route::get('/onboarding/state', [OnboardingController::class, 'state'])
        ->name('onboarding.state');
    Route::post('/onboarding/steps/{stepKey}/complete', [OnboardingController::class, 'complete'])
        ->name('onboarding.complete');
    Route::post('/onboarding/steps/{stepKey}/skip', [OnboardingController::class, 'skip'])
        ->name('onboarding.skip');
});

// US-1.3 — Billing: planos públicos (sem auth) e checkout/assinatura autenticados.
Route::get('/billing/plans', PlansController::class)->name('billing.plans');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/billing/checkout', CheckoutController::class)
        ->middleware('tenant.not-suspended')
        ->name('billing.checkout');
    Route::get('/billing/subscription', [SubscriptionController::class, 'show'])
        ->name('billing.subscription.show');
    Route::patch('/billing/subscription', [SubscriptionController::class, 'patch'])
        ->middleware('tenant.not-suspended')
        ->name('billing.subscription.patch');

    Route::get('/billing/ai-usage', [AiUsageController::class, 'show'])
        ->name('billing.ai-usage.show');

    Route::patch('/billing/ai-usage/hard-cap', [AiUsageController::class, 'patchHardCap'])
        ->middleware('tenant.not-suspended')
        ->name('billing.ai-usage.hard-cap');
});

// US-2.2 — Gestão de usuários internos (T244).
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/users', [UsersController::class, 'index'])->name('users.index');
    Route::patch('/users/{id}', [UsersController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UsersController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/invitations', [InvitationsController::class, 'index'])->name('users.invitations.index');
    Route::post('/users/invitations', [InvitationsController::class, 'store'])->name('users.invitations.store');
    Route::delete('/users/invitations/{id}', [InvitationsController::class, 'destroy'])->name('users.invitations.destroy');
});

// US-2.2 — Aceite de convite: endpoint público (sem auth), resolvido por subdomínio.
Route::post('/users/invitations/accept', [InvitationsController::class, 'accept'])
    ->middleware('throttle:api')
    ->name('users.invitations.accept');

// US-2.4 — Painel de auditoria e export CSV (T262 + T263).
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/audit-logs/export', [AuditLogsController::class, 'export'])->name('audit-logs.export');
    Route::get('/audit-logs', [AuditLogsController::class, 'index'])->name('audit-logs.index');
});
