<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Domain\Privacy\Events\ConsentimentoRevogado;
use App\Domain\Privacy\Listeners\ProcessSairCommandListener;
use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Domain\Privacy\Services\ConsentService;
use App\Models\Paciente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T036 (Fase 8 — Lote A US-13.1)** — Q25 / AC-13.1.3.
 *
 * Cenários validados:
 *   1. `/sair` revoga APENAS marketing — preserva transacional e pesquisa.
 *   2. `/sair tudo` revoga marketing + transacional + pesquisa.
 *   3. Listener é case-insensitive e tolera trailing whitespace.
 *   4. Mensagem que não inicia com `/sair` é ignorada (sem revoke).
 */
class ConsentRevocationViaSairTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_sair_only_revokes_marketing_preserving_transacional(): void
    {
        Event::fake([ConsentimentoRevogado::class]);

        [$tenant, ] = $this->tenantAndUserForRole('clinica-sair-only', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        /** @var ConsentService $service */
        $service = app(ConsentService::class);

        // Setup — paciente com 2 consentimentos ativos.
        $service->record($patient, 'whatsapp', ConsentFinalidade::Transacional);
        $service->record($patient, 'whatsapp', ConsentFinalidade::Marketing);

        // Paciente envia "/sair" via WhatsApp.
        /** @var ProcessSairCommandListener $listener */
        $listener = app(ProcessSairCommandListener::class);
        $revoked = $listener->process($patient, 'whatsapp', '/sair', messageId: 4242);

        $this->assertCount(1, $revoked, 'Apenas o marketing deve ter sido revogado.');

        $this->assertFalse(
            $service->hasGranted($patient->id, ConsentFinalidade::Marketing),
            'Marketing deve estar revogado após /sair.',
        );

        $this->assertTrue(
            $service->hasGranted($patient->id, ConsentFinalidade::Transacional),
            'Transacional deve permanecer ativo após /sair (Q25).',
        );

        Event::assertDispatchedTimes(ConsentimentoRevogado::class, 1);
    }

    public function test_sair_tudo_revokes_all_finalities(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-sair-tudo', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        /** @var ConsentService $service */
        $service = app(ConsentService::class);

        $service->record($patient, 'instagram', ConsentFinalidade::Transacional);
        $service->record($patient, 'instagram', ConsentFinalidade::Marketing);
        $service->record($patient, 'instagram', ConsentFinalidade::Pesquisa);

        /** @var ProcessSairCommandListener $listener */
        $listener = app(ProcessSairCommandListener::class);
        $revoked = $listener->process($patient, 'instagram', '/sair tudo');

        $this->assertCount(3, $revoked, 'Todas as 3 finalidades devem ter sido revogadas.');
        $this->assertFalse($service->hasGranted($patient->id, ConsentFinalidade::Transacional));
        $this->assertFalse($service->hasGranted($patient->id, ConsentFinalidade::Marketing));
        $this->assertFalse($service->hasGranted($patient->id, ConsentFinalidade::Pesquisa));
    }

    public function test_listener_is_case_insensitive_and_tolerates_whitespace(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-case', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        /** @var ConsentService $service */
        $service = app(ConsentService::class);
        $service->record($patient, 'web', ConsentFinalidade::Marketing);

        /** @var ProcessSairCommandListener $listener */
        $listener = app(ProcessSairCommandListener::class);

        // Variantes válidas.
        foreach (['  /sair  ', '/SAIR', '/Sair', '/sair'] as $variant) {
            $service->record($patient, 'web', ConsentFinalidade::Marketing); // re-grant entre variantes
            $revoked = $listener->process($patient, 'web', $variant);
            $this->assertNotEmpty($revoked, "Variante '{$variant}' deveria revogar.");
        }
    }

    public function test_unrelated_message_does_not_revoke(): void
    {
        [$tenant, ] = $this->tenantAndUserForRole('clinica-unrelated', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        /** @var ConsentService $service */
        $service = app(ConsentService::class);
        $service->record($patient, 'web', ConsentFinalidade::Marketing);

        /** @var ProcessSairCommandListener $listener */
        $listener = app(ProcessSairCommandListener::class);
        $revoked = $listener->process($patient, 'web', 'Olá, gostaria de agendar uma consulta.');

        $this->assertEmpty($revoked);
        $this->assertTrue($service->hasGranted($patient->id, ConsentFinalidade::Marketing));
    }
}
