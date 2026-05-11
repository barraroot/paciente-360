<?php

use App\Exceptions\Auth\AccountLockedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\InvalidPasswordResetTokenException;
use App\Exceptions\Auth\TenantSuspendedException;
use App\Exceptions\Billing\AlreadySubscribedException;
use App\Exceptions\Billing\BillingGatewayException;
use App\Exceptions\Billing\NoActiveSubscriptionException;
use App\Exceptions\Onboarding\StepLockedException;
use App\Exceptions\Onboarding\StepRequiredException;
use App\Exceptions\Onboarding\UnknownStepException;
use App\Exceptions\Pacientes\AnotacaoImutavelException;
use App\Exceptions\Pacientes\CpfDuplicadoException;
use App\Exceptions\Pacientes\DedupConflictException;
use App\Exceptions\Pacientes\MesclagemExpiradaException;
use App\Exceptions\Pacientes\MesclagemJaRevertidaException;
use App\Exceptions\Users\InvalidInvitationException;
use App\Exceptions\Users\LastAdminClinicaException;
use App\Exceptions\Users\PlanLimitReachedException;
use App\Http\Middleware\ApplyOverdueRestrictions;
use App\Http\Middleware\EnsureTenantNotSuspended;
use App\Http\Middleware\LogStructuredRequestData;
use App\Http\Middleware\ResolveTenant;
use App\Support\Cpf\CpfValidator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Excluir webhooks do CSRF (Stripe envia sem token).
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        // Aliases dos middlewares de tenancy. O `tenant.not-suspended`
        // é opt-in por rota (rotas de billing/onboarding aceitam tenant
        // suspenso para regularização — ver T043).
        $middleware->alias([
            'resolve.tenant' => ResolveTenant::class,
            'tenant.not-suspended' => EnsureTenantNotSuspended::class,
            'log.structured' => LogStructuredRequestData::class,
            'apply.overdue.restrictions' => ApplyOverdueRestrictions::class,
        ]);

        // T050 — Sanctum SPA stateful: injeta EnsureFrontendRequestsAreStateful
        // no grupo 'api' antes dos demais middlewares customizados.
        $middleware->statefulApi();

        // `ResolveTenant` roda em TODA request da API. Deve rodar ANTES
        // de qualquer Controller mas APÓS o middleware do Sanctum (para
        // que a sessão já esteja inicializada ao resolver o tenant —
        // Princípio II: tenant resolvido antes de qualquer Controller).
        $middleware->prependToGroup('api', ResolveTenant::class);
        $middleware->prependToGroup('web', ResolveTenant::class);

        // T052 — Log estruturado de request. Prepend ao grupo 'api' para
        // rodar após ResolveTenant (tenant já resolvido no contexto).
        // Como prependToGroup empilha LIFO, chamamos depois do
        // ResolveTenant para que LogStructured rode ANTES na cadeia real.
        $middleware->appendToGroup('api', LogStructuredRequestData::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Credenciais inválidas → 401 genérico (FR-032 — sem revelar existência).
        $exceptions->render(function (InvalidCredentialsException $e, Request $request) {
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => $e->getMessage(),
            ], 401);
        });

        // Conta bloqueada → 423 com timestamp de expiração (FR-023).
        $exceptions->render(function (AccountLockedException $e, Request $request) {
            return response()->json([
                'error' => 'account_locked',
                'message' => $e->getMessage(),
                'locked_until' => $e->lockedUntil->toIso8601String(),
            ], 423);
        });

        // Tenant suspenso → 403 (FR-005 / Princípio II).
        $exceptions->render(function (TenantSuspendedException $e, Request $request) {
            return response()->json([
                'error' => 'tenant_suspended',
                'message' => $e->getMessage(),
            ], 403);
        });

        // Token de recuperação de senha inválido/expirado/usado → 410 Gone (FR-030).
        $exceptions->render(function (InvalidPasswordResetTokenException $e, Request $request) {
            return response()->json([
                'error' => 'invalid_password_reset_token',
                'message' => $e->getMessage(),
            ], 410);
        });

        // Step de onboarding não encontrado → 404 (T162).
        $exceptions->render(function (UnknownStepException $e, Request $request) {
            return response()->json([
                'error' => 'step_not_found',
                'message' => $e->getMessage(),
            ], 404);
        });

        // Step de onboarding bloqueado → 409 (T162).
        $exceptions->render(function (StepLockedException $e, Request $request) {
            return response()->json([
                'error' => 'step_locked',
                'message' => $e->getMessage(),
            ], 409);
        });

        // Step de onboarding obrigatório não pode ser pulado → 409 (T162).
        $exceptions->render(function (StepRequiredException $e, Request $request) {
            return response()->json([
                'error' => 'step_required',
                'message' => $e->getMessage(),
            ], 409);
        });

        // Tenant já tem assinatura ativa → 409 (T185).
        $exceptions->render(function (AlreadySubscribedException $e, Request $request) {
            return response()->json([
                'error' => 'already_subscribed',
                'message' => $e->getMessage(),
            ], 409);
        });

        // Nenhuma assinatura ativa encontrada → 404 (T202).
        $exceptions->render(function (NoActiveSubscriptionException $e, Request $request) {
            return response()->json([
                'error' => 'subscription_not_found',
                'message' => $e->getMessage(),
            ], 404);
        });

        // Falha no gateway Stripe → 502 (T202).
        $exceptions->render(function (BillingGatewayException $e, Request $request) {
            return response()->json([
                'error' => 'billing_gateway_error',
                'message' => $e->getMessage(),
            ], 502);
        });

        // Limite de plano atingido → 409 (FR-027, T243).
        $exceptions->render(function (PlanLimitReachedException $e, Request $request) {
            return response()->json([
                'error' => 'plan_limit_reached',
                'message' => $e->getMessage(),
                'limit' => $e->limit,
            ], 409);
        });

        // Token de convite inválido/expirado/já usado → 410 Gone (FR-025, T243).
        $exceptions->render(function (InvalidInvitationException $e, Request $request) {
            return response()->json([
                'error' => 'invalid_invitation',
                'message' => $e->getMessage(),
            ], 410);
        });

        // Último admin-clinica → 409 (FR-029, T244).
        $exceptions->render(function (LastAdminClinicaException $e, Request $request) {
            return response()->json([
                'error' => 'last_admin_clinica',
                'message' => $e->getMessage(),
            ], 409);
        });

        // Anotação imutável → 409 (T029 — Fase 2 CRM).
        $exceptions->render(function (AnotacaoImutavelException $e, Request $request) {
            return response()->json([
                'error' => 'anotacao_imutavel',
                'message' => $e->getMessage(),
            ], 409);
        });

        // Duplicata detectada → 409 com candidatos e ações sugeridas (T110).
        $exceptions->render(function (DedupConflictException $e, Request $request) {
            return response()->json([
                'error' => 'dedup_conflict',
                'message' => $e->getMessage(),
                'candidatos' => $e->candidatos->map(fn ($p) => [
                    'id' => $p->id,
                    'nome' => $p->nome,
                    'cpf' => $p->cpf ? CpfValidator::format($p->cpf) : null,
                    'status' => $p->status,
                ])->values()->all(),
                'acoes' => ['mesclar', 'abrir_existente'],
            ], 409);
        });

        // CPF duplicado com ignorar_duplicata=true → 422 (T110).
        $exceptions->render(function (CpfDuplicadoException $e, Request $request) {
            return response()->json([
                'error' => 'cpf_duplicado_nao_permitido',
                'message' => $e->getMessage(),
            ], 422);
        });

        // Janela de reversão de mesclagem expirada → 410 Gone (T112).
        $exceptions->render(function (MesclagemExpiradaException $e, Request $request) {
            return response()->json([
                'error' => 'mesclagem_expirada',
                'message' => $e->getMessage(),
            ], 410);
        });

        // Mesclagem já revertida → 422 (T112).
        $exceptions->render(function (MesclagemJaRevertidaException $e, Request $request) {
            return response()->json([
                'error' => 'mesclagem_ja_revertida',
                'message' => $e->getMessage(),
            ], 422);
        });
    })->create();
