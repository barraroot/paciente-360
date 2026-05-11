<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Events\Paciente\LeadMovidoNoFunil;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Funil\FunilService;
use App\Services\Funil\FunilTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T202 — Testes de movimentação automática de card no funil (gancho Fase 5).
 *
 * AC-3.4.5: FunilService::moveCard() com automatico=true gera evento
 * LeadMovidoNoFunil com automatico=true no payload.
 */
class FunilAutoMoveTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->user] = $this->tenantAndUserForRole('clinica-alfa', 'admin-clinica');
    }

    /** @test */
    public function test_funil_service_move_card_aceita_automatico_true(): void
    {
        Event::fake([LeadMovidoNoFunil::class]);

        /** @var FunilTemplateService $templateService */
        $templateService = app(FunilTemplateService::class);
        $templateService->ensureTenantHasColumns($this->tenant);

        $coluna = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'qualificado')->first();

        $paciente = Paciente::factory()->forTenant($this->tenant)->create();

        /** @var FunilService $service */
        $service = app(FunilService::class);
        $service->moveCard($paciente, $coluna, 1.0, null, null, automatico: true);

        Event::assertDispatched(LeadMovidoNoFunil::class, function (LeadMovidoNoFunil $event): bool {
            return $event->automatico === true;
        });
    }
}
