<?php

declare(strict_types=1);

namespace App\Domain\Ai\Distribution\Services;

use App\Domain\Ai\Distribution\Models\AiChannelDistributionState;
use App\Domain\Ai\Matrix\Services\AiMatrixService;
use App\Domain\Ai\Persona\Models\AiPersona;
use Illuminate\Support\Facades\DB;

/**
 * Round-robin persistente por (tenant, canal) — FR-012..FR-016.
 *
 * Determinístico e isolado por clínica+canal. O estado é serializado com
 * `lockForUpdate` para que seleções concorrentes do mesmo tenant+canal não
 * dupliquem a posição (G3).
 */
final class AiPersonaSelectorService
{
    public function __construct(private readonly AiMatrixService $matrix) {}

    /**
     * Seleciona a próxima persona ativa do canal, avançando o ciclo.
     * Retorna null se não houver persona elegível (IA não atende — FR-011).
     */
    public function selectForNewConversation(int $tenantId, string $channelType): ?AiPersona
    {
        $personas = $this->matrix->activePersonasForChannel($tenantId, $channelType);

        if ($personas->isEmpty()) {
            return null;
        }

        // Garante a linha de estado antes do lock (UNIQUE protege a corrida de criação).
        AiChannelDistributionState::firstOrCreate(
            ['tenant_id' => $tenantId, 'channel_type' => $channelType],
            ['last_position' => -1],
        );

        return DB::transaction(function () use ($tenantId, $channelType, $personas): AiPersona {
            $state = AiChannelDistributionState::query()
                ->where('tenant_id', $tenantId)
                ->where('channel_type', $channelType)
                ->lockForUpdate()
                ->first();

            $count = $personas->count();
            $nextPosition = ($state->last_position + 1) % $count;

            /** @var AiPersona $persona */
            $persona = $personas->values()->get($nextPosition);

            $state->update([
                'last_position' => $nextPosition,
                'last_ai_persona_id' => $persona->id,
            ]);

            return $persona;
        });
    }
}
