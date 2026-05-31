<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Ai\Voice\Models\VoiceCatalogEntry;
use Illuminate\Database\Seeder;

/**
 * **T034 (Fase 18 — US5, Q-clarify-4=B)** — popula o catálogo global de
 * vozes ElevenLabs em PT-BR.
 *
 * NÃO é tenant-scoped — catálogo global gerenciado pelo super-admin via
 * Filament (T160). Idempotente (firstOrCreate pelo par provider+provider_voice_id).
 *
 * Exatamente 1 voz com is_system_default=true por language (UNIQUE parcial).
 * Os `provider_voice_id` abaixo são IDs reais do catálogo ElevenLabs PT-BR
 * (a serem confirmados no go-live; placeholders coerentes por enquanto).
 */
class VoiceCatalogSeeder extends Seeder
{
    /**
     * @var list<array{
     *   provider: string,
     *   provider_voice_id: string,
     *   display_name: string,
     *   gender: string,
     *   tone: string,
     *   language: string,
     *   is_system_default: bool,
     * }>
     */
    private const VOICES = [
        [
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'EXAVITQu4vr4xnSDxMaL', // placeholder PT-BR feminino
            'display_name' => 'Camila Acolhedora',
            'gender' => 'f',
            'tone' => 'acolhedor',
            'language' => 'pt-BR',
            'is_system_default' => true,
        ],
        [
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'oWAxZDx7w5VEj9dCyTzz',
            'display_name' => 'Beatriz Profissional',
            'gender' => 'f',
            'tone' => 'profissional',
            'language' => 'pt-BR',
            'is_system_default' => false,
        ],
        [
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'pNInz6obpgDQGcFmaJgB',
            'display_name' => 'Ricardo Profissional',
            'gender' => 'm',
            'tone' => 'profissional',
            'language' => 'pt-BR',
            'is_system_default' => false,
        ],
        [
            'provider' => 'elevenlabs',
            'provider_voice_id' => 'TxGEqnHWrfWFTfGW9XjX',
            'display_name' => 'Bruno Calmo',
            'gender' => 'm',
            'tone' => 'calmo',
            'language' => 'pt-BR',
            'is_system_default' => false,
        ],
    ];

    public function run(): void
    {
        foreach (self::VOICES as $voice) {
            VoiceCatalogEntry::firstOrCreate(
                [
                    'provider' => $voice['provider'],
                    'provider_voice_id' => $voice['provider_voice_id'],
                ],
                [
                    'display_name' => $voice['display_name'],
                    'gender' => $voice['gender'],
                    'tone' => $voice['tone'],
                    'language' => $voice['language'],
                    'is_active' => true,
                    'is_system_default' => $voice['is_system_default'],
                ],
            );
        }
    }
}
