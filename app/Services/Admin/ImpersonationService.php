<?php

namespace App\Services\Admin;

use App\Events\Admin\ImpersonationEnded;
use App\Events\Admin\ImpersonationStarted;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;

/**
 * Gerencia o modo de impersonação do Super Admin (T286).
 *
 * O Super Admin pode assumir a identidade de um usuário de tenant para
 * diagnóstico/suporte. Cada início e fim de sessão de impersonação é
 * auditado. A sessão expira automaticamente após 60 minutos via
 * `EnforceImpersonationExpiry` middleware.
 */
final class ImpersonationService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Inicia a impersonação do usuário alvo, logando a ação de auditoria.
     */
    public function start(User $superAdmin, Tenant $targetTenant, User $targetUser): void
    {
        Event::dispatch(new ImpersonationStarted(
            superAdmin: $superAdmin,
            targetTenant: $targetTenant,
            targetUser: $targetUser,
        ));

        Session::put('impersonation', [
            'original_user_id' => $superAdmin->id,
            'target_user_id' => $targetUser->id,
            'expires_at' => now()->addMinutes(60)->toIso8601String(),
        ]);

        Auth::guard('web')->login($targetUser);
    }

    /**
     * Encerra a impersonação, restaura o Super Admin original e audita.
     *
     * Retorna o usuário original restaurado.
     */
    public function stop(User $currentUser): User
    {
        /** @var array{original_user_id: int, target_user_id: int, expires_at: string}|null $impersonation */
        $impersonation = Session::get('impersonation');

        if ($impersonation === null) {
            return $currentUser;
        }

        /** @var User|null $originalUser */
        $originalUser = User::withTrashed()->find($impersonation['original_user_id']);

        Session::forget('impersonation');

        if ($originalUser !== null) {
            Event::dispatch(new ImpersonationEnded(
                superAdmin: $originalUser,
                targetUser: $currentUser,
            ));

            Auth::guard('web')->login($originalUser);

            return $originalUser;
        }

        return $currentUser;
    }

    /**
     * Verifica se a sessão atual está em modo de impersonação.
     */
    public function isImpersonating(): bool
    {
        return Session::has('impersonation');
    }
}
