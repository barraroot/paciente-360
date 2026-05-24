<?php

declare(strict_types=1);

namespace App\Services\Professionals;

use App\Events\Professionals\ProfessionalCreated;
use App\Http\Resources\Professionals\ProfessionalResource;
use App\Models\Professional;
use App\Models\User;
use App\Services\Users\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * **T053 (Spec 012)** — Cria Professional via fluxo de convite por email.
 *
 * Comportamento:
 *   - Email já-é-user no tenant atual SEM `confirmed_existing_user=true`:
 *     retorna 409 com payload `{code, existing_user}` (Q2 / FR-005a).
 *   - Email já-é-user no tenant atual COM confirmação: cria Professional
 *     vinculado direto (sem novo convite).
 *   - Email é user em OUTRO tenant: 422 (Princípio II / FR-008).
 *   - Email não existe em lugar nenhum: cria Professional `is_active=false,
 *     user_id=NULL, pending_invitation_email=email` + cria Invitation
 *     (Fase 4) com role `medico`.
 *
 * @see specs/012-professionals-management/research.md R4
 */
final class ProfessionalInvitationService
{
    public function __construct(
        private readonly ProfessionalService $service,
        private readonly InvitationService $invitationService,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function createWithInvite(array $data, User $actor): JsonResponse
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $confirmedExistingUser = (bool) ($data['confirmed_existing_user'] ?? false);

        // Verifica se email já é user em algum tenant.
        $userInCurrentTenant = User::where('tenant_id', $actor->tenant_id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $userInOtherTenant = User::where('tenant_id', '!=', $actor->tenant_id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        // Princípio II — cross-tenant invite bloqueado.
        if ($userInOtherTenant) {
            return response()->json([
                'message' => trans('professionals.validation.email_in_other_tenant'),
                'errors' => ['email' => [trans('professionals.validation.email_in_other_tenant')]],
            ], 422);
        }

        // Q2 — email já-é-user-do-tenant: 409 se não confirmado.
        if ($userInCurrentTenant && ! $confirmedExistingUser) {
            return response()->json([
                'message' => trans('professionals.validation.email_already_user_requires_confirmation'),
                'code' => 'email_already_user_requires_confirmation',
                'existing_user' => [
                    'id' => $userInCurrentTenant->id,
                    'name' => $userInCurrentTenant->name,
                ],
            ], 409);
        }

        // Email já-é-user + confirmado: vincular direto (sem novo convite).
        if ($userInCurrentTenant && $confirmedExistingUser) {
            $data['user_id'] = $userInCurrentTenant->id;
            unset($data['email'], $data['confirmed_existing_user']);
            $professional = $this->service->create($data, $actor);

            return ProfessionalResource::make($professional)->response()->setStatusCode(201);
        }

        // Caso novo — cria Invitation + Professional pendente.
        return DB::transaction(function () use ($data, $email, $actor): JsonResponse {
            $professional = Professional::create([
                'tenant_id' => $actor->tenant_id,
                'user_id' => null,
                'pending_invitation_email' => $email,
                'name' => $data['name'],
                'council_type' => $data['council_type'],
                'council_type_other' => $data['council_type_other'] ?? null,
                'council_number' => $data['council_number'],
                'council_state' => strtoupper($data['council_state']),
                'especialidade' => $data['especialidade'] ?? null,
                'is_active' => false,
            ]);

            $invitation = $this->invitationService->createInvitation(
                $actor->tenant,
                $actor,
                $email,
                'medico',
            );

            event(new ProfessionalCreated($professional));

            return response()->json([
                'data' => array_merge(
                    ProfessionalResource::make($professional->fresh())->resolve(),
                    [
                        'invitation' => [
                            'id' => $invitation->id,
                            'email' => $invitation->email,
                            'status' => $invitation->status,
                            'expires_at' => $invitation->expires_at?->toIso8601String(),
                        ],
                    ],
                ),
            ], 201);
        });
    }
}
