<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Sandbox;

use Illuminate\Support\Str;

/**
 * **T057 (Fase 18 — US6, FR-040/041)** — geração de outputs sintéticos para
 * capabilities de ESCRITA quando a sessão é sandbox.
 *
 * Implementação real do neutralizer está nos `runSandbox()` de cada
 * capability (CreateOrFindLeadCapability, HoldSlotCapability,
 * UpdateLeadProfileCapability — T097/T098 da US3). Esta classe é o
 * BUILDER unificado dos outputs (centraliza estrutura e nomenclatura
 * `sandbox-*` para os IDs sintéticos).
 *
 * Capabilities de LEITURA NÃO precisam do neutralizer (sandbox lê dados
 * reais para o teste ser fiel — FR-041).
 */
final class SandboxNeutralizer
{
    /**
     * @return array<string, mixed>
     */
    public static function leadSynthetic(): array
    {
        return [
            'patient_id' => 'sandbox-'.Str::uuid(),
            'created' => true,
            'status' => 'lead',
            'sandbox' => true,
            'text' => '[SANDBOX] Lead sintético criado — nenhuma linha persistida em pacientes.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function holdSynthetic(): array
    {
        return [
            'slot_reservation_id' => 'sandbox-'.Str::uuid(),
            'sandbox' => true,
            'text' => '[SANDBOX] Hold sintético colocado — nenhuma reserva real em slot_reservations.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function profileUpdateSynthetic(string $field, string $value): array
    {
        return [
            'patient_id' => 'sandbox',
            'field_updated' => $field,
            'value_before' => null,
            'value_after' => $value,
            'curation_event_id' => 'sandbox-'.Str::uuid(),
            'sandbox' => true,
            'applied' => false,
        ];
    }
}
