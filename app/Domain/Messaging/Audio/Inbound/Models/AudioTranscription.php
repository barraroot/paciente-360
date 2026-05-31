<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Audio\Inbound\Models;

use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feature 018 (US4) — transcrição de cada áudio inbound (STT).
 *
 * Uma linha por áudio processado. `transcribed_text` é o texto bruto vindo
 * do provedor (Whisper); ANTES de ir ao modelo de IA passa por PiiScrubber
 * (Fase 17 — FR-055b).
 *
 * Retenção: alinhada com messaging_message_media (Fase 13). NÃO depende do
 * consent `Transcricao` — é texto, sem voz biométrica. O áudio bruto
 * (referenciado por media_id) é o que requer consent Transcricao para
 * retenção prolongada (FR-055a — PurgeExpiredAudioRawJob T210).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $message_id
 * @property int $media_id
 * @property string $provider openai_whisper|google_stt|azure_speech
 * @property string|null $language_detected
 * @property string|null $transcribed_text
 * @property bool $truncated
 * @property string|null $error_code silence|language_unsupported|timeout|provider_error|audio_corrupted
 * @property string|null $error_message
 * @property int|null $duration_seconds
 * @property int|null $latency_ms
 */
class AudioTranscription extends Model
{
    use BelongsToTenant;

    protected $table = 'audio_transcriptions';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'message_id',
        'media_id',
        'provider',
        'language_detected',
        'transcribed_text',
        'truncated',
        'error_code',
        'error_message',
        'duration_seconds',
        'latency_ms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'truncated' => 'boolean',
            'duration_seconds' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    /**
     * @return BelongsTo<MessageMedia, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MessageMedia::class, 'media_id');
    }
}
