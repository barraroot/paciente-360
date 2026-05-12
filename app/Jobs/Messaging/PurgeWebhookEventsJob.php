<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Infrastructure\Webhook\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * T258 — Job mensal de purge de eventos de webhook expirados (30 dias padrão).
 *
 * Webhook events são infra log (não auditáveis por LGPD) — hard delete.
 * Processamento em chunks de 1000 para performance em tabelas grandes.
 */
class PurgeWebhookEventsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(): void
    {
        $cutoff = now()->subDays(config('messaging.retention.webhook_events_days', 30));
        $totalDeleted = 0;

        WebhookEvent::withoutGlobalScopes()
            ->where('received_at', '<', $cutoff)
            ->chunkById(1000, function ($batch) use (&$totalDeleted): void {
                $ids = $batch->pluck('id');
                WebhookEvent::withoutGlobalScopes()->whereIn('id', $ids)->delete();
                $totalDeleted += $ids->count();
            });

        Log::info('webhook_events.purge.completed', [
            'total_deleted' => $totalDeleted,
            'cutoff' => $cutoff->toIso8601String(),
            'purged_at' => now()->toIso8601String(),
        ]);
    }
}
