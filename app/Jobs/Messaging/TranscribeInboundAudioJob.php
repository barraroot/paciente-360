<?php

declare(strict_types=1);

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Audio\Inbound\Services\AudioTranscriptionService;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Permission\PermissionRegistrar;

/**
 * **T129 (Fase 18 — US4)** — STT do áudio inbound, na fila dedicada
 * `transcription` (Horizon — config/horizon.php).
 *
 * Despachado pelo `ProcessInboundMessageJob` (T130) ANTES de qualquer outro
 * listener (lead onboarding, coalescência) quando a mensagem é áudio em canal
 * suportado (WhatsApp/Instagram Direct — FR-024). Widget de site é skipped
 * pelo despachador (FR-026).
 *
 * Após sucesso: re-dispatch `MensagemRecebida` para que a coalescência
 * (US1) e o lead onboarding (US2) processem o texto transcrito como uma
 * mensagem normal. Essa re-emissão é segura porque o `Message` foi
 * atualizado com `content_type='text'` + `body=<transcrição>` —
 * idempotência por id da mensagem garante que listeners não duplicam.
 *
 * tries=1: falhas viram marker visível na conversa (FR-027), não retry
 * (evita gastar tokens Whisper).
 */
final class TranscribeInboundAudioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $messageId,
        public readonly int $tenantId,
    ) {
        $this->onQueue('transcription');
    }

    public function handle(AudioTranscriptionService $service): void
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        app()->instance('tenant', $tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $message = Message::query()
            ->where('tenant_id', $this->tenantId)
            ->with(['media', 'conversation'])
            ->find($this->messageId);

        if ($message === null) {
            return;
        }

        $service->transcribeInbound($message);

        // Re-emite o evento para que coalescência/lead onboarding processem
        // a versão TEXTO do áudio. Listeners já existentes são idempotentes
        // por message_id (US1 INCR/coalesce; US2 tem UNIQUE em pacientes).
        if ($message->conversation !== null) {
            event(new MensagemRecebida($message->refresh(), $message->conversation));
        }
    }
}
