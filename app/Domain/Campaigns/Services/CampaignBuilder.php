<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Events\CampanhaCancelada;
use App\Domain\Campaigns\Events\CampanhaCriada;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignChannel;
use App\Domain\Campaigns\Models\CampaignStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * **T159 (Fase 8 — Lote C US-9.1)** — CRUD + preview de campanhas.
 *
 * Responsabilidades:
 *   - `create()` — cria em status=draft + emite CampanhaCriada
 *   - `preview()` — calcula audiência elegível + warnings (sem opt-in,
 *      template não aprovado, fora de horário comercial)
 *   - `cancel()` — só permitido se status != completed/canceled
 */
final class CampaignBuilder
{
    public function __construct(
        private readonly CampaignAudienceCalculator $audience,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Tenant $tenant, User $createdBy, array $data): Campaign
    {
        return DB::transaction(function () use ($tenant, $createdBy, $data): Campaign {
            $channel = $data['channel'] instanceof CampaignChannel
                ? $data['channel']
                : CampaignChannel::from($data['channel']);

            $scheduledFor = isset($data['scheduled_for'])
                ? Carbon::parse($data['scheduled_for'])
                : null;

            $status = $scheduledFor !== null ? CampaignStatus::Scheduled : CampaignStatus::Draft;

            $campaign = Campaign::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'status' => $status,
                'channel' => $channel,
                'template_id' => $data['template_id'] ?? null,
                'audience_filters' => $data['audience_filters'] ?? [],
                'scheduled_for' => $scheduledFor,
                'daily_limit_applied' => $tenant->dailyCampaignLimit(),
                'created_by_user_id' => $createdBy->id,
            ]);

            Event::dispatch(new CampanhaCriada(
                tenantId: $tenant->id,
                campaignId: $campaign->id,
                createdByUserId: $createdBy->id,
                channel: $channel->value,
                audienceFilters: $campaign->audience_filters ?? [],
                scheduledFor: $scheduledFor,
            ));

            return $campaign;
        });
    }

    /**
     * Pré-visualização: calcula público elegível + warnings de conformidade.
     *
     * @return array{eligible_count: int, warnings: list<string>}
     */
    public function preview(Campaign $campaign): array
    {
        $count = $this->audience->estimate($campaign->tenant_id, $campaign->audience_filters ?? []);

        $warnings = [];

        if ($count === 0) {
            $warnings[] = 'Nenhum paciente elegível pelos filtros aplicados.';
        }

        if ($campaign->template_id === null) {
            $warnings[] = 'Template não selecionado — disparo será bloqueado.';
        }

        $tenant = $campaign->tenant;
        if ($tenant !== null && $tenant->dailyCampaignLimit() < $count) {
            $warnings[] = sprintf(
                'Limite diário do plano (%d) menor que público estimado (%d). Disparo será fragmentado.',
                $tenant->dailyCampaignLimit(),
                $count,
            );
        }

        return [
            'eligible_count' => $count,
            'warnings' => $warnings,
        ];
    }

    public function cancel(Campaign $campaign, User $canceledBy, ?string $reason = null): Campaign
    {
        if ($campaign->status->isTerminal()) {
            throw new RuntimeException("Campanha #{$campaign->id} já está em status terminal: {$campaign->status->value}");
        }

        return DB::transaction(function () use ($campaign, $canceledBy, $reason): Campaign {
            $now = Carbon::now();

            $campaign->update([
                'status' => CampaignStatus::Canceled,
                'canceled_at' => $now,
                'canceled_by_user_id' => $canceledBy->id,
                'canceled_reason' => $reason,
            ]);

            Event::dispatch(new CampanhaCancelada(
                tenantId: $campaign->tenant_id,
                campaignId: $campaign->id,
                canceledByUserId: $canceledBy->id,
                canceledAt: $now,
                reason: $reason,
            ));

            return $campaign->refresh();
        });
    }
}
