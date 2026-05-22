<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Jobs;

use App\Domain\Campaigns\Events\MensagemCampanhaEnviada;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignRecipientStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * **T162 (Fase 8 — Lote C US-9.1)** — Envia 1 mensagem de campanha (AC-9.1.3).
 *
 * Idempotente via DB UNIQUE `(campaign_id, patient_id)` na tabela
 * `campaign_recipients` (cria nada novo — apenas atualiza). Em retry,
 * a verificação `status === Pending` impede re-envio.
 *
 * **MVP — envio real DEFERRED**: lookup de `messaging_channels` ativo +
 * chamada Twilio/Meta Graph API fica para integração de produção. Por
 * enquanto, marca status=sent + emite evento + grava external_message_id
 * mock para validar o pipeline E2E.
 */
final class SendCampaignMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public string $queue = 'campaigns';

    public function __construct(
        public readonly int $campaignId,
        public readonly int $recipientId,
    ) {}

    public function handle(): void
    {
        /** @var CampaignRecipient|null $recipient */
        $recipient = CampaignRecipient::query()->find($this->recipientId);

        if ($recipient === null || $recipient->status !== CampaignRecipientStatus::Pending) {
            // Recipient já processado — idempotente.
            return;
        }

        /** @var Campaign|null $campaign */
        $campaign = Campaign::query()->find($this->campaignId);
        if ($campaign === null) {
            return;
        }

        // DEFERRED: integração real com messaging service da Fase 3.
        // Por enquanto, simula envio bem-sucedido com external_message_id mock.
        $externalMessageId = 'mock_'.Str::random(24);

        $recipient->update([
            'status' => CampaignRecipientStatus::Sent,
            'dispatched_at' => Carbon::now(),
            'external_message_id' => $externalMessageId,
        ]);

        Event::dispatch(new MensagemCampanhaEnviada(
            tenantId: $campaign->tenant_id,
            campaignId: $campaign->id,
            patientId: $recipient->patient_id,
            channel: $campaign->channel->value,
            status: 'sent',
            blockedReason: null,
        ));

        Log::info('campaigns.message.sent', [
            'campaign_id' => $campaign->id,
            'patient_id' => $recipient->patient_id,
            'external_message_id' => $externalMessageId,
        ]);
    }
}
