<?php

namespace Tests\Feature\Fase2\Pacientes;

use App\Models\Paciente;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tags\TagNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T230 — Testes de segmentação por tags (US-3.5).
 *
 * Cobre AC-3.5.1, 3.5.2, 3.5.3, 3.5.4, 3.5.7.
 */
class TagSegmentacaoTest extends TestCase
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
    public function test_admin_pode_criar_tag_livre(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson($this->url('/tags'), ['nome' => 'Diabético']);

        $response->assertCreated();
        $response->assertJsonPath('data.tipo', 'livre');
        $response->assertJsonPath('data.nome', 'Diabético');

        $this->assertDatabaseHas('tags', [
            'nome' => 'Diabético',
            'nome_normalizado' => TagNormalizer::normalize('Diabético'),
            'tipo' => 'livre',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function test_tag_normalizada_reusa_existente(): void
    {
        // Cria tag inicial
        $this->actingAs($this->adminUser)
            ->postJson($this->url('/tags'), ['nome' => 'Diabético'])
            ->assertCreated();

        // Tenta criar com nome diferente mas mesma normalização
        $response = $this->actingAs($this->adminUser)
            ->postJson($this->url('/tags'), ['nome' => 'DIABETICO']);

        $response->assertStatus(409);
        $response->assertJsonPath('data.nome_normalizado', TagNormalizer::normalize('Diabético'));

        // Confirma que apenas 1 tag existe no tenant
        $this->assertDatabaseCount('tags', 1);
    }

    /** @test */
    public function test_prefixo_sys_reservado_para_sistema(): void
    {
        $response = $this->actingAs($this->medicoUser)
            ->postJson($this->url('/tags'), ['nome' => 'sys:teste']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['nome']);
    }

    /** @test */
    public function test_tag_sistemica_nao_removivel_por_user_comum(): void
    {
        // Cria tag sistêmica diretamente via model (contexto interno/sistema)
        $tagSistemica = Tag::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'sys:em-tratamento',
            'nome_normalizado' => 'sys:em-tratamento',
            'tipo' => 'sistemica',
        ]);

        $paciente = Paciente::factory()->forTenant($this->tenant)->create([
            'status' => 'ativo',
        ]);
        $paciente->tags()->attach($tagSistemica->id, [
            'tenant_id' => $this->tenant->id,
            'aplicada_por_user_id' => $this->adminUser->id,
            'aplicada_at' => now(),
        ]);

        $response = $this->actingAs($this->medicoUser)
            ->deleteJson($this->url("/pacientes/{$paciente->id}/tags/{$tagSistemica->id}"));

        $response->assertForbidden();
    }

    /** @test */
    public function test_paciente_pode_ter_multiplas_tags(): void
    {
        $paciente = Paciente::factory()->forTenant($this->tenant)->create([
            'status' => 'ativo',
        ]);

        $nomes = ['Diabético', 'Hipertenso', 'Alérgico', 'Idoso'];

        foreach ($nomes as $nome) {
            // Cria a tag
            $tagResp = $this->actingAs($this->adminUser)
                ->postJson($this->url('/tags'), ['nome' => $nome]);
            $tagId = $tagResp->json('data.id');

            // Aplica ao paciente
            $this->actingAs($this->adminUser)
                ->postJson($this->url("/pacientes/{$paciente->id}/tags"), ['tag_id' => $tagId]);
        }

        $paciente->refresh()->load('tags');
        $this->assertCount(4, $paciente->tags);
    }

    /** @test */
    public function test_soft_limit_10_tags_alerta(): void
    {
        $paciente = Paciente::factory()->forTenant($this->tenant)->create([
            'status' => 'ativo',
        ]);

        // Aplica 10 tags
        for ($i = 1; $i <= 10; $i++) {
            $tagResp = $this->actingAs($this->adminUser)
                ->postJson($this->url('/tags'), ['nome' => "Tag {$i}"])
                ->assertCreated();
            $tagId = $tagResp->json('data.id');
            $this->actingAs($this->adminUser)
                ->postJson($this->url("/pacientes/{$paciente->id}/tags"), ['tag_id' => $tagId]);
        }

        // 11ª tag
        $tag11Resp = $this->actingAs($this->adminUser)
            ->postJson($this->url('/tags'), ['nome' => 'Tag 11'])
            ->assertCreated();
        $tag11Id = $tag11Resp->json('data.id');

        $response = $this->actingAs($this->adminUser)
            ->postJson($this->url("/pacientes/{$paciente->id}/tags"), ['tag_id' => $tag11Id]);

        $response->assertOk();
        $this->assertNotEmpty($response->headers->get('X-Soft-Warning'));
        $this->assertStringContainsString('tag.soft_limit', $response->headers->get('X-Soft-Warning'));
    }

    /** @test */
    public function test_busca_filtra_por_tag(): void
    {
        $pacienteCom = Paciente::factory()->forTenant($this->tenant)->create([
            'nome' => 'Paciente Com Tag',
            'status' => 'ativo',
        ]);
        $pacienteSem = Paciente::factory()->forTenant($this->tenant)->create([
            'nome' => 'Paciente Sem Tag',
            'status' => 'ativo',
        ]);

        $tagResp = $this->actingAs($this->adminUser)
            ->postJson($this->url('/tags'), ['nome' => 'Diabetico'])
            ->assertCreated();
        $tagId = $tagResp->json('data.id');

        $this->actingAs($this->adminUser)
            ->postJson($this->url("/pacientes/{$pacienteCom->id}/tags"), ['tag_id' => $tagId]);

        $response = $this->actingAs($this->adminUser)
            ->getJson($this->url('/pacientes?tag=diabetico'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($pacienteCom->id, $ids->toArray());
        $this->assertNotContains($pacienteSem->id, $ids->toArray());
    }
}
