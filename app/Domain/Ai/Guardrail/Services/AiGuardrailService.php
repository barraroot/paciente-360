<?php

declare(strict_types=1);

namespace App\Domain\Ai\Guardrail\Services;

use App\Domain\Ai\Guardrail\Models\AiGuardrail;

/**
 * Regras de negócio dos guardrails da clínica (FR-024/FR-025).
 * `is_active` muda apenas via activate()/deactivate(); guardrail inativo não é
 * aplicado em novas respostas (filtrado no AiGuardrailEnforcer).
 */
final class AiGuardrailService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, int $tenantId, ?int $userId): AiGuardrail
    {
        return AiGuardrail::create([
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
    public function update(AiGuardrail $guardrail, array $data, ?int $userId): AiGuardrail
    {
        unset($data['is_active'], $data['tenant_id']);

        $guardrail->fill($data);
        $guardrail->updated_by = $userId;
        $guardrail->save();

        return $guardrail->refresh();
    }

    public function activate(AiGuardrail $guardrail, ?int $userId): AiGuardrail
    {
        if (! $guardrail->is_active) {
            $guardrail->update(['is_active' => true, 'updated_by' => $userId]);
        }

        return $guardrail;
    }

    public function deactivate(AiGuardrail $guardrail, ?int $userId): AiGuardrail
    {
        if ($guardrail->is_active) {
            $guardrail->update(['is_active' => false, 'updated_by' => $userId]);
        }

        return $guardrail;
    }
}
