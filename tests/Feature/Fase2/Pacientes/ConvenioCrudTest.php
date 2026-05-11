<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\Convenio;
use App\Models\Paciente;
use App\Models\PacienteConvenio;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T232 — Testes CRUD de convênios (US-3.5).
 *
 * Cobre criação, atualização, exclusão com restrição de uso,
 * deactivate e listagem filtrada.
 */
class ConvenioCrudTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    private User $medicoUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->adminUser] = $this->tenantAndUserForRole('clinica-alfa', 'admin-clinica');
        $this->medicoUser = $this->userForRole($this->tenant, 'medico');
    }

    private function url(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    /** @test */
    public function test_admin_cria_convenio(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson($this->url('/convenios'), [
                'nome' => 'Unimed BH',
                'codigo_ans' => '12345',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.nome', 'Unimed BH');
        $response->assertJsonPath('data.codigo_ans', '12345');
        $response->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('convenios', [
            'nome' => 'Unimed BH',
            'codigo_ans' => '12345',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function test_medico_nao_pode_criar_convenio(): void
    {
        $this->actingAs($this->medicoUser)
            ->postJson($this->url('/convenios'), [
                'nome' => 'Amil',
            ])
            ->assertForbidden();
    }

    /** @test */
    public function test_admin_atualiza_convenio(): void
    {
        $convenio = Convenio::factory()->forTenant($this->tenant)->create();

        $response = $this->actingAs($this->adminUser)
            ->patchJson($this->url("/convenios/{$convenio->id}"), [
                'nome' => 'Unimed Atualizado',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.nome', 'Unimed Atualizado');
        $this->assertDatabaseHas('convenios', ['id' => $convenio->id, 'nome' => 'Unimed Atualizado']);
    }

    /** @test */
    public function test_excluir_convenio_em_uso_retorna_409(): void
    {
        $convenio = Convenio::factory()->forTenant($this->tenant)->create();
        $paciente = Paciente::factory()->forTenant($this->tenant)->create([
            'status' => 'ativo',
        ]);

        PacienteConvenio::create([
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $paciente->id,
            'convenio_id' => $convenio->id,
            'papel' => 'principal',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson($this->url("/convenios/{$convenio->id}"));

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'convenio.em_uso');
    }

    /** @test */
    public function test_deactivate_via_patch_funciona(): void
    {
        $convenio = Convenio::factory()->forTenant($this->tenant)->create(['is_active' => true]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson($this->url("/convenios/{$convenio->id}"), [
                'is_active' => false,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('convenios', ['id' => $convenio->id, 'is_active' => false]);
    }

    /** @test */
    public function test_listar_convenios_apenas_ativos_por_default(): void
    {
        Convenio::factory()->forTenant($this->tenant)->count(3)->create(['is_active' => true]);
        Convenio::factory()->forTenant($this->tenant)->count(2)->create(['is_active' => false]);

        // Default: apenas ativos
        $response = $this->actingAs($this->adminUser)
            ->getJson($this->url('/convenios'));

        $response->assertOk();
        $ativos = collect($response->json('data'));
        $this->assertCount(3, $ativos);
        $ativos->each(fn ($c) => $this->assertTrue($c['is_active']));

        // Com ?is_active=false: apenas inativos
        $response2 = $this->actingAs($this->adminUser)
            ->getJson($this->url('/convenios?is_active=false'));

        $response2->assertOk();
        $inativos = collect($response2->json('data'));
        $this->assertCount(2, $inativos);
        $inativos->each(fn ($c) => $this->assertFalse($c['is_active']));
    }
}
