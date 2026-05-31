<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Audio\Inbound\Services;

use App\Domain\Messaging\Audio\Inbound\Models\AudioTranscription;
use App\Domain\Messaging\Message\Models\Message;
use App\Support\Lgpd\PiiScrubber;
use App\Support\Metrics\AiMetricsContract;
use Illuminate\Support\Facades\Log;

/**
 * **T128 (Fase 18 — US4, FR-024/025/029/030)** — orquestra a transcrição
 * inbound: chama o provedor, persiste `AudioTranscription`, atualiza a
 * mensagem com o texto transcrito (pseudonimizado — FR-055b/FR-029),
 * trata falhas (FR-027), respeita truncamento (FR-028).
 */
final class AudioTranscriptionService
{
    public function __construct(
        private readonly AudioTranscriptionProvider $provider,
        private readonly AiMetricsContract $metrics,
    ) {}

    /**
     * Transcreve o áudio anexo à `Message` inbound e atualiza o
     * `messaging_messages.body` com o texto pseudonimizado.
     *
     * Retorna o `AudioTranscription` persistido (mesmo em falha — com
     * `error_code` populado).
     */
    public function transcribeInbound(Message $message): AudioTranscription
    {
        $media = $message->media()->first();

        if ($media === null) {
            $audioTrans = AudioTranscription::create([
                'tenant_id' => $message->tenant_id,
                'message_id' => $message->id,
                'media_id' => 0,
                'provider' => $this->provider->name(),
                'language_detected' => null,
                'transcribed_text' => null,
                'truncated' => false,
                'error_code' => 'audio_corrupted',
                'error_message' => 'Mensagem inbound áudio sem MessageMedia anexado.',
                'duration_seconds' => null,
                'latency_ms' => 0,
            ]);

            $this->markFailureOnMessage($message, $audioTrans);

            return $audioTrans;
        }

        $maxDurationS = (int) config('messaging.audio.stt.max_duration_s', 120);
        $language = (string) config('messaging.audio.stt.default_language', 'pt');

        $result = $this->provider->transcribe($media, $language);

        $truncated = false;
        $text = $result->text;

        if ($result->durationSeconds !== null && $result->durationSeconds > $maxDurationS) {
            // FR-028 — marca truncamento (a chamada já consumiu até o limite do provedor).
            $truncated = true;
        }

        $audioTrans = AudioTranscription::create([
            'tenant_id' => $message->tenant_id,
            'message_id' => $message->id,
            'media_id' => $media->id,
            'provider' => $this->provider->name(),
            'language_detected' => $result->languageDetected,
            'transcribed_text' => $text,
            'truncated' => $truncated,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
            'duration_seconds' => $result->durationSeconds,
            'latency_ms' => $result->latencyMs,
        ]);

        $this->metrics->sttDuration(
            provider: $this->provider->name(),
            outcome: $result->failed() ? 'error' : 'success',
            seconds: $result->latencyMs / 1000.0,
        );

        if ($result->failed()) {
            $this->markFailureOnMessage($message, $audioTrans);

            return $audioTrans;
        }

        // FR-025 + FR-055b — substitui o body por texto pseudonimizado + marca
        // is_audio_origin. transcription_id vincula a linha.
        $scrubbed = (string) PiiScrubber::scrub($text ?? '');
        $messageBody = $truncated
            ? $scrubbed."\n\n[áudio truncado em {$maxDurationS}s]"
            : $scrubbed;

        $message->update([
            'body' => $messageBody,
            'body_searchable' => $scrubbed,
            'body_preview' => mb_substr($scrubbed, 0, 140),
            'content_type' => 'text',
            'is_audio_origin' => true,
            'transcription_id' => $audioTrans->id,
        ]);

        return $audioTrans;
    }

    /**
     * FR-027 — falha visível ao operador. NÃO atualiza body (mantém content_type
     * original `audio`); marca `is_audio_origin=true` + vincula a transcrição
     * (com erro) para o operador conseguir clicar e ver o motivo.
     */
    private function markFailureOnMessage(Message $message, AudioTranscription $audioTrans): void
    {
        Log::info('audio_transcription.failed', [
            'tenant_id' => $message->tenant_id,
            'message_id' => $message->id,
            'error_code' => $audioTrans->error_code,
            'provider' => $audioTrans->provider,
        ]);

        $message->update([
            'is_audio_origin' => true,
            'transcription_id' => $audioTrans->id,
        ]);
    }
}
