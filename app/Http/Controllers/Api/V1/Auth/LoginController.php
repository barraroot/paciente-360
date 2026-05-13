<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Contracts\BearerAuthContract;
use App\Domain\Auth\Enums\MotivoLoginFalho;
use App\Domain\Auth\Events\LoginFalhouViaToken;
use App\Exceptions\Auth\AccountLockedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\TenantSuspendedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Metrics\AuthMetricsContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * Controller de login Bearer via Sanctum Personal Access Tokens (Lote D — T042).
 *
 * Substitui o controller cookie/session-based da Fase 0. Responsabilidade única:
 *  1. Validar credenciais via Hash::check (sem sessão Sanctum).
 *  2. Verificar lock de conta (failed_login_attempts ≥ 5 → AccountLockedException).
 *  3. Emitir token via `BearerAuthContract::issueToken`.
 *  4. Disparar `LoginFalhouViaToken` em caso de erro (auditoria).
 *  5. Retornar 201 com `{token, token_expires_at, user, tenant}`.
 *
 * Tenant resolution: como `users.email` é UNIQUE global (migration Lote B),
 * o tenant é resolvido diretamente pelo relacionamento `$user->tenant` sem
 * necessidade de lookup adicional.
 *
 * Rate limit: `throttle:login` aplicado na rota (5 req/min por IP+host).
 * Lock de conta: 5 tentativas falhas com senha errada → 423 via AccountLockedException.
 *
 * @group Auth
 *
 * @see App\Domain\Auth\Contracts\BearerAuthContract
 * @see App\Domain\Auth\Services\TokenIssuerService
 * @see specs/004-token-auth-migration/spec.md §FR-021, §FR-023, §FR-024
 */
final class LoginController extends Controller
{
    private const LOCKOUT_THRESHOLD = 5;

    private const LOCKOUT_MINUTES = 15;

    /**
     * Login Bearer — emite Personal Access Token para o usuário.
     *
     * @unauthenticated
     *
     * @response 201 scenario="sucesso" {"token": "paciente360_...", "token_expires_at": "2026-06-11T00:00:00+00:00", "user": {}, "tenant": {}}
     * @response 401 scenario="credenciais inválidas" {"error": "invalid_credentials", "message": "Credenciais inválidas."}
     * @response 423 scenario="conta bloqueada" {"error": "account_locked", "message": "...", "locked_until": "ISO8601"}
     */
    public function __invoke(
        LoginRequest $request,
        BearerAuthContract $issuer,
        AuthMetricsContract $metrics,
    ): JsonResponse {
        $user = User::withoutGlobalScopes()
            ->where('email', $request->email)
            ->first();

        if ($user === null) {
            Event::dispatch(new LoginFalhouViaToken(
                ip: $request->ip() ?? '0.0.0.0',
                tokenIdPrefix: null,
                path: $request->path(),
                motivo: MotivoLoginFalho::Invalid,
            ));

            $metrics->loginTotal('invalid_credentials');

            throw new InvalidCredentialsException;
        }

        // Verificar lock antes de qualquer validação de credencial.
        // Conta bloqueada retorna 423 independente da senha fornecida (FR-023).
        if ($this->isLocked($user)) {
            $metrics->loginTotal('account_locked');

            throw new AccountLockedException($user->locked_until);
        }

        if (! Hash::check($request->password, $user->password)) {
            $this->handleFailedAttempt($user);

            Event::dispatch(new LoginFalhouViaToken(
                ip: $request->ip() ?? '0.0.0.0',
                tokenIdPrefix: null,
                path: $request->path(),
                motivo: MotivoLoginFalho::Invalid,
            ));

            $metrics->loginTotal('invalid_credentials');

            throw new InvalidCredentialsException;
        }

        // Usuário desativado → 401 genérico (não vaza status — FR-032).
        // Roda APÓS verificar senha para preservar o timing da resposta entre
        // "senha errada" e "usuário desativado".
        if ($user->status !== 'active') {
            Event::dispatch(new LoginFalhouViaToken(
                ip: $request->ip() ?? '0.0.0.0',
                tokenIdPrefix: null,
                path: $request->path(),
                motivo: MotivoLoginFalho::Invalid,
            ));

            $metrics->loginTotal('invalid_credentials');

            throw new InvalidCredentialsException;
        }

        // Tenant suspenso → 403 (FR-005). Credenciais foram validadas, então é
        // seguro revelar o motivo (usuário pode ir regularizar billing).
        if ($user->tenant !== null && $user->tenant->status === 'suspended') {
            $metrics->loginTotal('tenant_suspended');

            throw new TenantSuspendedException;
        }

        // Sucesso — resetar contador de tentativas antes de emitir token.
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'first_login_at' => $user->first_login_at ?? now(),
        ])->save();

        $result = $issuer->issueToken(
            user: $user,
            name: $request->string('device_name', 'Default')->toString(),
            abilities: ['*'],
        );

        $metrics->loginTotal('success');

        return response()->json([
            'token' => $result['token'],
            'token_expires_at' => $result['expires_at']->toIso8601String(),
            'user' => UserResource::make($user),
            'tenant' => TenantResource::make($user->tenant),
        ], 201);
    }

    /**
     * Verifica se a conta está bloqueada (lock não expirou).
     */
    private function isLocked(User $user): bool
    {
        return $user->locked_until !== null && $user->locked_until->isFuture();
    }

    /**
     * Processa tentativa de login falha: incrementa contador e aplica lock
     * quando atingir o threshold de 5 tentativas.
     */
    private function handleFailedAttempt(User $user): void
    {
        $attempts = (int) $user->failed_login_attempts + 1;

        $data = ['failed_login_attempts' => $attempts];

        if ($attempts >= self::LOCKOUT_THRESHOLD) {
            $data['locked_until'] = now()->addMinutes(self::LOCKOUT_MINUTES);
        }

        $user->forceFill($data)->save();
    }
}
