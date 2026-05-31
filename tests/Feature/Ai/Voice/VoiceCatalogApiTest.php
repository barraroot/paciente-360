<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Voice;

use App\Domain\Ai\Voice\Models\VoiceCatalogEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T173 (Fase 18 — US5, FR-037c) — API de listagem de catálogo de vozes.
 *
 * Testa:
 * - GET /api/v1/ai/voices retorna vozes ativas
 * - provider_voice_id e provider NÃO são expostos
 * - Requer permission ai.persona.manage
 */
final class VoiceCatalogApiTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('test-voice-api', 'admin-clinica');
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    public function test_list_voices_filtered_to_active(): void
    {
        VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-active',
            'display_name' => 'Ativa',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_active' => true,
            'is_system_default' => false,
        ]);

        VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-inactive',
            'display_name' => 'Inativa',
            'gender' => 'm',
            'tone' => 'profissional',
            'language' => 'pt-BR',
            'is_active' => false,
            'is_system_default' => false,
        ]);

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/ai/voices')
        );

        $response->assertOk();
        $voices = $response->json('data');

        $this->assertCount(1, $voices);
        $this->assertEquals('Ativa', $voices[0]['display_name']);
    }

    public function test_voice_resource_does_not_expose_provider_id(): void
    {
        VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'super-secret-voice-id',
            'display_name' => 'Camila',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_active' => true,
            'is_system_default' => false,
        ]);

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/ai/voices')
        );

        $response->assertOk();
        $voice = $response->json('data')[0];

        $this->assertArrayNotHasKey('provider_voice_id', $voice);
        $this->assertArrayNotHasKey('provider', $voice);
    }

    public function test_voice_resource_contains_expected_fields(): void
    {
        VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-abc',
            'display_name' => 'Camila',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'preview_audio_path' => 'voices/preview.mp3',
            'is_active' => true,
            'is_system_default' => false,
        ]);

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/ai/voices')
        );

        $voice = $response->json('data')[0];

        $this->assertArrayHasKey('id', $voice);
        $this->assertArrayHasKey('display_name', $voice);
        $this->assertArrayHasKey('gender', $voice);
        $this->assertArrayHasKey('tone', $voice);
        $this->assertArrayHasKey('language', $voice);
        $this->assertArrayHasKey('preview_url', $voice);
        $this->assertArrayHasKey('is_system_default', $voice);
    }

    public function test_requires_permission_ai_persona_manage(): void
    {
        $otherUser = $this->createUserForTenant($this->tenant);
        Sanctum::actingAs($otherUser, ['*']);

        $response = $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, '/ai/voices')
        );

        $response->assertForbidden();
    }

    private function tenantUrl(Tenant $tenant, string $path = ''): string
    {
        $suffix = config('tenancy.subdomain_suffix', 'lvh.me');

        return "http://{$tenant->slug}.{$suffix}/api/v1{$path}";
    }

    private function createUserForTenant(Tenant $tenant)
    {
        return User::factory()->create(['tenant_id' => $tenant->id]);
    }
}
