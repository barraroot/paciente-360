<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Message\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * T257 — Job mensal de purge de mensagens expiradas (FR-039, 2 anos de retenção).
 *
 * Não apaga o registro — preserva metadata (tenant_id, conversation_id, direction,
 * content_type, status, timestamps) para fins de auditoria e analytics.
 * Apenas zera o conteúdo: body, body_searchable, body_preview.
 *
 * Processamento em chunks de 500 para segurança de memória.
 */
class PurgeExpiredMessagesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(): void
    {
        $cutoff = now()->subMonths(config('messaging.retention.message_months', 24));
        $totalPurged = 0;
        $tenantStats = [];
        $placeholder = '[mensagem arquivada em '.now()->format('Y-m-d').']';

        Message::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('body_preview')  // ainda não purgadas
            ->chunkById(500, function ($batch) use (&$totalPurged, &$tenantStats, $placeholder): void {
                foreach ($batch as $message) {
                    $message->update([
                        'body' => null,
                        'body_searchable' => null,
                        'body_preview' => $placeholder,
                    ]);

                    $tenantId = $message->tenant_id;
                    $tenantStats[$tenantId] ??= 0;
                    $tenantStats[$tenantId]++;
                    $totalPurged++;
                }
            });

        foreach ($tenantStats as $tenantId => $count) {
            Log::info('messages.purge.tenant_summary', [
                'tenant_id' => $tenantId,
                'purged_count' => $count,
                'purged_at' => now()->toIso8601String(),
            ]);
        }

        Log::info('messages.purge.completed', [
            'total_purged' => $totalPurged,
            'cutoff' => $cutoff->toIso8601String(),
        ]);
    }
}
