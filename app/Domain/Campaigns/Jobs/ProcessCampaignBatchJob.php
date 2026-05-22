<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Jobs;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignRecipientStatus;
use App\Domain\Campaigns\Models\CampaignStatus;
use App\Domain\Campaigns\Services\CampaignComplianceGate;
use App\Domain\Campaigns\Services\MetaTemplateStatusChecker;
use App\Models\Paciente;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * **T161 (Fase 8 — Lote C US-9.1)** — Processa batch de recipients da campanha.
 *
 * Itera em chunks de 100 recipients pending, aplica CampaignComplianceGate
 * por recipient + enfileira SendCampaignMessageJob nos aprovados. Re-enfileira
 * para si mesmo se ainda houver recipients pending após o chunk corrente.
 *
 * Fila `campaigns` (concurrency 10 — config/horizon.php).
 */
final class ProcessCampaignBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public string $queue = 'campaigns';

    public function __construct(
        public readonly int $campaignId,
    ) {}

    public function handle(
        CampaignComplianceGate $gate,
        MetaTemplateStatusChecker $templateChecker,
    ): void {
        /** @var Campaign|null $campaign */
        $campaign = Campaign::query()->find($this->campaignId);

        if ($campaign === null || $campaign->status->isTerminal()) {
            return;
        }

        $tenant = $campaign->tenant;
        if ($tenant === null) {
            return;
        }

        $templateMeta = $campaign->template_id !== null
            ? $templateChecker->getOrCreateMeta($campaign->template_id)
            : null;

        $alreadyDispatched = (int) CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('dispatched_at', '>=', Carbon::today())
            ->whereIn('status', [
                CampaignRecipientStatus::Sent->value,
                CampaignRecipientStatus::Delivered->value,
                CampaignRecipientStatus::Read->value,
                CampaignRecipientStatus::Responded->value,
            ])
            ->count();

        $pending = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->limit(100)
            ->get();

        if ($pending->isEmpty()) {
            // Sem mais recipients — marca completed.
            $totalDispatched = (int) CampaignRecipient::query()
                ->where('campaign_id', $campaign->id)
                ->whereIn('status', [CampaignRecipientStatus::Sent->value, CampaignRecipientStatus::Delivered->value])
                ->count();
            $totalBlocked = (int) CampaignRecipient::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', CampaignRecipientStatus::Blocked->value)
                ->count();

            $campaign->update([
                'status' => CampaignStatus::Completed,
                'total_dispatched' => $totalDispatched,
                'total_blocked' => $totalBlocked,
            ]);

            Log::info('campaigns.batch.completed', [
                'campaign_id' => $campaign->id,
                'tenant_id' => $tenant->id,
                'total_dispatched' => $totalDispatched,
                'total_blocked' => $totalBlocked,
            ]);

            return;
        }

        $processedThisRun = 0;
        foreach ($pending as $recipient) {
            $patient = Paciente::query()->find($recipient->patient_id);
            if ($patient === null) {
                $recipient->update([
                    'status' => CampaignRecipientStatus::Blocked,
                    'blocked_reason' => 'no_reachable_channel',
                    'dispatched_at' => Carbon::now(),
                ]);
                continue;
            }

            $result = $gate->evaluate(
                patient: $patient,
                tenant: $tenant,
                templateMeta: $templateMeta,
                alreadyDispatchedToday: $alreadyDispatched + $processedThisRun,
            );

            if (! $result->passed) {
                $recipient->update([
                    'status' => CampaignRecipientStatus::Blocked,
                    'blocked_reason' => $result->blockReason,
                    'dispatched_at' => Carbon::now(),
                ]);
                $this->logDispatch($campaign, $patient->id, 'blocked', $result->blockReason, $result->details);
                continue;
            }

            // Enfileira o envio real.
            Bus::dispatch(new SendCampaignMessageJob(
                campaignId: $campaign->id,
                recipientId: $recipient->id,
            ));

            $processedThisRun++;
        }

        // Se ainda há recipients pending após este chunk, re-enfileira para si mesmo.
        $stillPending = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->exists();

        if ($stillPending) {
            Bus::dispatch(new self(campaignId: $campaign->id));
        }
    }

    /**
     * @param  array<string, mixed>|string|null  $details
     */
    private function logDispatch(Campaign $campaign, int $patientId, string $result, ?string $blockReason, mixed $details): void
    {
        DB::table('campaign_dispatch_log')->insert([
            'tenant_id' => $campaign->tenant_id,
            'campaign_id' => $campaign->id,
            'patient_id' => $patientId,
            'attempted_at' => Carbon::now(),
            'result' => $result,
            'block_reason' => $blockReason,
            'details' => is_string($details) ? json_encode(['details' => $details]) : json_encode($details),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
