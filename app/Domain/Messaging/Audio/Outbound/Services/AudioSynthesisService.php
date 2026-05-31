<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Audio\Outbound\Services;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Voice\Services\PersonaVoiceResolverService;
use App\Domain\Messaging\Audio\Outbound\Models\AudioSynthesis;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Support\Metrics\AiMetricsContract;
use Illuminate\Support\Facades\Storage;

/**
 * **T147 (Fase 18 — US5, FR-031..037)** — orquestra a síntese TTS de uma
 * resposta da IA: resolve voz (PersonaVoiceResolverService), normaliza o
 * texto (TtsTextNormalizer), chama provider, persiste audio + media,
 * registra fallback to text quando aplicável.
 *
 * Retorna o `AudioSynthesis` persistido (mesmo em falha — com `fallback_to_text=true`
 * + `error_code` populado — FR-034).
 *
 * O caller (AiMessageProcessor, T148) decide se anexa o áudio à mensagem
 * outbound ou se cai para envio texto-only.
 */
final class AudioSynthesisService
{
    public function __construct(
        private readonly AudioSynthesisProvider $provider,
        private readonly TtsTextNormalizer $normalizer,
        private readonly PersonaVoiceResolverService $voiceResolver,
        private readonly AiMetricsContract $metrics,
    ) {}

    /**
     * Sintetiza áudio TTS a partir do texto da resposta da IA.
     *
     * Retorna `AudioSynthesis` (success ou failure). Em failure,
     * `fallback_to_text=true` indica que o caller deve enviar como texto.
     */
    public function synthesizeForMessage(Message $message, AiPersona $persona, string $sourceText): AudioSynthesis
    {
        $voice = $this->voiceResolver->resolveFor($persona);
        if ($voice === null) {
            return $this->failureFallback($message, $persona, $sourceText, 'voice_unavailable', 'No voice resolved for persona.');
        }

        $maxLength = (int) config('messaging.audio.tts.max_text_length', 2000);
        $segmented = mb_strlen($sourceText) > $maxLength;
        $textToSynth = $segmented ? mb_substr($sourceText, 0, $maxLength) : $sourceText;

        $normalized = $this->normalizer->normalize($textToSynth);

        $result = $this->provider->synthesize(
            normalizedText: $normalized,
            providerVoiceId: $voice->provider_voice_id,
        );

        $this->metrics->ttsDuration(
            provider: $this->provider->name(),
            outcome: $result->failed() ? 'error' : 'success',
            seconds: $result->latencyMs / 1000.0,
        );

        if ($result->failed()) {
            $this->metrics->ttsFallbackToText($this->provider->name(), $result->errorCode ?? 'unknown');

            return AudioSynthesis::create([
                'tenant_id' => $message->tenant_id,
                'message_id' => $message->id,
                'media_id' => null,
                'voice_id' => $voice->id,
                'provider' => $this->provider->name(),
                'source_text' => $sourceText,
                'normalized_text' => $normalized,
                'segmented' => $segmented,
                'duration_seconds' => null,
                'latency_ms' => $result->latencyMs,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
                'fallback_to_text' => true,
            ]);
        }

        // Persiste áudio gerado em storage + MessageMedia
        $ext = match ($result->mimeType) {
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            default => 'mp3',
        };
        $disk = (string) config('filesystems.default', 'local');
        $path = "audio-out/{$message->tenant_id}/{$message->id}.{$ext}";
        Storage::disk($disk)->put($path, (string) $result->audioBytes);

        $media = MessageMedia::create([
            'tenant_id' => $message->tenant_id,
            'message_id' => $message->id,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'mime_type' => $result->mimeType ?? 'audio/mpeg',
            'size_bytes' => strlen((string) $result->audioBytes),
            'original_filename' => null,
            'checksum_sha256' => hash('sha256', (string) $result->audioBytes),
            'sensitive_hint' => false, // áudio outbound da clínica, não PII de paciente
        ]);

        return AudioSynthesis::create([
            'tenant_id' => $message->tenant_id,
            'message_id' => $message->id,
            'media_id' => $media->id,
            'voice_id' => $voice->id,
            'provider' => $this->provider->name(),
            'source_text' => $sourceText,
            'normalized_text' => $normalized,
            'segmented' => $segmented,
            'duration_seconds' => $result->durationSeconds,
            'latency_ms' => $result->latencyMs,
            'error_code' => null,
            'error_message' => null,
            'fallback_to_text' => false,
        ]);
    }

    /**
     * Cria registro de falha que indica fallback to text (FR-034) sem chamar provider.
     * Usado quando nem voz resolveu — caller deve enviar como texto.
     */
    private function failureFallback(Message $message, AiPersona $persona, string $sourceText, string $errorCode, string $errorMessage): AudioSynthesis
    {
        $this->metrics->ttsFallbackToText($this->provider->name(), $errorCode);

        return AudioSynthesis::create([
            'tenant_id' => $message->tenant_id,
            'message_id' => $message->id,
            'media_id' => null,
            'voice_id' => null, // nullable após T147-aux — sem voz resolvida
            'provider' => $this->provider->name(),
            'source_text' => $sourceText,
            'normalized_text' => $sourceText,
            'segmented' => false,
            'duration_seconds' => null,
            'latency_ms' => 0,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'fallback_to_text' => true,
        ]);
    }
}
