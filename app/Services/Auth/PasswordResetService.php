<?php

namespace App\Services\Auth;

use App\Events\Auth\PasswordResetCompleted;
use App\Events\Auth\PasswordResetRequested;
use App\Exceptions\Auth\InvalidPasswordResetTokenException;
use App\Jobs\Email\SendPasswordResetEmailJob;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * Serviço de recuperação de senha (US-2.3 — FR-030, FR-031, FR-032, FR-033).
 *
 * Implementação direta sem `Password::broker` porque o broker padrão do
 * Laravel filtra apenas por e-mail; neste sistema a identidade é
 * `(email, tenant_id)` — dois usuários em tenants distintos podem ter o
 * mesmo e-mail (edge case da spec).
 *
 * Fluxo de token:
 *  - `requestReset()`: gera token em claro (64 hex chars), armazena o
 *    **hash** em `password_reset_tokens`, envia o token em claro por e-mail.
 *  - `reset()`: recupera a linha, confere hash, validade (60 min) e
 *    deleta após uso (token de uso único — FR-030).
 *
 * @see App\Jobs\Email\SendPasswordResetEmailJob
 * @see App\Mail\ResetPasswordMail
 * @see App\Notifications\PasswordChangedNotification
 */
final class PasswordResetService
{
    /** Validade do token em minutos (alinhado com config('auth.passwords.users.expire')). */
    private const TOKEN_EXPIRE_MINUTES = 60;

    /**
     * Solicita a recuperação de senha para o e-mail informado.
     *
     * Responde silenciosamente quando o e-mail não existe no tenant (FR-032).
     * O audit event diferencia os casos via payload.user_found.
     */
    public function requestReset(string $email): void
    {
        $tenant = app('tenant');

        $user = User::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->where('status', 'active')
            ->first();

        if ($user === null) {
            Event::dispatch(new PasswordResetRequested(
                user: null,
                emailAttempted: $email,
                userFound: false,
            ));

            return;
        }

        // Token em claro: 32 bytes aleatórios → 64 chars hexadecimais.
        $tokenInClaro = bin2hex(random_bytes(32));

        // UPSERT: substitui token anterior caso exista (edge case: solicitações
        // múltiplas — apenas o último token vale, FR spec edge case).
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email, 'tenant_id' => $tenant->id],
            ['token' => Hash::make($tokenInClaro), 'created_at' => now()],
        );

        SendPasswordResetEmailJob::dispatch($user->id, $tokenInClaro);

        Event::dispatch(new PasswordResetRequested(
            user: $user,
            emailAttempted: $email,
            userFound: true,
        ));
    }

    /**
     * Redefine a senha do usuário com o token de recuperação.
     *
     * @throws InvalidPasswordResetTokenException quando o token é inválido,
     *                                            expirado ou já foi utilizado.
     */
    public function reset(string $email, string $tokenInClaro, string $newPassword): User
    {
        $tenant = app('tenant');

        $row = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($row === null) {
            throw new InvalidPasswordResetTokenException;
        }

        if (! Hash::check($tokenInClaro, $row->token)) {
            throw new InvalidPasswordResetTokenException;
        }

        $createdAt = Carbon::parse($row->created_at);

        if (! $createdAt->addMinutes(self::TOKEN_EXPIRE_MINUTES)->isFuture()) {
            throw new InvalidPasswordResetTokenException;
        }

        $user = User::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if ($user === null) {
            throw new InvalidPasswordResetTokenException;
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        // Consome o token — uso único (FR-030).
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('tenant_id', $tenant->id)
            ->delete();

        $user->notify(new PasswordChangedNotification($tenant));

        Event::dispatch(new PasswordResetCompleted($user));

        return $user;
    }
}
