<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Voice;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Voice\Models\VoiceCatalogEntry;
use App\Domain\Ai\Voice\Services\PersonaVoiceResolverService;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T169 (Fase 18 — US5, Q-clarify-4=B, FR-037a) — Resolução de voz para persona.
 *
 * Testa a cadeia de resolução:
 * 1. Persona.voice_id (se ativa)
 * 2. Tenant.default_voice_id (se ativa)
 * 3. is_system_default=true da language
 */
final class PersonaVoiceResolverTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private PersonaVoiceResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(PersonaVoiceResolverService::class);
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);
    }

    public function test_resolves_persona_voice_when_set(): void
    {
        $voice = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-persona',
            'display_name' => 'Persona Voice',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_active' => true,
            'is_system_default' => false,
        ]);

        $persona = AiPersona::factory()->create([
            'tenant_id' => $this->tenant->id,
            'voice_id' => $voice->id,
        ]);

        $resolved = $this->resolver->resolveFor($persona);

        $this->assertNotNull($resolved);
        $this->assertEquals($voice->id, $resolved->id);
    }

    public function test_falls_back_to_tenant_default(): void
    {
        $personaVoice = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-inactive',
            'display_name' => 'Inactive Voice',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_active' => false,
            'is_system_default' => false,
        ]);

        $tenantVoice = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-tenant-default',
            'display_name' => 'Tenant Default',
            'gender' => 'm',
            'tone' => 'profissional',
            'language' => 'pt-BR',
            'is_active' => true,
            'is_system_default' => false,
        ]);

        $this->tenant->forceFill(['default_voice_id' => $tenantVoice->id])->save();

        $persona = AiPersona::factory()->create([
            'tenant_id' => $this->tenant->id,
            'voice_id' => $personaVoice->id,
        ]);

        $resolved = $this->resolver->resolveFor($persona);

        $this->assertNotNull($resolved);
        $this->assertEquals($tenantVoice->id, $resolved->id);
    }

    public function test_falls_back_to_system_default(): void
    {
        $systemDefault = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-system-default',
            'display_name' => 'System Default',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_active' => true,
            'is_system_default' => true,
        ]);

        $persona = AiPersona::factory()->create([
            'tenant_id' => $this->tenant->id,
            'voice_id' => null,
        ]);

        $resolved = $this->resolver->resolveFor($persona);

        $this->assertNotNull($resolved);
        $this->assertEquals($systemDefault->id, $resolved->id);
    }

    public function test_returns_null_when_no_voice_found(): void
    {
        $persona = AiPersona::factory()->create([
            'tenant_id' => $this->tenant->id,
            'voice_id' => null,
        ]);

        $resolved = $this->resolver->resolveFor($persona);

        $this->assertNull($resolved);
    }

    public function test_inactive_persona_voice_skipped(): void
    {
        $inactiveVoice = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-inactive',
            'display_name' => 'Inactive',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_active' => false,
            'is_system_default' => false,
        ]);

        $systemDefault = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-system-default',
            'display_name' => 'System Default',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_active' => true,
            'is_system_default' => true,
        ]);

        $persona = AiPersona::factory()->create([
            'tenant_id' => $this->tenant->id,
            'voice_id' => $inactiveVoice->id,
        ]);

        $resolved = $this->resolver->resolveFor($persona);

        $this->assertNotNull($resolved);
        $this->assertEquals($systemDefault->id, $resolved->id);
    }

    public function test_inactive_tenant_default_skipped(): void
    {
        $inactiveTenantDefault = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-inactive-tenant',
            'display_name' => 'Inactive Tenant',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_active' => false,
            'is_system_default' => false,
        ]);

        $systemDefault = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-system-default',
            'display_name' => 'System Default',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_active' => true,
            'is_system_default' => true,
        ]);

        $this->tenant->forceFill(['default_voice_id' => $inactiveTenantDefault->id])->save();

        $persona = AiPersona::factory()->create([
            'tenant_id' => $this->tenant->id,
            'voice_id' => null,
        ]);

        $resolved = $this->resolver->resolveFor($persona);

        $this->assertNotNull($resolved);
        $this->assertEquals($systemDefault->id, $resolved->id);
    }

    public function test_respects_language_parameter(): void
    {
        $ptDefault = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-pt',
            'display_name' => 'PT-BR Default',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_active' => true,
            'is_system_default' => true,
        ]);

        $enDefault = VoiceCatalogEntry::create([
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'voice-en',
            'display_name' => 'EN-US Default',
            'gender' => 'm',
            'tone' => 'professional',
            'language' => 'en-US',
            'is_active' => true,
            'is_system_default' => true,
        ]);

        $persona = AiPersona::factory()->create([
            'tenant_id' => $this->tenant->id,
            'voice_id' => null,
        ]);

        $ptResolved = $this->resolver->resolveFor($persona, 'pt-BR');
        $enResolved = $this->resolver->resolveFor($persona, 'en-US');

        $this->assertEquals($ptDefault->id, $ptResolved->id);
        $this->assertEquals($enDefault->id, $enResolved->id);
    }
}
