<?php

namespace App\Services\Auth;

use App\Events\Auth\Actions;
use App\Events\Auth\LoginFailed;
use App\Events\Auth\LoginSucceeded;
use App\Exceptions\Auth\AccountLockedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * Serviço de autenticação de usuários internos (US-2.1 — FR-021 a FR-024).
 *
 * Encapsula o fluxo completo de login: verificação de lock, busca do usuário,
 * validação de credenciais, incremento de tentativas, lock após threshold,
 * atualização de timestamps e disparo de eventos auditáveis.
 *
 * O tenant já foi resolvido pelo middleware `ResolveTenant` antes de chegar
 * aqui; `app('tenant')` está disponível no container.
 *
 * @see App\Http\Controllers\Api\V1\Auth\LoginController
 * @see App\Events\Auth\LoginSucceeded
 * @see App\Events\Auth\LoginFailed
 */
final class AuthenticationService
{
    private const LOCKOUT_THRESHOLD = 5;

    private const LOCKOUT_MINUTES = 15;

    /**
     * Tenta autenticar o usuário com as credenciais da request.
     *
     * @throws AccountLockedException quando a conta está bloqueada.
     * @throws InvalidCredentialsException quando credenciais são inválidas.
     */
    public function attemptLogin(LoginRequest $request): User
    {
        $tenant = app('tenant');

        $user = User::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $request->email)
            ->first();

        // Verificar lock antes de qualquer validação de credencial — conta bloqueada
        // retorna 423 independente da senha fornecida (FR-023).
        if ($user !== null && $this->isLocked($user)) {
            throw new AccountLockedException($user->locked_until);
        }

        // Credenciais inválidas: usuário não existe, está desabilitado ou senha errada.
        if (! $this->hasValidCredentials($user, $request->password)) {
            $this->handleFailedAttempt($user, $request->email);
            throw new InvalidCredentialsException;
        }

        // Sucesso — resetar contador, atualizar timestamps e autenticar.
        $this->onLoginSuccess($user, $request);

        return $user;
    }

    /**
     * Verifica se a conta está bloqueada (lock não expirou).
     */
    private function isLocked(User $user): bool
    {
        return $user->locked_until !== null && $user->locked_until->isFuture();
    }

    /**
     * Valida as credenciais: usuário deve existir, estar ativo e a senha ser correta.
     */
    private function hasValidCredentials(?User $user, string $password): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->status !== 'active') {
            return false;
        }

        return Hash::check($password, $user->password);
    }

    /**
     * Processa tentativa de login falha: incrementa contador, aplica lock se
     * atingiu o threshold e dispara eventos auditáveis.
     */
    private function handleFailedAttempt(?User $user, string $emailAttempted): void
    {
        if ($user === null) {
            // Usuário não existe — audita com user_id null para rastreabilidade.
            Event::dispatch(new LoginFailed(
                user: null,
                action: Actions::USER_LOGIN_FAILED,
                extraPayload: ['email_attempted' => $emailAttempted],
            ));

            return;
        }

        $attempts = (int) $user->failed_login_attempts + 1;
        $user->failed_login_attempts = $attempts;

        $action = Actions::USER_LOGIN_FAILED;

        if ($attempts >= self::LOCKOUT_THRESHOLD) {
            $user->locked_until = now()->addMinutes(self::LOCKOUT_MINUTES);
            $action = Actions::USER_LOGIN_LOCKED;
        }

        $user->save();

        Event::dispatch(new LoginFailed(user: $user, action: $action));
    }

    /**
     * Processa login bem-sucedido: reseta falhas, atualiza timestamps,
     * autentica na sessão e dispara evento.
     */
    private function onLoginSuccess(User $user, LoginRequest $request): void
    {
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->last_login_at = now();
        $user->first_login_at ??= now();
        $user->save();

        Auth::guard('web')->login($user, $request->boolean('remember'));

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        Event::dispatch(new LoginSucceeded($user));
    }
}
