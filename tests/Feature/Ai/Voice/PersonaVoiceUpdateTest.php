<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Voice;

use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Voice\Models\VoiceCatalogEntry;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T174 (Fase 18 — US5, FR-037b) — Atualização de voice_id em persona.
 *
 * Testa:
 * - PUT /api/v1/ai/personas/{id} com voice_id válido (ativo)
 * - Rejeita voice_id inativo
 * - Rejeita voice_id inexistente
 */
final class PersonaVoiceUpdateTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private AiModel $model;

    private AiPersona $persona;

    private VoiceCatalogEntry $activeVoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('test-persona-voice', 'admin-clinica');

        $this->model = AiModel::factory()->create();

        $this->activeVoice = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-active',
            'display_name' => 'Ativa',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_active' => true,
            'is_system_default' => false,
        ]);

        $this->persona = AiPersona::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ai_model_id' => $this->model->id,
            'voice_id' => null,
        ]);
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    private function tenantUrl(Tenant $tenant, string $path = ''): string
    {
        $suffix = config('tenancy.subdomain_suffix', 'lvh.me');

        return "http://{$tenant->slug}.{$suffix}/api/v1{$path}";
    }

    public function test_update_persona_voice_with_active_voice(): void
    {
        $response = $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}"),
            ['voice_id' => $this->activeVoice->id],
        );

        $response->assertOk();

        $this->persona->refresh();
        $this->assertEquals($this->activeVoice->id, $this->persona->voice_id);
    }

    public function test_rejects_inactive_voice(): void
    {
        $inactiveVoice = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-inactive',
            'display_name' => 'Inativa',
            'gender' => 'm',
            'tone' => 'profissional',
            'language' => 'pt-BR',
            'is_active' => false,
            'is_system_default' => false,
        ]);

        $response = $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}"),
            ['voice_id' => $inactiveVoice->id],
        );

        $response->assertStatus(422)->assertJsonValidationErrors('voice_id');
    }

    public function test_rejects_nonexistent_voice(): void
    {
        $response = $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}"),
            ['voice_id' => 99999],
        );

        $response->assertStatus(422)->assertJsonValidationErrors('voice_id');
    }

    public function test_allows_null_voice_id(): void
    {
        $this->persona->forceFill(['voice_id' => $this->activeVoice->id])->save();

        $response = $this->withHeaders($this->headers())->putJson(
            $this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}"),
            ['voice_id' => null],
        );

        $response->assertOk();

        $this->persona->refresh();
        $this->assertNull($this->persona->voice_id);
    }
}
