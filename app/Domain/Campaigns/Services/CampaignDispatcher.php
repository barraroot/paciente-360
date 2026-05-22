<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Events\CampanhaDisparada;
use App\Domain\Campaigns\Jobs\ProcessCampaignBatchJob;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignRecipientStatus;
use App\Domain\Campaigns\Models\CampaignStatus;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * **T160 (Fase 8 — Lote C US-9.1)** — Despacha campanha (AC-9.1.2).
 *
 * Pipeline:
 *   1. Validar campanha em status válido (draft ou scheduled).
 *   2. Calcular audiência elegível via CampaignAudienceCalculator.
 *   3. Snapshot dos destinatários em `campaign_recipients` (status=pending,
 *      UNIQUE garante idempotência absoluta — re-dispatch é no-op).
 *   4. Transição: status → dispatching.
 *   5. Enfileirar `ProcessCampaignBatchJob` que processa em chunks de 100
 *      aplicando o CampaignComplianceGate por recipient.
 *   6. Emitir CampanhaDisparada (audit).
 */
final class CampaignDispatcher
{
    public function __construct(
        private readonly CampaignAudienceCalculator $audience,
    ) {}

    public function dispatch(Campaign $campaign): void
    {
        if (! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true)) {
            throw new RuntimeException("Campanha #{$campaign->id} não pode ser disparada — status {$campaign->status->value}.");
        }

        $tenant = $campaign->tenant ?? Tenant::query()->find($campaign->tenant_id);
        if ($tenant === null) {
            throw new RuntimeException("Tenant #{$campaign->tenant_id} não encontrado.");
        }

        DB::transaction(function () use ($campaign, $tenant): void {
            $now = Carbon::now();

            // Snapshot recipients — INSERT idempotente via UNIQUE.
            $eligibleCount = 0;
            $rows = [];

            $this->audience->iterate($tenant->id, $campaign->audience_filters ?? [])
                ->each(function ($patient) use ($campaign, $tenant, $now, &$rows, &$eligibleCount): void {
                    $rows[] = [
                        'tenant_id' => $tenant->id,
                        'campaign_id' => $campaign->id,
                        'patient_id' => $patient->id,
                        'status' => CampaignRecipientStatus::Pending->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $eligibleCount++;

                    if (count($rows) >= 500) {
                        // Insert em batch — onConflict mantém o existente (idempotência).
                        CampaignRecipient::query()->insertOrIgnore($rows);
                        $rows = [];
                    }
                });

            if ($rows !== []) {
                CampaignRecipient::query()->insertOrIgnore($rows);
            }

            $campaign->update([
                'status' => CampaignStatus::Dispatching,
                'dispatched_at' => $now,
                'total_eligible' => $eligibleCount,
                'daily_limit_applied' => $tenant->dailyCampaignLimit(),
            ]);

            Event::dispatch(new CampanhaDisparada(
                tenantId: $tenant->id,
                campaignId: $campaign->id,
                dispatchedAt: $now,
                totalEligible: $eligibleCount,
            ));

            // Enfileira o batch processor — atravessa o Gate por recipient.
            Bus::dispatch(new ProcessCampaignBatchJob(campaignId: $campaign->id));
        });
    }
}
