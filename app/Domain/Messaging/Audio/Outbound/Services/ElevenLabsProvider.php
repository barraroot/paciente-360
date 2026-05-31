<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Audio\Outbound\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * **T143 (Fase 18 — US5)** — provedor ElevenLabs para TTS.
 *
 * Endpoint: `POST /v1/text-to-speech/{voice_id}` com body JSON.
 * Model: `eleven_turbo_v2_5` (latência baixa, PT-BR de qualidade).
 *
 * Mapeia falhas para `error_code` semântico:
 *   - timeout (Http::timeout exceeded)
 *   - voice_unavailable (4xx 404/422)
 *   - too_long (4xx 413 — texto excede limite do modelo)
 *   - provider_error (outros 4xx/5xx ou exceção)
 *
 * O texto MUST chegar já normalizado (TtsTextNormalizer — T144); este
 * provider NÃO faz normalização.
 */
final class ElevenLabsProvider implements AudioSynthesisProvider
{
    public function synthesize(string $normalizedText, string $providerVoiceId, string $format = 'mp3'): SynthesisResult
    {
        $startedAt = microtime(true);

        $apiKey = (string) config('messaging.audio.tts.elevenlabs_api_key');
        if ($apiKey === '') {
            return SynthesisResult::failure(
                errorCode: 'provider_error',
                errorMessage: 'ElevenLabs API key not configured (MESSAGING_TTS_ELEVENLABS_API_KEY).',
                latencyMs: 0,
            );
        }

        $model = (string) config('messaging.audio.tts.elevenlabs_model', 'eleven_turbo_v2_5');
        $mimeType = match ($format) {
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            default => 'audio/mpeg',
        };

        try {
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'Accept' => $mimeType,
            ])
                ->timeout(20)
                ->post("https://api.elevenlabs.io/v1/text-to-speech/{$providerVoiceId}", [
                    'text' => $normalizedText,
                    'model_id' => $model,
                    'voice_settings' => [
                        'stability' => 0.5,
                        'similarity_boost' => 0.75,
                    ],
                ])
                ->throw();

            $audioBytes = (string) $response->body();

            return SynthesisResult::success(
                audioBytes: $audioBytes,
                mimeType: $mimeType,
                latencyMs: $this->elapsed($startedAt),
            );
        } catch (ConnectionException $e) {
            return SynthesisResult::failure(
                errorCode: $this->isTimeoutMessage($e->getMessage()) ? 'timeout' : 'provider_error',
                errorMessage: $e->getMessage(),
                latencyMs: $this->elapsed($startedAt),
            );
        } catch (RequestException $e) {
            $status = (int) ($e->response?->status() ?? 0);
            $errorCode = match (true) {
                $status === 413 => 'too_long',
                $status === 404, $status === 422 => 'voice_unavailable',
                default => 'provider_error',
            };

            return SynthesisResult::failure(
                errorCode: $errorCode,
                errorMessage: substr($e->getMessage(), 0, 500),
                latencyMs: $this->elapsed($startedAt),
            );
        } catch (Throwable $e) {
            return SynthesisResult::failure(
                errorCode: 'provider_error',
                errorMessage: substr($e->getMessage(), 0, 500),
                latencyMs: $this->elapsed($startedAt),
            );
        }
    }

    public function name(): string
    {
        return 'elevenlabs';
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function isTimeoutMessage(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'timeout') || str_contains($lower, 'timed out');
    }
}
