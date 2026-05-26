<?php

declare(strict_types=1);

namespace App\Domain\Ai\Matrix\Services;

use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Persona\Models\AiPersona;
use Illuminate\Support\Collection;

/**
 * Resolve a matriz Persona × Canal de uma clínica (FR-009..FR-011a).
 *
 * Habilitação de IA por canal é DERIVADA (clarificação 2026-05-26): o canal
 * está habilitado se, e somente se, houver ≥1 persona ATIVA vinculada e ativa
 * na matriz para aquele canal. Não há flag separada.
 */
final class AiMatrixService
{
    /**
     * Personas ativas vinculadas (ativas na matriz) a um canal, em ordem
     * determinística (por id) — base do round-robin.
     *
     * @return Collection<int, AiPersona>
     */
    public function activePersonasForChannel(int $tenantId, string $channelType): Collection
    {
        return AiPersona::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('channels', function ($q) use ($channelType): void {
                $q->where('channel_type', $channelType)->where('is_active', true);
            })
            ->orderBy('id')
            ->get();
    }

    public function isChannelAiEnabled(int $tenantId, string $channelType): bool
    {
        return AiPersonaChannel::query()
            ->where('tenant_id', $tenantId)
            ->where('channel_type', $channelType)
            ->where('is_active', true)
            ->whereHas('persona', fn ($q) => $q->where('is_active', true))
            ->exists();
    }

    /**
     * Configuração de IA por canal (para a tela e o endpoint de consulta).
     *
     * @return array{channel_type: string, ai_enabled: bool, personas: list<array{id: int, name: string}>}
     */
    public function channelConfig(int $tenantId, string $channelType): array
    {
        $personas = $this->activePersonasForChannel($tenantId, $channelType);

        return [
            'channel_type' => $channelType,
            'ai_enabled' => $personas->isNotEmpty(),
            'personas' => $personas->map(fn (AiPersona $p): array => [
                'id' => $p->id,
                'name' => $p->name,
            ])->values()->all(),
        ];
    }

    /**
     * Estado completo da matriz (personas × canais) para a UI.
     *
     * @return array<int, array{ai_persona_id: int, name: string, channels: array<string, bool>}>
     */
    public function matrix(int $tenantId): array
    {
        $channelTypes = ['whatsapp', 'instagram', 'web'];

        $personas = AiPersona::query()
            ->where('tenant_id', $tenantId)
            ->with('channels')
            ->orderBy('name')
            ->get();

        return $personas->map(function (AiPersona $persona) use ($channelTypes): array {
            $cells = [];
            foreach ($channelTypes as $type) {
                $cell = $persona->channels->firstWhere('channel_type', $type);
                $cells[$type] = $cell !== null && $cell->is_active;
            }

            return [
                'ai_persona_id' => $persona->id,
                'name' => $persona->name,
                'is_active' => $persona->is_active,
                'channels' => $cells,
            ];
        })->all();
    }

    /**
     * Aplica células da matriz (upsert por persona+canal).
     *
     * @param list<array{ai_persona_id: int, channel_type: string, is_active: bool}> $cells
     */
    public function syncCells(int $tenantId, array $cells): void
    {
        foreach ($cells as $cell) {
            // Co-tenancy: só personas do tenant atual (global scope) são elegíveis.
            $persona = AiPersona::find($cell['ai_persona_id']);
            if ($persona === null) {
                continue;
            }

            AiPersonaChannel::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'ai_persona_id' => $persona->id,
                    'channel_type' => $cell['channel_type'],
                ],
                ['is_active' => $cell['is_active']],
            );
        }
    }
}
