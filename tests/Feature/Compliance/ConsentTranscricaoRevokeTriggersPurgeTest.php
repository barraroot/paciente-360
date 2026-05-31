<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Services\ConsentService;
use App\Jobs\Compliance\PurgePatientExtendedAudioJob;
use App\Listeners\Privacy\PurgePatientAudioOnConsentTranscricaoRevoked;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * **T213 (Fase 18 — Polish, FR-055c)** — revoke do consent `Transcricao`
 * enfileira `PurgePatientExtendedAudioJob` para o paciente específico.
 *
 * Cobre o listener {@see PurgePatientAudioOnConsentTranscricaoRevoked}
 * + filtragem por finalidade.
 */
final class ConsentTranscricaoRevokeTriggersPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoke_transcricao_enqueues_patient_purge_job(): void
    {
        Bus::fake([PurgePatientExtendedAudioJob::class]);

        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);
        $paciente = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        $service = app(ConsentService::class);

        // Grant primeiro (precisa estar ativo para revoke fazer efeito).
        $service->record(
            patient: $paciente,
            channel: 'panel',
            finalidade: ConsentFinalidade::Transcricao,
        );

        // Revoke dispara ConsentimentoRevogado → listener enfileira o job.
        $service->revoke(
            patient: $paciente,
            finalidade: ConsentFinalidade::Transcricao,
            channel: 'panel',
            scope: 'all',
        );

        Bus::assertDispatched(
            PurgePatientExtendedAudioJob::class,
            fn (PurgePatientExtendedAudioJob $job): bool => $job->tenantId === $tenant->id
                && $job->patientId === $paciente->id,
        );
    }

    public function test_revoke_other_finalidade_does_not_trigger_purge(): void
    {
        Bus::fake([PurgePatientExtendedAudioJob::class]);

        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);
        $paciente = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        $service = app(ConsentService::class);

        // Marketing — finalidade não relacionada a áudio.
        $service->record(
            patient: $paciente,
            channel: 'panel',
            finalidade: ConsentFinalidade::Marketing,
        );

        $service->revoke(
            patient: $paciente,
            finalidade: ConsentFinalidade::Marketing,
            channel: 'panel',
            scope: 'all',
        );

        Bus::assertNotDispatched(PurgePatientExtendedAudioJob::class);
    }
}
