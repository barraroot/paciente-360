<?php

declare(strict_types=1);

namespace App\Jobs\Compliance;

use App\Domain\Messaging\Audio\Inbound\Models\AudioTranscription;
use App\Events\Compliance\AudioRawPurged;
use App\Listeners\Privacy\PurgePatientAudioOnConsentTranscricaoRevoked;
use App\Models\Paciente;
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
 * **T212 (Fase 18 — Polish, FR-055c)** — purge retroativo dos áudios brutos
 * de UM paciente específico após revogação do consent `Transcricao`.
 *
 * Diferente de {@see PurgeExpiredAudioRawJob}: este NÃO espera o cutoff
 * temporal — apaga TUDO que ainda existe além do prazo padrão, imediatamente,
 * no momento em que o paciente diz "não autorizo mais".
 *
 * Disparado por {@see PurgePatientAudioOnConsentTranscricaoRevoked}.
 *
 * Fila `privacy`.
 */
final class PurgePatientExtendedAudioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $patientId,
    ) {
        $this->onQueue('privacy');
    }

    public function handle(): void
    {
        $retentionDays = (int) config('messaging.audio.retention.default_days', 90);
        $cutoff = Carbon::now()->subDays($retentionDays);

        $purged = 0;

        AudioTranscription::query()
            ->where('tenant_id', $this->tenantId)
            ->with(['media', 'message.conversation'])
            ->whereHas('message.conversation', fn ($q) => $q->where('patient_id', $this->patientId))
            ->whereHas('media', fn ($q) => $q
                ->where('created_at', '<', $cutoff)
                ->whereNull('media_purged_at')
                ->whereNotNull('storage_path'))
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use (&$purged): void {
                foreach ($chunk as $transcription) {
                    $media = $transcription->media;
                    if ($media === null) {
                        continue;
                    }
                    if ($this->purgeMedia($media)) {
                        $purged++;
                    }
                }
            });

        event(new AudioRawPurged(
            tenantId: $this->tenantId,
            patientId: $this->patientId,
            audioCount: $purged,
            reason: 'consent_revoked',
        ));
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
            Log::warning('compliance.purge_patient_audio.media_failed', [
                'media_id' => $media->id,
                'patient_id' => $this->patientId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Utilitário usado pelo controller para previsão (`audios_to_purge` no
     * response do revoke, contract §3) — count rápido sem disparar o job.
     */
    public static function countTargets(int $tenantId, int $patientId): int
    {
        $retentionDays = (int) config('messaging.audio.retention.default_days', 90);
        $cutoff = Carbon::now()->subDays($retentionDays);

        return AudioTranscription::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('message.conversation', fn ($q) => $q->where('patient_id', $patientId))
            ->whereHas('media', fn ($q) => $q
                ->where('created_at', '<', $cutoff)
                ->whereNull('media_purged_at')
                ->whereNotNull('storage_path'))
            ->count();
    }

    /**
     * Quando o `Paciente` existe somente como instância (não persisted), o
     * `patient_id` pode vir do `Paciente::id`. Helper para o tipo seguro.
     */
    public static function fromPaciente(Paciente $paciente): self
    {
        return new self(
            tenantId: (int) $paciente->tenant_id,
            patientId: (int) $paciente->id,
        );
    }
}
