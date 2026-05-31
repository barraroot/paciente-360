<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Audio\Outbound\Services;

/**
 * **T142 (Fase 18 — US5)** — contrato de síntese TTS, plugável.
 *
 * Implementações: {@see ElevenLabsProvider} (default).
 */
interface AudioSynthesisProvider
{
    public function synthesize(string $normalizedText, string $providerVoiceId, string $format = 'mp3'): SynthesisResult;

    /** Identificador do provedor (ex: 'elevenlabs') — gravado em `audio_syntheses.provider`. */
    public function name(): string;
}
