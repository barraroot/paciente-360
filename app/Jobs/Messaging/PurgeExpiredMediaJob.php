<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Message\Models\MessageMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * T251 — Job mensal de purge de mídia expirada (NC-14, 1 ano de retenção).
 *
 * Para cada registro expirado: deleta arquivo do S3 e seta `media_purged_at`.
 * Log estruturado por tenant. Processamento em chunks de 100 para segurança
 * de memória em tabelas grandes.
 *
 * `withoutOverlapping()` registrado no schedule.
 */
class PurgeExpiredMediaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(): void
    {
        $cutoff = now()->subMonths(config('messaging.retention.media_months', 12));
        $totalDeleted = 0;
        $totalBytes = 0;
        $tenantStats = [];

        MessageMedia::withoutGlobalScopes()
            ->whereNull('media_purged_at')
            ->where('created_at', '<', $cutoff)
            ->chunkById(100, function ($batch) use (&$totalDeleted, &$totalBytes, &$tenantStats): void {
                foreach ($batch as $media) {
                    try {
                        Storage::disk($media->storage_disk)->delete($media->storage_path);
                    } catch (\Throwable $e) {
                        Log::warning('media.purge.s3_delete_failed', [
                            'media_id' => $media->id,
                            'storage_path' => $media->storage_path,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $media->update(['media_purged_at' => now()]);

                    $tenantId = $media->tenant_id;
                    $tenantStats[$tenantId] ??= ['deleted_count' => 0, 'total_bytes' => 0];
                    $tenantStats[$tenantId]['deleted_count']++;
                    $tenantStats[$tenantId]['total_bytes'] += $media->size_bytes;

                    $totalDeleted++;
                    $totalBytes += $media->size_bytes;
                }
            });

        foreach ($tenantStats as $tenantId => $stats) {
            Log::info('media.purge.tenant_summary', [
                'tenant_id' => $tenantId,
                'deleted_count' => $stats['deleted_count'],
                'total_bytes' => $stats['total_bytes'],
                'purged_at' => now()->toIso8601String(),
            ]);
        }

        Log::info('media.purge.completed', [
            'total_deleted' => $totalDeleted,
            'total_bytes' => $totalBytes,
            'cutoff' => $cutoff->toIso8601String(),
        ]);
    }
}
