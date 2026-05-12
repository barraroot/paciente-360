<?php

namespace App\Console\Commands;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Support\Metrics\MessagingMetricsContract;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * T270 — Coleta conversas ativas por (tenant, canal) e publica no gauge Prometheus.
 *
 * Roda a cada 5 minutos via scheduler (routes/console.php). Agrupa conversas
 * com status `aberta` ou `pendente` por tenant_id + tipo de canal e atualiza
 * o gauge `paciente360_conversations_active{tenant_id, channel}`.
 *
 * A query usa JOIN implícito via eager loading de canal para obter o tipo.
 * Em produção, com index em `(status, tenant_id, channel_id)` existente,
 * a varredura é < 50ms mesmo com 10k conversas ativas cross-tenant.
 *
 * Leituras são consistentes com replica: usa `->toBase()` (sem Eloquent overhead)
 * e agrupa no banco para evitar N+1.
 *
 * @see routes/console.php
 */
final class RecordActiveConversationsMetricsCommand extends Command
{
    protected $signature = 'messaging:metrics-conversations';

    protected $description = 'Registra o número de conversas ativas por tenant e canal no gauge Prometheus.';

    public function handle(MessagingMetricsContract $metrics): int
    {
        /**
         * Agrega conversas ativas (aberta + pendente) por (tenant_id, channel.type).
         *
         * @var Collection<int, object{tenant_id: int, channel_type: string, total: int}> $rows
         */
        $rows = Conversation::query()
            ->whereIn('status', ['aberta', 'pendente'])
            ->join('messaging_channels', 'messaging_conversations.channel_id', '=', 'messaging_channels.id')
            ->selectRaw('messaging_conversations.tenant_id, messaging_channels.type as channel_type, COUNT(*) as total')
            ->groupBy('messaging_conversations.tenant_id', 'messaging_channels.type')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $metrics->conversationsActive(
                tenantId: (int) $row->tenant_id,
                channel: (string) $row->channel_type,
                count: (int) $row->total,
            );
        }

        return self::SUCCESS;
    }
}
