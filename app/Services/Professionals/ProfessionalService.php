<?php

declare(strict_types=1);

namespace App\Services\Professionals;

use App\Events\Professionals\ProfessionalCreated;
use App\Events\Professionals\ProfessionalUpdated;
use App\Models\Professional;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * **T016 (Spec 012)** — Orquestrador de CRUD de Professional.
 *
 * Encapsula transações + emissão de eventos auditáveis. Não trata fluxo de
 * convite (delegado para `ProfessionalInvitationService`).
 *
 * Princípio II (multi-tenant): `BelongsToTenant` no model garante escopo
 * automático em queries; service assume `tenant_id` é resolvido pelo trait.
 *
 * @see specs/012-professionals-management/research.md R8
 */
final class ProfessionalService
{
    /**
     * Cria profissional vinculado a User existente.
     *
     * Para fluxo de convite (email), use ProfessionalInvitationService.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor): Professional
    {
        return DB::transaction(function () use ($data, $actor): Professional {
            $professional = Professional::create([
                'tenant_id' => $actor->tenant_id,
                'user_id' => $data['user_id'] ?? null,
                'name' => $data['name'],
                'council_type' => $data['council_type'],
                'council_type_other' => $data['council_type_other'] ?? null,
                'council_number' => $data['council_number'],
                'council_state' => strtoupper($data['council_state']),
                'especialidade' => $data['especialidade'] ?? null,
                'is_active' => true,
            ]);

            event(new ProfessionalCreated($professional));

            return $professional->fresh(['user']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Professional $professional, array $data, User $actor): Professional
    {
        return DB::transaction(function () use ($professional, $data): Professional {
            $original = $professional->getAttributes();

            if (isset($data['council_state'])) {
                $data['council_state'] = strtoupper($data['council_state']);
            }

            $professional->fill($data);
            $changes = [];

            foreach ($professional->getDirty() as $key => $new) {
                $changes[$key] = [
                    'old' => $original[$key] ?? null,
                    'new' => $new,
                ];
            }

            $professional->save();

            if ($changes !== []) {
                event(new ProfessionalUpdated($professional, $changes));
            }

            return $professional->fresh(['user']);
        });
    }

    /**
     * Desativa (soft-delete + is_active=false). Dispara observer existente
     * (Fase 5) que enfileira ReassignOrphansJob.
     */
    public function deactivate(Professional $professional, User $actor): Professional
    {
        return DB::transaction(function () use ($professional): Professional {
            $professional->update(['is_active' => false]);
            $professional->delete(); // soft delete

            return $professional->fresh();
        });
    }

    /**
     * Reativa profissional soft-deletado. NÃO restaura pacientes já
     * reatribuídos (admin pode reatribuir manualmente se desejar).
     */
    public function activate(Professional $professional, User $actor): Professional
    {
        return DB::transaction(function () use ($professional): Professional {
            if ($professional->trashed()) {
                $professional->restore();
            }
            $original = $professional->getAttributes();
            $professional->update(['is_active' => true]);

            $changes = [
                'is_active' => ['old' => false, 'new' => true],
            ];
            event(new ProfessionalUpdated($professional, $changes));

            return $professional->fresh(['user']);
        });
    }
}
