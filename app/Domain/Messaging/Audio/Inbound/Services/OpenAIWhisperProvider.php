<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Audio\Inbound\Services;

use App\Domain\Messaging\Message\Models\MessageMedia;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * **T127 (Fase 18 — US4, FR-024)** — provedor OpenAI Whisper para STT.
 *
 * Endpoint: `POST /audio/transcriptions` multipart. Default model: `whisper-1`,
 * PT-BR. Sem armazenamento no provedor (DPA-friendly — LGPD).
 *
 * Mapeia falhas para `error_code` semântico:
 *   - timeout (Http::timeout exceeded)
 *   - connection_refused (ConnectionException)
 *   - audio_corrupted (4xx do provedor)
 *   - provider_error (5xx ou exceção inesperada)
 *
 * Áudios sem fala/curtos demais → text vazio → `silence` (mapeado no Service).
 */
final class OpenAIWhisperProvider implements AudioTranscriptionProvider
{
    public function transcribe(MessageMedia $media, ?string $language = null): TranscriptionResult
    {
        $startedAt = microtime(true);

        $apiKey = (string) config('messaging.audio.stt.openai_api_key');
        if ($apiKey === '') {
            return TranscriptionResult::failure(
                errorCode: 'provider_error',
                errorMessage: 'OpenAI Whisper API key not configured (MESSAGING_STT_OPENAI_API_KEY).',
                latencyMs: 0,
            );
        }

        $disk = Storage::disk($media->storage_disk);
        if (! $disk->exists($media->storage_path)) {
            return TranscriptionResult::failure(
                errorCode: 'audio_corrupted',
                errorMessage: "Media file not found at {$media->storage_disk}:{$media->storage_path}.",
                latencyMs: $this->elapsed($startedAt),
            );
        }

        $contents = $disk->get($media->storage_path);
        if ($contents === null || $contents === '') {
            return TranscriptionResult::failure(
                errorCode: 'audio_corrupted',
                errorMessage: 'Media file is empty.',
                latencyMs: $this->elapsed($startedAt),
            );
        }

        $filename = basename($media->storage_path);
        $timeoutS = (int) config('messaging.audio.stt.timeout_s', 15);
        $lang = $language ?? (string) config('messaging.audio.stt.default_language', 'pt');

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeoutS)
                ->attach('file', $contents, $filename)
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => 'whisper-1',
                    'language' => $lang,
                    'response_format' => 'verbose_json',
                ])
                ->throw();

            $json = (array) $response->json();
            $text = trim((string) ($json['text'] ?? ''));
            $detectedLang = (string) ($json['language'] ?? $lang);
            $duration = isset($json['duration']) ? (int) round((float) $json['duration']) : null;

            if ($text === '') {
                return TranscriptionResult::failure(
                    errorCode: 'silence',
                    errorMessage: 'Áudio sem fala detectável.',
                    latencyMs: $this->elapsed($startedAt),
                );
            }

            return TranscriptionResult::success(
                text: $text,
                language: $detectedLang,
                durationSeconds: $duration,
                latencyMs: $this->elapsed($startedAt),
            );
        } catch (ConnectionException $e) {
            return TranscriptionResult::failure(
                errorCode: $this->isTimeoutMessage($e->getMessage()) ? 'timeout' : 'connection_refused',
                errorMessage: $e->getMessage(),
                latencyMs: $this->elapsed($startedAt),
            );
        } catch (RequestException $e) {
            $status = (int) ($e->response?->status() ?? 0);

            return TranscriptionResult::failure(
                errorCode: $status >= 400 && $status < 500 ? 'audio_corrupted' : 'provider_error',
                errorMessage: substr($e->getMessage(), 0, 500),
                latencyMs: $this->elapsed($startedAt),
            );
        } catch (Throwable $e) {
            return TranscriptionResult::failure(
                errorCode: 'provider_error',
                errorMessage: substr($e->getMessage(), 0, 500),
                latencyMs: $this->elapsed($startedAt),
            );
        }
    }

    public function name(): string
    {
        return 'openai_whisper';
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function isTimeoutMessage(string $message): bool
    {
        return str_contains(strtolower($message), 'timeout')
            || str_contains(strtolower($message), 'timed out');
    }
}
