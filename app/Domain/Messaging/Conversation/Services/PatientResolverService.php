<?php

namespace App\Domain\Messaging\Conversation\Services;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Events\ConversaVinculadaAPaciente;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * T082b — Serviço de resolução de paciente para conversas inbound.
 *
 * Estratégia por canal:
 *  - WhatsApp: busca por `telefone_primario` (E.164 sem prefixo whatsapp:)
 *
 * Deduplicação:
 *  - 1 resultado → vincula e dispara ConversaVinculadaAPaciente
 *  - 0 resultados → retorna null (conversa vai para "Não identificado")
 *  - >1 resultados → retorna null (colisão, não vincula automaticamente)
 *
 * @see specs/003-omnichannel-inbox/spec.md — FR-011
 */
final class PatientResolverService
{
    /**
     * Resolve o paciente para um canal inbound pelo identificador externo.
     *
     * @throws ModelNotFoundException nunca — retorna null em caso de falha
     */
    public function resolveForInboundChannel(Channel $channel, string $externalThreadId): ?Paciente
    {
        return match ($channel->type) {
            'whatsapp' => $this->resolveByPhone($channel, $externalThreadId),
            default => null,
        };
    }

    /**
     * Busca paciente por telefone E.164 no tenant do canal.
     */
    private function resolveByPhone(Channel $channel, string $phone): ?Paciente
    {
        // phone já chega sem o prefixo "whatsapp:"
        $e164 = $phone;

        /** @var Collection<int, Paciente> $matches */
        $matches = Paciente::withoutGlobalScopes()
            ->where('tenant_id', $channel->tenant_id)
            ->where(function ($query) use ($e164): void {
                $query->where('telefone_primario', $e164)
                    ->orWhere('telefone_primario_normalizado', $e164);
            })
            ->whereNull('anonimizado_em')
            ->get();

        $count = $matches->count();

        Log::info('PatientResolverService.resolveByPhone', [
            'channel_id' => $channel->id,
            'tenant_id' => $channel->tenant_id,
            'phone' => substr($e164, 0, 5).'***',
            'matches' => $count,
        ]);

        if ($count === 1) {
            $paciente = $matches->first();

            return $paciente;
        }

        if ($count > 1) {
            Log::warning('PatientResolverService: múltiplos pacientes com mesmo telefone', [
                'channel_id' => $channel->id,
                'tenant_id' => $channel->tenant_id,
                'count' => $count,
            ]);
        }

        return null;
    }
}
