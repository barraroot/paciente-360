<?php

namespace App\Services\Users;

use App\Events\Users\InvitationAccepted;
use App\Events\Users\InvitationCreated;
use App\Events\Users\InvitationRevoked;
use App\Exceptions\Users\InvalidInvitationException;
use App\Exceptions\Users\PlanLimitReachedException;
use App\Jobs\Email\SendInvitationEmailJob;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Serviço de convites de usuários internos (US-2.2, FR-025–027).
 *
 * Regras de negócio:
 *  - Role allowlist: admin-clinica, medico, atendente, recepcionista, financeiro.
 *  - Limite de plano: ativos + pendentes ≤ max_users.
 *  - Token: 64 chars hex (32 bytes); somente hash armazenado (LGPD).
 *  - Accept: scan de invitations pending do tenant + Hash::check.
 */
final class InvitationService
{
    /** @var list<string> */
    public const ALLOWED_ROLES = [
        'admin-clinica',
        'medico',
        'atendente',
        'recepcionista',
        'financeiro',
    ];

    /**
     * Cria um convite e dispara o e-mail ao destinatário.
     *
     * @throws PlanLimitReachedException quando o limite de usuários do plano é atingido.
     * @throws \InvalidArgumentException quando a role não está na allowlist.
     * @throws \RuntimeException quando e-mail já tem convite pending ou usuário ativo.
     */
    public function createInvitation(
        Tenant $tenant,
        User $inviter,
        string $email,
        string $role,
    ): Invitation {
        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            throw new \InvalidArgumentException("Role '{$role}' não é permitida para convites.");
        }

        $this->assertPlanLimit($tenant);

        $tokenClaro = bin2hex(random_bytes(32));

        try {
            $invitation = Invitation::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'email' => $email,
                'intended_role' => $role,
                'inviter_user_id' => $inviter->id,
                'token_hash' => Hash::make($tokenClaro),
                'status' => 'pending',
                'expires_at' => now()->addHours((int) config('auth.invitations.expire', 24)),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new \RuntimeException(
                'Já existe um convite pendente para este e-mail neste tenant.',
                422
            );
        }

        SendInvitationEmailJob::dispatch($invitation->id, $tokenClaro);

        Event::dispatch(new InvitationCreated($invitation));

        return $invitation;
    }

    /**
     * Aceita um convite identificado pelo token claro.
     *
     * Estratégia de lookup: itera pelas invitations `pending` do tenant
     * e verifica o hash. No volume esperado do MVP (dezenas por tenant),
     * o custo é aceitável.
     *
     * @throws InvalidInvitationException quando token não é encontrado, expirou ou já foi usado.
     */
    public function acceptInvitation(
        string $tokenClaro,
        string $name,
        string $password,
        Tenant $tenant,
    ): User {
        $invitation = $this->findPendingByToken($tokenClaro, $tenant);

        if ($invitation === null) {
            throw new InvalidInvitationException;
        }

        if ($invitation->expires_at->isPast()) {
            $invitation->status = 'expired';
            $invitation->save();
            throw new InvalidInvitationException(__('users.invitation.invalid'));
        }

        // Cria o usuário.
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $invitation->email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        // Atribui a role via Spatie (team mode).
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole($invitation->intended_role);

        // Marca convite como aceito.
        $invitation->status = 'accepted';
        $invitation->accepted_at = now();
        $invitation->save();

        Event::dispatch(new InvitationAccepted($invitation, $user));

        // Faz login do usuário (apenas quando há sessão disponível — API stateful).
        Auth::guard('web')->login($user);

        $request = request();

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return $user;
    }

    /**
     * Revoga um convite pendente.
     *
     * @throws InvalidInvitationException quando o convite não está em status `pending`.
     */
    public function revokeInvitation(Invitation $invitation): void
    {
        if ($invitation->status !== 'pending') {
            throw new InvalidInvitationException(__('users.invitation.invalid'));
        }

        $invitation->status = 'revoked';
        $invitation->revoked_at = now();
        $invitation->save();

        Event::dispatch(new InvitationRevoked($invitation));
    }

    /**
     * Verifica se o tenant atingiu o limite de usuários do plano.
     *
     * Contagem: usuários `active` + invitations `pending` ≤ max_users do plano
     * (ou limite trial sem plano = config `auth.invitations.trial_limit`, default 5).
     *
     * @throws PlanLimitReachedException
     */
    private function assertPlanLimit(Tenant $tenant): void
    {
        $maxUsers = $tenant->plan?->max_users
            ?? (int) config('auth.invitations.trial_limit', 5);

        $activeUsers = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->count();

        $pendingInvitations = Invitation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->count();

        if (($activeUsers + $pendingInvitations) >= $maxUsers) {
            throw new PlanLimitReachedException($maxUsers);
        }
    }

    /**
     * Busca convite `pending` do tenant pelo token claro via `Hash::check`.
     */
    private function findPendingByToken(string $tokenClaro, Tenant $tenant): ?Invitation
    {
        $pending = Invitation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->get();

        foreach ($pending as $invitation) {
            if (Hash::check($tokenClaro, $invitation->token_hash)) {
                return $invitation;
            }
        }

        return null;
    }
}
