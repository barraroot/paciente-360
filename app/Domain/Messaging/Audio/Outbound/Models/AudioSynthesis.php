<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Audio\Outbound\Models;

use App\Domain\Ai\Voice\Models\VoiceCatalogEntry;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feature 018 (US5) — áudio outbound TTS gerado para cada resposta da IA
 * que dispara o gatilho explícito de áudio (Q3=A — FR-031).
 *
 * `source_text` é o texto que veio do modelo; `normalized_text` é o texto
 * pós TtsTextNormalizer (FR-035 — R$/horários/datas para fala).
 *
 * Em caso de falha, `fallback_to_text=true` indica que a resposta foi
 * enviada como texto sem perda (FR-034); `error_code` registra a causa.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $message_id
 * @property int|null $media_id
 * @property int $voice_id
 * @property string $provider elevenlabs|openai_tts|azure_tts
 * @property string $source_text
 * @property string $normalized_text
 * @property bool $segmented
 * @property int|null $duration_seconds
 * @property int|null $latency_ms
 * @property string|null $error_code
 * @property string|null $error_message
 * @property bool $fallback_to_text
 */
class AudioSynthesis extends Model
{
    use BelongsToTenant;

    protected $table = 'audio_syntheses';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'message_id',
        'media_id',
        'voice_id',
        'provider',
        'source_text',
        'normalized_text',
        'segmented',
        'duration_seconds',
        'latency_ms',
        'error_code',
        'error_message',
        'fallback_to_text',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segmented' => 'boolean',
            'duration_seconds' => 'integer',
            'latency_ms' => 'integer',
            'fallback_to_text' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<VoiceCatalogEntry, $this>
     */
    public function voice(): BelongsTo
    {
        return $this->belongsTo(VoiceCatalogEntry::class, 'voice_id');
    }
}
