<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use App\Jobs\Ai\EmbedKnowledgeBaseJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US4 / G1 — CRUD de bases de conhecimento, escopo de tenant e cross-tenant 404.
 */
final class KnowledgeBaseCrudTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('rag-admin', 'admin-clinica');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    public function test_creates_base_and_dispatches_embedding_job(): void
    {
        Bus::fake();

        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/knowledge-bases'),
            [
                'name' => 'FAQ Atendimento',
                'markdown_content' => "# Horários\n\nSeg a Sex, 8h às 18h.",
                'tags' => ['faq'],
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.name', 'FAQ Atendimento')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('ai_knowledge_bases', [
            'tenant_id' => $this->tenant->id,
            'name' => 'FAQ Atendimento',
        ]);

        Bus::assertDispatched(EmbedKnowledgeBaseJob::class);
    }

    public function test_sanitizes_markdown_on_store(): void
    {
        Bus::fake();

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/knowledge-bases'),
            [
                'name' => 'Com Script',
                'markdown_content' => "# Ok\n\n<script>alert('x')</script> conteúdo seguro.",
            ],
        )->assertCreated();

        $base = AiKnowledgeBase::first();
        $this->assertStringNotContainsString('<script>', (string) $base->markdown_content);
    }

    public function test_update_dispatches_reindex_only_when_content_changes(): void
    {
        Bus::fake();
        $base = AiKnowledgeBase::factory()->forTenant($this->tenant)->create();

        $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, "/ai/knowledge-bases/{$base->id}"),
            ['name' => 'Novo nome'],
        )->assertOk();
        Bus::assertNotDispatched(EmbedKnowledgeBaseJob::class);

        $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, "/ai/knowledge-bases/{$base->id}"),
            ['markdown_content' => '# Diferente agora'],
        )->assertOk();
        Bus::assertDispatched(EmbedKnowledgeBaseJob::class);
    }

    public function test_is_active_is_prohibited_on_update(): void
    {
        Bus::fake();
        $base = AiKnowledgeBase::factory()->forTenant($this->tenant)->create();

        $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, "/ai/knowledge-bases/{$base->id}"),
            ['is_active' => false],
        )->assertStatus(422)->assertJsonValidationErrors('is_active');
    }

    public function test_activate_and_deactivate_endpoints(): void
    {
        Bus::fake();
        $base = AiKnowledgeBase::factory()->forTenant($this->tenant)->create(['is_active' => true]);

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, "/ai/knowledge-bases/{$base->id}/deactivate"),
        )->assertOk()->assertJsonPath('data.is_active', false);

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, "/ai/knowledge-bases/{$base->id}/activate"),
        )->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_cross_tenant_base_returns_404(): void
    {
        Bus::fake();
        $otherTenant = Tenant::factory()->create();
        $otherBase = AiKnowledgeBase::factory()->forTenant($otherTenant)->create();

        $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, "/ai/knowledge-bases/{$otherBase->id}"),
        )->assertNotFound();
    }

    public function test_manage_requires_permission(): void
    {
        Bus::fake();
        // 'medico' tem ai.knowledge.view mas NÃO ai.knowledge.manage.
        $medico = $this->userForRole($this->tenant, 'medico');
        Sanctum::actingAs($medico, ['*']);

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/ai/knowledge-bases'),
            ['name' => 'X', 'markdown_content' => '# Y'],
        )->assertForbidden();
    }
}
