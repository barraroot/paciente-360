<?php

declare(strict_types=1);

namespace App\Console\Commands\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\CampaignDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * **T172 (Fase 8 — Lote C US-9.1)** — Cron every 5min (`campaigns:dispatch-scheduled`).
 *
 * Detecta campanhas em `status=scheduled AND scheduled_for <= now()` e
 * enfileira o dispatch via `CampaignDispatcher`. Scope `readyToDispatch`
 * já filtra com PARTIAL INDEX idx_campaigns_scheduled_dispatch.
 *
 * Idempotência: re-execução é segura — `CampaignDispatcher::dispatch()`
 * valida status válido antes; campanhas já em dispatching/completed/canceled
 * são puladas com RuntimeException catch.
 */
final class DispatchScheduledCampaignsCommand extends Command
{
    protected $signature = 'campaigns:dispatch-scheduled {--dry-run}';

    protected $description = 'Detecta campanhas agendadas (scheduled_for <= now()) e enfileira dispatch via pipeline.';

    public function handle(CampaignDispatcher $dispatcher): int
    {
        $dryRun = $this->option('dry-run');

        $ready = Campaign::query()->readyToDispatch()->get();

        $this->info(sprintf('Campanhas prontas para dispatch: %d %s', $ready->count(), $dryRun ? '[DRY-RUN]' : ''));

        if ($dryRun) {
            foreach ($ready as $c) {
                $this->line(sprintf('  - #%d %s (scheduled_for=%s)', $c->id, $c->name, $c->scheduled_for?->toIso8601String()));
            }

            return self::SUCCESS;
        }

        foreach ($ready as $campaign) {
            try {
                $dispatcher->dispatch($campaign);
                $this->line(sprintf('  ✓ #%d %s — enfileirada', $campaign->id, $campaign->name));
            } catch (\Throwable $e) {
                Log::warning('campaigns.scheduled.dispatch_failed', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error(sprintf('  ✗ #%d falhou: %s', $campaign->id, $e->getMessage()));
            }
        }

        return self::SUCCESS;
    }
}
