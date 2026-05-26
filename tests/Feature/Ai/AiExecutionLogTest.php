<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Execution\Models\AiExecutionLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US7 / G11 / G1 — consulta de logs de execução escopada por tenant, sem PII.
 */
final class AiExecutionLogTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('ai-logs', 'admin-clinica');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    public function test_lists_only_current_tenant_logs(): void
    {
        AiExecutionLog::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $otherTenant = Tenant::factory()->create();
        AiExecutionLog::factory()->count(2)->create(['tenant_id' => $otherTenant->id]);

        $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/ai/execution-logs'),
        )->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_filters_by_status(): void
    {
        AiExecutionLog::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'status' => 'success']);
        AiExecutionLog::factory()->escalated()->create(['tenant_id' => $this->tenant->id]);

        $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/ai/execution-logs?status=escalated'),
        )->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'escalated');
    }

    public function test_show_returns_pseudonymized_detail(): void
    {
        $log = AiExecutionLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'prompt_summary' => 'Pergunta pseudonimizada.',
            'response_summary' => 'Resposta pseudonimizada.',
        ]);

        $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, "/ai/execution-logs/{$log->id}"),
        )->assertOk()
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.prompt_summary', 'Pergunta pseudonimizada.')
            ->assertJsonPath('data.response_summary', 'Resposta pseudonimizada.');
    }

    public function test_cross_tenant_log_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherLog = AiExecutionLog::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, "/ai/execution-logs/{$otherLog->id}"),
        )->assertNotFound();
    }

    public function test_requires_log_view_permission(): void
    {
        // 'financeiro' não tem ai.log.view.
        $noPerm = $this->userForRole($this->tenant, 'financeiro');
        Sanctum::actingAs($noPerm, ['*']);

        $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/ai/execution-logs'),
        )->assertForbidden();
    }
}
