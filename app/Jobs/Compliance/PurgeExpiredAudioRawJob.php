<?php

declare(strict_types=1);

namespace App\Jobs\Compliance;

use App\Domain\Messaging\Audio\Inbound\Models\AudioTranscription;
use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Services\ConsentService;
use App\Events\Compliance\AudioRawPurged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * **T210 (Fase 18 — Polish, FR-055a)** — purga LGPD-aware dos áudios brutos
 * (arquivos no storage) cuja janela de retenção padrão já expirou.
 *
 * Critério:
 *   - `messaging_message_media.created_at < now - default_retention_days`
 *     (default 90 — vem de `messaging.audio.retention.default_days`);
 *   - paciente NÃO tem `ConsentFinalidade::Transcricao` ativo (FR-055a/b);
 *   - mídia ainda NÃO foi purgada (`media_purged_at IS NULL`);
 *   - vínculo via `AudioTranscription.media_id` → `MessageMedia.message_id` →
 *     `Message.conversation_id` → `Conversation.patient_id`.
 *
 * Efeitos por mídia purgada:
 *   - `Storage::disk($media->storage_disk)->delete($media->storage_path)`;
 *   - `media->update(['storage_path' => null, 'media_purged_at' => now()])`;
 *   - **a transcrição em texto PERMANECE** (não toca `transcribed_text`) —
 *     LGPD permite texto sem voz biométrica indefinidamente.
 *
 * Emite `AudioRawPurged` (auditável) UMA vez por execução, com `audio_count`
 * total.
 *
 * Fila `privacy` (concurrencies baixas, timeout alto — operação batch).
 * Agendado em `app/Console/Kernel.php` daily 04:00 (T211).
 *
 * Sem efeitos colaterais em falha parcial — chunk-by-chunk + try/catch por
 * media; falha de uma não interrompe as demais.
 */
final class PurgeExpiredAudioRawJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly ?int $tenantId = null,
    ) {
        $this->onQueue('privacy');
    }

    public function handle(ConsentService $consent): void
    {
        $retentionDays = (int) config('messaging.audio.retention.default_days', 90);
        $cutoff = Carbon::now()->subDays($retentionDays);

        $purgedByTenant = []; // tenant_id => count

        AudioTranscription::query()
            ->when($this->tenantId !== null, fn ($q) => $q->where('tenant_id', $this->tenantId))
            ->with(['media', 'message.conversation'])
            ->whereHas('media', function ($q) use ($cutoff): void {
                $q->where('created_at', '<', $cutoff)
                    ->whereNull('media_purged_at')
                    ->whereNotNull('storage_path');
            })
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($consent, &$purgedByTenant): void {
                foreach ($chunk as $transcription) {
                    $media = $transcription->media;
                    if ($media === null) {
                        continue;
                    }

                    $patientId = $transcription->message?->conversation?->patient_id;
                    if ($patientId !== null && $consent->hasGranted($patientId, ConsentFinalidade::Transcricao)) {
                        continue; // paciente autorizou retenção prolongada
                    }

                    if ($this->purgeMedia($media)) {
                        $purgedByTenant[$transcription->tenant_id] = ($purgedByTenant[$transcription->tenant_id] ?? 0) + 1;
                    }
                }
            });

        foreach ($purgedByTenant as $tenantId => $count) {
            event(new AudioRawPurged(
                tenantId: (int) $tenantId,
                patientId: null,
                audioCount: $count,
                reason: 'expired_no_consent',
            ));
        }
    }

    private function purgeMedia($media): bool
    {
        try {
            if ($media->storage_path !== null && $media->storage_disk !== null) {
                Storage::disk($media->storage_disk)->delete($media->storage_path);
            }

            $media->forceFill([
                'storage_path' => null,
                'media_purged_at' => Carbon::now(),
            ])->save();

            return true;
        } catch (Throwable $e) {
            Log::warning('compliance.purge_expired_audio.media_failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
