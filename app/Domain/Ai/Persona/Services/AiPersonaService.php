<?php

declare(strict_types=1);

namespace App\Domain\Ai\Persona\Services;

use App\Domain\Ai\Assignment\Services\AiConversationAssignmentService;
use App\Domain\Ai\Persona\Models\AiPersona;

/**
 * Regras de negócio das personas de IA (FR-004..FR-008).
 * `is_active` muda apenas via activate()/deactivate() — nunca no update.
 */
final class AiPersonaService
{
    public function __construct(private readonly AiConversationAssignmentService $assignments) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, int $tenantId, ?int $userId): AiPersona
    {
        return AiPersona::create([
            ...$data,
            'tenant_id' => $tenantId,
            'is_active' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(AiPersona $persona, array $data, ?int $userId): AiPersona
    {
        unset($data['is_active'], $data['tenant_id']);

        $persona->fill($data);
        $persona->updated_by = $userId;
        $persona->save();

        return $persona->refresh();
    }

    public function activate(AiPersona $persona, ?int $userId): AiPersona
    {
        if (! $persona->is_active) {
            $persona->update(['is_active' => true, 'updated_by' => $userId]);
        }

        return $persona;
    }

    public function deactivate(AiPersona $persona, ?int $userId): AiPersona
    {
        if ($persona->is_active) {
            $persona->update(['is_active' => false, 'updated_by' => $userId]);

            // FR-016a/G10b — conversas ativas desta persona são reatribuídas
            // (a persona já está inativa, então não é reescolhida pelo round-robin).
            $this->assignments->reassignAwayFromPersona($persona);
        }

        return $persona;
    }

    /**
     * Remove a persona (soft delete) reatribuindo antes as conversas ativas,
     * para que nenhuma atribuição fique apontando a uma persona inexistente.
     */
    public function delete(AiPersona $persona): void
    {
        if ($persona->is_active) {
            $persona->update(['is_active' => false]);
        }

        $this->assignments->reassignAwayFromPersona($persona);

        $persona->delete();
    }
}
