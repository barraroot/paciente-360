<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US2 / G2 / G5 — matriz Persona×Canal, co-tenancy e habilitação derivada.
 */
final class PersonaChannelMatrixTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private AiModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('ai-matrix-admin', 'admin-clinica');
        $this->model = AiModel::factory()->create();
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    public function test_updates_matrix_cells(): void
    {
        $persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $this->model->id]);

        $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, '/ai/persona-channels'),
            ['cells' => [
                ['ai_persona_id' => $persona->id, 'channel_type' => 'whatsapp', 'is_active' => true],
                ['ai_persona_id' => $persona->id, 'channel_type' => 'web', 'is_active' => false],
            ]],
        )->assertOk();

        $this->assertDatabaseHas('ai_persona_channels', [
            'tenant_id' => $this->tenant->id,
            'ai_persona_id' => $persona->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);
    }

    public function test_rejects_persona_from_other_tenant(): void
    {
        $tenantB = $this->createTenant(['slug' => 'ai-matrix-b']);
        $personaB = AiPersona::factory()->forTenant($tenantB)->create(['ai_model_id' => $this->model->id]);

        $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, '/ai/persona-channels'),
            ['cells' => [
                ['ai_persona_id' => $personaB->id, 'channel_type' => 'whatsapp', 'is_active' => true],
            ]],
        )->assertStatus(422)->assertJsonValidationErrors('cells.0.ai_persona_id');
    }

    public function test_channel_config_reports_ai_disabled_without_active_persona(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenant, '/ai/channels/whatsapp/config'));

        $response->assertOk()
            ->assertJsonPath('data.ai_enabled', false)
            ->assertJsonPath('data.channel_type', 'whatsapp');
    }

    public function test_channel_config_reports_enabled_with_active_persona(): void
    {
        $persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $this->model->id]);
        AiPersonaChannel::create([
            'tenant_id' => $this->tenant->id,
            'ai_persona_id' => $persona->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);

        $this->withHeaders($this->headers())
            ->getJson($this->tenantUrl($this->tenant, '/ai/channels/whatsapp/config'))
            ->assertOk()
            ->assertJsonPath('data.ai_enabled', true);
    }

    public function test_requires_matrix_manage_permission(): void
    {
        [$tenant] = $this->tenantAndUserForRole('ai-matrix-medico', 'medico');

        $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])->putJson(
            $this->tenantUrl($tenant, '/ai/persona-channels'),
            ['cells' => [['ai_persona_id' => 1, 'channel_type' => 'whatsapp', 'is_active' => true]]],
        )->assertForbidden();
    }
}
