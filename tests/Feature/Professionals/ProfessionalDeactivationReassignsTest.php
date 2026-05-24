<?php

declare(strict_types=1);

namespace Tests\Feature\Professionals;

use App\Jobs\Pacientes\ReassignOrphansJob;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **G6 (Spec 012)** — Desativar profissional dispara reatribuição de pacientes.
 *
 * Reusa o comportamento da Fase 2: observer em `Professional::updated` emite
 * `ProfessionalDeactivated` → `ProfessionalDeactivatedListener` enfileira
 * `ReassignOrphansJob` quando há pacientes órfãos.
 */
final class ProfessionalDeactivationReassignsTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        [$this->tenant] = $this->tenantAndUserForRole('reassign-admin', 'admin-clinica');
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    public function test_deactivating_professional_with_patients_dispatches_reassign_job(): void
    {
        Queue::fake();

        $professional = Professional::factory()->forTenant($this->tenant)->create(['is_active' => true]);

        Paciente::factory()->forTenant($this->tenant)->create([
            'profissional_responsavel_id' => $professional->id,
        ]);

        $response = $this->withHeaders($this->headers())->deleteJson(
            $this->tenantUrl($this->tenant, "/professionals/{$professional->id}")
        );

        $response->assertNoContent();

        // Deve cair na fila 'default' (consumida pelo Horizon), nunca numa
        // fila com nome de conexão como 'redis' (regressão: onQueue recebia
        // config('queue.default') que retorna o nome da conexão).
        Queue::assertPushed(ReassignOrphansJob::class, function (ReassignOrphansJob $job): bool {
            return $job->queue === 'default';
        });

        $this->assertFalse((bool) $professional->fresh()->is_active);
        $this->assertSoftDeleted('professionals', ['id' => $professional->id]);
    }

    public function test_reactivating_professional_does_not_dispatch_reassign_job(): void
    {
        $professional = Professional::factory()->forTenant($this->tenant)->create(['is_active' => true]);
        Paciente::factory()->forTenant($this->tenant)->create([
            'profissional_responsavel_id' => $professional->id,
        ]);

        // Desativa primeiro (fora do fake — comportamento real).
        $this->withHeaders($this->headers())->deleteJson(
            $this->tenantUrl($this->tenant, "/professionals/{$professional->id}")
        )->assertNoContent();

        Queue::fake();

        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, "/professionals/{$professional->id}/activate")
        );

        $response->assertOk();
        $response->assertJsonPath('data.is_active', true);

        Queue::assertNotPushed(ReassignOrphansJob::class);
    }
}
