<?php

declare(strict_types=1);

namespace App\Domain\Ai\Voice\Services;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Voice\Models\VoiceCatalogEntry;
use App\Models\Tenant;

/**
 * **T146 (Fase 18 — US5, Q-clarify-4=B, FR-037a)** — resolve a voz TTS para
 * uma Persona seguindo a cadeia:
 *
 *   1. `Persona.voice_id` (se preenchido e voz `is_active`)
 *   2. `Tenant.default_voice_id` (se preenchido e voz `is_active`)
 *   3. Catálogo `is_system_default=true` para a `language` solicitada
 *
 * Retorna null se NENHUMA das 3 camadas resolver — caller deve cair para
 * fallback texto (FR-034).
 */
final class PersonaVoiceResolverService
{
    public function resolveFor(AiPersona $persona, string $language = 'pt-BR'): ?VoiceCatalogEntry
    {
        // 1) Persona.voice_id
        if ($persona->voice_id !== null) {
            $voice = VoiceCatalogEntry::query()
                ->where('id', $persona->voice_id)
                ->where('is_active', true)
                ->first();
            if ($voice !== null) {
                return $voice;
            }
        }

        // 2) Tenant default
        $tenant = Tenant::query()->find($persona->tenant_id);
        if ($tenant !== null && $tenant->default_voice_id !== null) {
            $voice = VoiceCatalogEntry::query()
                ->where('id', $tenant->default_voice_id)
                ->where('is_active', true)
                ->first();
            if ($voice !== null) {
                return $voice;
            }
        }

        // 3) System default por language
        return VoiceCatalogEntry::query()
            ->where('language', $language)
            ->where('is_active', true)
            ->where('is_system_default', true)
            ->first();
    }
}
