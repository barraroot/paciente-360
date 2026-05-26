<?php

declare(strict_types=1);

namespace App\Domain\Ai\Persona\Services;

use App\Domain\Ai\Persona\Models\AiPersona;

/**
 * Regras de negócio das personas de IA (FR-004..FR-008).
 * `is_active` muda apenas via activate()/deactivate() — nunca no update.
 */
final class AiPersonaService
{
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
        }

        return $persona;
    }
}
