<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Audio\Inbound\Services;

/**
 * DTO imutável do resultado de uma transcrição STT (Fase 18, US4).
 *
 * `text` é null em caso de falha; `errorCode` enumera silence | language_unsupported |
 * timeout | provider_error | audio_corrupted. `truncated` indica que o áudio
 * excedeu o limite configurado e só uma parte foi transcrita (FR-028).
 */
final readonly class TranscriptionResult
{
    public function __construct(
        public ?string $text,
        public ?string $languageDetected,
        public bool $truncated,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?int $durationSeconds,
        public int $latencyMs,
    ) {}

    public static function success(string $text, ?string $language, ?int $durationSeconds, int $latencyMs, bool $truncated = false): self
    {
        return new self(
            text: $text,
            languageDetected: $language,
            truncated: $truncated,
            errorCode: null,
            errorMessage: null,
            durationSeconds: $durationSeconds,
            latencyMs: $latencyMs,
        );
    }

    public static function failure(string $errorCode, string $errorMessage, int $latencyMs): self
    {
        return new self(
            text: null,
            languageDetected: null,
            truncated: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            durationSeconds: null,
            latencyMs: $latencyMs,
        );
    }

    public function failed(): bool
    {
        return $this->errorCode !== null || $this->text === null;
    }
}
