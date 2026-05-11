<?php

namespace Tests\Feature\Fase2\Professional;

use App\Jobs\Pacientes\ReassignOrphansJob;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\TarefaReatribuicao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfessionalDeactivatedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function professional_deactivation_creates_reassignment_task_and_reassigns_orphans(): void
    {
        Queue::fake();

        // Arrange
        TarefaReatribuicao::query()->truncate();

        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $user = User::factory()->create();

        $professional = Professional::factory()
            ->forTenant($tenant)
            ->for($user)
            ->state(['is_active' => true])
            ->create();

        // Cria 3 pacientes vinculados ao profissional
        $pacientes = Paciente::factory()
            ->count(3)
            ->forTenant($tenant)
            ->state(['profissional_responsavel_id' => $professional->id])
            ->create();

        $pacienteIds = $pacientes->pluck('id')->toArray();

        // Act: desativa o profissional
        $professional->update(['is_active' => false]);

        // Assert: tarefa de reatribuição foi criada
        $this->assertEquals(1, TarefaReatribuicao::where('tenant_id', $tenant->id)->count());

        $tarefa = TarefaReatribuicao::where('tenant_id', $tenant->id)->first();

        $this->assertNotNull($tarefa);
        $this->assertEquals($professional->id, $tarefa->profissional_desativado_id);
        $this->assertEquals(3, $tarefa->total_pacientes);
        $this->assertEqualsCanonicalizing($pacienteIds, $tarefa->pacientes_orfaos_ids);
        $this->assertTrue($tarefa->isPendente());

        // Assert: job foi despachado (com Queue::fake, não é executado imediatamente)
        Queue::assertPushed(ReassignOrphansJob::class);
    }

    #[Test]
    public function professional_deactivation_without_pacientes_does_not_create_task(): void
    {
        Queue::fake();

        // Arrange
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $user = User::factory()->create();

        $professional = Professional::factory()
            ->forTenant($tenant)
            ->for($user)
            ->state(['is_active' => true])
            ->create();

        // Nenhum paciente vinculado

        // Act: desativa o profissional
        $professional->update(['is_active' => false]);

        // Assert: nenhuma tarefa foi criada
        $this->assertEquals(0, TarefaReatribuicao::where('tenant_id', $tenant->id)->count());
    }

    #[Test]
    public function professional_reactivation_does_not_trigger_deactivation_event(): void
    {
        Queue::fake();

        // Arrange
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $user = User::factory()->create();

        $professional = Professional::factory()
            ->forTenant($tenant)
            ->for($user)
            ->state(['is_active' => false])
            ->create();

        $paciente = Paciente::factory()
            ->forTenant($tenant)
            ->state(['profissional_responsavel_id' => $professional->id])
            ->create();

        // Sanity check: nenhuma tarefa
        $this->assertEquals(0, TarefaReatribuicao::where('tenant_id', $tenant->id)->count());

        // Act: reativa o profissional
        $professional->update(['is_active' => true]);

        // Assert: nenhuma tarefa foi criada (event só dispara em true->false)
        $this->assertEquals(0, TarefaReatribuicao::where('tenant_id', $tenant->id)->count());

        // Paciente continua vinculado
        $paciente->refresh();
        $this->assertEquals($professional->id, $paciente->profissional_responsavel_id);
    }
}
