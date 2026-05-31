<?php

declare(strict_types=1);

namespace App\Domain\Ai\Voice\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Feature 018 (US5, Q-clarify-4=B) — entrada do catálogo global de vozes TTS.
 *
 * NÃO tem tenant scope — é catálogo curado pelo super-admin via Filament
 * (T160). O resolvedor PersonaVoiceResolverService (T146) usa a cadeia:
 * Persona.voice_id → tenant.default_voice_id → entrada com is_system_default=true.
 *
 * `provider_voice_id` NUNCA é exposto ao admin de clínica (FR-037c — só
 * identificadores técnicos no painel super-admin).
 *
 * @property int $id
 * @property string $provider
 * @property string $provider_voice_id
 * @property string $display_name
 * @property string $gender f|m|neutral
 * @property string $tone acolhedor|profissional|energico|calmo
 * @property string $language default pt-BR
 * @property string|null $preview_audio_path
 * @property bool $is_active
 * @property bool $is_system_default
 */
class VoiceCatalogEntry extends Model
{
    protected $table = 'voice_catalog';

    /** @var array<int, string> */
    protected $fillable = [
        'provider',
        'provider_voice_id',
        'display_name',
        'gender',
        'tone',
        'language',
        'preview_audio_path',
        'is_active',
        'is_system_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system_default' => 'boolean',
        ];
    }
}
