<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Audio\Outbound\Services;

/**
 * DTO imutável do resultado de síntese TTS (Fase 18, US5).
 *
 * `audioBytes` é null em caso de falha; `errorCode` enumera
 * provider_error | too_long | voice_unavailable.
 * `mimeType` é o do áudio gerado (default audio/mpeg).
 */
final readonly class SynthesisResult
{
    public function __construct(
        public ?string $audioBytes,
        public ?string $mimeType,
        public ?int $durationSeconds,
        public int $latencyMs,
        public ?string $errorCode,
        public ?string $errorMessage,
    ) {}

    public static function success(string $audioBytes, string $mimeType, int $latencyMs, ?int $durationSeconds = null): self
    {
        return new self(
            audioBytes: $audioBytes,
            mimeType: $mimeType,
            durationSeconds: $durationSeconds,
            latencyMs: $latencyMs,
            errorCode: null,
            errorMessage: null,
        );
    }

    public static function failure(string $errorCode, string $errorMessage, int $latencyMs): self
    {
        return new self(
            audioBytes: null,
            mimeType: null,
            durationSeconds: null,
            latencyMs: $latencyMs,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
        );
    }

    public function failed(): bool
    {
        return $this->errorCode !== null || $this->audioBytes === null;
    }
}
