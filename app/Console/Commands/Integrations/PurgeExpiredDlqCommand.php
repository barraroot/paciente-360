<?php

declare(strict_types=1);

namespace App\Console\Commands\Integrations;

use App\Domain\Integrations\Models\WebhookDeadLetter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * **T202 (Fase 8 — Lote D US-11.1)** — Purga DLQ expirado (>30d).
 *
 * Schedule daily 03:00 BRT (T009 — routes/console.php).
 */
final class PurgeExpiredDlqCommand extends Command
{
    protected $signature = 'integrations:purge-expired-dlq {--dry-run}';

    protected $description = 'Remove rows expiradas (>30d) da Dead Letter Queue.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = WebhookDeadLetter::query()->where('expires_at', '<', Carbon::now());
        $count = $query->count();

        if ($dryRun) {
            $this->info("[dry-run] Removeria {$count} rows expiradas do DLQ.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        Log::info('integrations.purge_expired_dlq', ['deleted' => $deleted]);

        $this->info("Removidas {$deleted} rows expiradas do DLQ.");

        return self::SUCCESS;
    }
}
