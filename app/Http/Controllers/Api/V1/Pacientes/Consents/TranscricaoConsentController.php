<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pacientes\Consents;

use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Models\ConsentRecord;
use App\Domain\Privacy\Models\ConsentState;
use App\Domain\Privacy\Services\ConsentService;
use App\Http\Controllers\Controller;
use App\Jobs\Compliance\PurgePatientExtendedAudioJob;
use App\Listeners\Privacy\PurgePatientAudioOnConsentTranscricaoRevoked;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * **T208 (Fase 18 — Polish, FR-055a/b/c)** — endpoints dedicados ao consent
 * `Transcricao` por paciente (retenção prolongada de áudio bruto).
 *
 * Reusa a infra da Fase 8 ({@see ConsentService}); este controller só
 * embrulha em URLs ergonômicas + retorna o payload rico do contract
 * (description, retention windows, audios_to_purge).
 *
 * O fluxo grant→armazenar prolongado / revoke→purge é amarrado pelo listener
 * {@see PurgePatientAudioOnConsentTranscricaoRevoked}
 * (T212) — este controller NÃO purga diretamente, só conta `audios_to_purge`
 * para feedback imediato ao operador.
 *
 * Permissions:
 *   - GET    → privacy.view
 *   - POST/grant + revoke → privacy.view (revoke é ação manual, ainda granular
 *     na mesma ability per Fase 8 pattern).
 *
 * Middleware: `auth:sanctum`+`tenant.slug`+`tenant.not-suspended` herdados do grupo.
 *
 * @group Privacy — Consent Transcricao
 */
final class TranscricaoConsentController extends Controller
{
    public function __construct(
        private readonly ConsentService $consent,
    ) {}

    /**
     * Retorna o estado atual do consent Transcricao do paciente.
     *
     * @authenticated
     */
    public function show(Paciente $paciente): JsonResponse
    {
        Gate::authorize('privacy.view');
        $this->assertSameTenant($paciente);

        $record = ConsentRecord::query()
            ->where('patient_id', $paciente->id)
            ->where('finalidade', ConsentFinalidade::Transcricao)
            ->orderByDesc('created_at')
            ->first();

        return response()->json([
            'data' => $this->buildPayload($paciente, $record),
        ]);
    }

    /**
     * Concede o consent Transcricao (permite retenção prolongada de áudio).
     *
     * @authenticated
     */
    public function grant(Request $request, Paciente $paciente): JsonResponse
    {
        Gate::authorize('privacy.view');
        $this->assertSameTenant($paciente);

        $channel = (string) ($request->input('channel') ?? 'panel');

        $record = $this->consent->record(
            patient: $paciente,
            channel: $channel,
            finalidade: ConsentFinalidade::Transcricao,
            evidenceMessageId: $request->integer('evidence_message_id') ?: null,
            evidenceSnapshot: null,
            termsVersion: (string) ($request->input('terms_version') ?? '1.0'),
        );

        return response()->json([
            'data' => $this->buildPayload($paciente, $record),
        ]);
    }

    /**
     * Revoga o consent Transcricao e enfileira purge retroativo dos áudios
     * brutos do paciente além do prazo padrão.
     *
     * @authenticated
     */
    public function revoke(Request $request, Paciente $paciente): JsonResponse
    {
        Gate::authorize('privacy.view');
        $this->assertSameTenant($paciente);

        $channel = (string) ($request->input('channel') ?? 'panel');

        $audiosToPurge = PurgePatientExtendedAudioJob::countTargets(
            tenantId: $paciente->tenant_id,
            patientId: $paciente->id,
        );

        $revoked = $this->consent->revoke(
            patient: $paciente,
            finalidade: ConsentFinalidade::Transcricao,
            channel: $channel,
            evidenceMessageId: $request->integer('evidence_message_id') ?: null,
            scope: 'all',
        );

        $record = $revoked[0] ?? ConsentRecord::query()
            ->where('patient_id', $paciente->id)
            ->where('finalidade', ConsentFinalidade::Transcricao)
            ->orderByDesc('created_at')
            ->first();

        $payload = $this->buildPayload($paciente, $record);
        $payload['purge_job_enqueued'] = $revoked !== [];
        $payload['audios_to_purge'] = $audiosToPurge;

        return response()->json(['data' => $payload]);
    }

    private function assertSameTenant(Paciente $paciente): void
    {
        if (! app()->bound('tenant') || $paciente->tenant_id !== app('tenant')->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Paciente $paciente, ?ConsentRecord $record): array
    {
        $isGranted = $record !== null && $record->state === ConsentState::Granted;

        return [
            'finalidade' => ConsentFinalidade::Transcricao->value,
            'granted' => $isGranted,
            'granted_at' => $isGranted ? $record->granted_at?->toIso8601String() : null,
            'revoked_at' => $record !== null && $record->state === ConsentState::Revoked
                ? $record->revoked_at?->toIso8601String()
                : null,
            'channel' => $record?->channel,
            'description' => 'Permite armazenamento prolongado de áudios para fins de auditoria interna e melhoria do atendimento. Sem esse consentimento, áudios são apagados no prazo padrão e apenas a transcrição em texto permanece.',
            'default_retention_days' => (int) config('messaging.audio.retention.default_days', 90),
            'extended_retention_days' => (int) config('messaging.audio.retention.extended_days', 365),
        ];
    }
}
