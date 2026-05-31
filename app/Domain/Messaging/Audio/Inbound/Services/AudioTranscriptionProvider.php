<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Audio\Inbound\Services;

use App\Domain\Messaging\Message\Models\MessageMedia;

/**
 * **T126 (Fase 18 — US4)** — contrato de transcrição STT, plugável.
 *
 * Implementações: {@see OpenAIWhisperProvider} (default). Trocar provider =
 * trocar o binding no service container (`bind(AudioTranscriptionProvider::class, ...)`).
 */
interface AudioTranscriptionProvider
{
    public function transcribe(MessageMedia $media, ?string $language = null): TranscriptionResult;

    /** Identificador do provedor (ex: 'openai_whisper') — gravado em `audio_transcriptions.provider`. */
    public function name(): string;
}
