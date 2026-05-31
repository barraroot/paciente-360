<?php

use App\Domain\Ai\Persona\Models\PersonaTestSession;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Broadcast Channel Authorization (Princípio II)
|--------------------------------------------------------------------------
|
| Canais privados de tenant. Toda autorização valida:
|   (a) pertencimento ao tenant (tenant_id do user === parâmetro do canal)
|   (b) ability `inbox.view` via Spatie team mode (canais de inbox)
|   (c) visibilidade da conversa para role `medico` (spec § 2.3 + § 11)
|
| Convenção de nomes:
|   - `tenant.{tenantId}`                      → broadcast global do tenant
|   - `tenant.{tenantId}.user.{userId}`        → broadcast por usuário
|   - `tenant.{tenantId}.inbox`                → inbox omnichannel (T041)
|   - `tenant.{tenantId}.conversa.{id}`        → sala de conversa (T041)
|
| O canal default `App.Models.User.{id}` (notificações Laravel) fica
| inalterado — valida pertencimento por id, não por tenant.
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    return $user !== null
        && $user->tenant_id !== null
        && (int) $user->tenant_id === (int) $tenantId;
});

Broadcast::channel('tenant.{tenantId}.user.{userId}', function ($user, $tenantId, $userId) {
    return $user !== null
        && $user->tenant_id !== null
        && (int) $user->tenant_id === (int) $tenantId
        && (int) $user->id === (int) $userId;
});

/*
|--------------------------------------------------------------------------
| T027 (Fase 5) — Canal de Agenda do tenant
|--------------------------------------------------------------------------
|
| `tenant.{tenantId}.agenda` — broadcast de mudanças na agenda
| (ConsultaCriada / ConsultaReagendada / ConsultaCancelada) para sync
| multi-aba do calendário FullCalendar (US-6.3).
|
| Canal privado (não-presence). Qualquer user com `appointment.view` no tenant
| pode ingressar; verificação por team_id Spatie segue o mesmo pattern do
| canal de inbox (cold-start safety com fallback explícito).
*/

Broadcast::channel('tenant.{tenantId}.agenda', function (User $user, int $tenantId) {
    if ((int) $user->tenant_id !== $tenantId) {
        return false;
    }

    $teamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

    if ($teamId !== $user->tenant_id) {
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    return $user->can('appointment.view');
});

/*
|--------------------------------------------------------------------------
| T041 — Canais Omnichannel Inbox (Fase 3)
|--------------------------------------------------------------------------
|
| `tenant.{tenantId}.inbox` — presence channel da lista de atendimento.
|   Qualquer user com `inbox.view` no tenant pode ingressar.
|   Retorna array com id+name para o presence payload.
|
| `tenant.{tenantId}.conversa.{conversationId}` — sala privada da conversa.
|   Além de `inbox.view`, verifica visibilidade por role:
|     - medico: somente conversas atribuídas a si OU cujo paciente tem
|       `profissional_responsavel_id` apontando para um `Professional`
|       cujo `user_id === $user->id` (spec § 2.3).
|     - demais roles com inbox.view: vê todas conversas do tenant.
*/

Broadcast::channel('tenant.{tenantId}.inbox', function (User $user, int $tenantId) {
    // (a) Pertencimento ao tenant
    if ((int) $user->tenant_id !== $tenantId) {
        return false;
    }

    // (b) Ability inbox.view (Spatie team mode — tenant_id como team).
    //     ResolveTenant middleware seta o team_id antes do callback; em
    //     ambiente de cold-start (fila, listener pré-roteamento) pode não
    //     estar setado — fallback explícito garante consistência.
    $teamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

    if ($teamId !== $user->tenant_id) {
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    return $user->can('inbox.view')
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
});

/*
|--------------------------------------------------------------------------
| T108 — Canal de Receituários (Fase 7)
|--------------------------------------------------------------------------
|
| `prescriptions.{tenantId}` — canal privado para broadcast de alertas
| de vencimento (ReceitaProximaDoVencimento → PrescriptionsReportRefresh).
|
| Qualquer user autenticado no tenant pode ingressar.
| Usado pelo frontend para triggerar refresh do relatório de receitas.
*/

Broadcast::channel('prescriptions.{tenantId}', function (User $user, int $tenantId) {
    return $user->tenant_id !== null && (int) $user->tenant_id === (int) $tenantId;
});

/*
|--------------------------------------------------------------------------
| T185 (Fase 18 — US6) — Canal de chat sandbox de Persona Test
|--------------------------------------------------------------------------
|
| `persona-test.{sessionId}` — canal privado por sessão de teste.
| Só o admin que abriu a sessão pode ingressar (FR-043 — isolamento).
| Sessões fechadas/expiradas também negam ingresso.
*/

Broadcast::channel('persona-test.{sessionId}', function (User $user, int $sessionId) {
    /** @var PersonaTestSession|null $session */
    $session = PersonaTestSession::query()
        ->where('id', $sessionId)
        ->first();

    if ($session === null) {
        return false;
    }

    if ((int) $session->tenant_id !== (int) $user->tenant_id) {
        return false;
    }

    if ((int) $session->admin_user_id !== (int) $user->id) {
        return false;
    }

    if ($session->status !== 'open') {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});

Broadcast::channel('tenant.{tenantId}.conversa.{conversationId}', function (User $user, int $tenantId, int $conversationId) {
    // (a) Pertencimento ao tenant
    if ((int) $user->tenant_id !== $tenantId) {
        return false;
    }

    // Spatie team mode: garante o team_id setado. O /broadcasting/auth roda só com
    // auth:sanctum + tenant.slug (sem ResolveTenant), então can()/hasRole() ficariam
    // sem contexto de team e negariam tudo. Mesmo fallback do canal .inbox acima.
    $teamId = app(PermissionRegistrar::class)->getPermissionsTeamId();

    if ($teamId !== $user->tenant_id) {
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    // (b) Ability inbox.view
    if (! $user->can('inbox.view')) {
        return false;
    }

    // (c) Conversa existe e pertence ao tenant
    /** @var Conversation|null $conversation */
    $conversation = Conversation::query()
        ->withoutTenantScope()
        ->where('id', $conversationId)
        ->where('tenant_id', $tenantId)
        ->first();

    if ($conversation === null) {
        return false;
    }

    // Spec § 2.3: médico enxerga apenas conversas atribuídas a si OU
    // cujo paciente tem como profissional responsável um Professional
    // vinculado a este usuário (Professional.user_id === $user->id).
    if ($user->hasRole('medico')) {
        $isAssigned = (int) $conversation->assigned_user_id === $user->id;

        $isPatientOwner = $conversation->patient_id !== null
            && $conversation->patient !== null
            && $conversation->patient->profissionalResponsavel !== null
            && (int) $conversation->patient->profissionalResponsavel->user_id === $user->id;

        return ($isAssigned || $isPatientOwner)
            ? ['id' => $user->id, 'name' => $user->name]
            : false;
    }

    // Demais roles com inbox.view enxergam todas as conversas do tenant
    return ['id' => $user->id, 'name' => $user->name];
});
