<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

/**
 * Feature 017 (US2, FR-017/026) — substitui o marcador `{{primeiro_nome}}` na
 * mensagem de saída pelo primeiro nome real do contato.
 *
 * O nome real NUNCA é enviado ao provedor de IA (o modelo só vê o marcador).
 * Quando o nome é desconhecido, o marcador é removido e a pontuação residual é
 * normalizada — o token literal NUNCA chega ao paciente.
 */
final class OutboundNameInjector
{
    private const PLACEHOLDER = '{{primeiro_nome}}';

    public function inject(string $text, ?string $fullName): string
    {
        $firstName = $this->firstName($fullName);

        if ($firstName !== null) {
            return str_replace(self::PLACEHOLDER, $firstName, $text);
        }

        // Nome desconhecido: remove o marcador e limpa vírgula/espaço residual.
        $text = str_replace(self::PLACEHOLDER, '', $text);
        $text = preg_replace('/\s+([,.!?;:])/u', '$1', $text) ?? $text;
        $text = preg_replace('/,\s*,/u', ',', $text) ?? $text;
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function firstName(?string $fullName): ?string
    {
        if ($fullName === null) {
            return null;
        }

        $trimmed = trim($fullName);
        if ($trimmed === '') {
            return null;
        }

        $first = preg_split('/\s+/u', $trimmed)[0] ?? '';

        return $first !== '' ? $first : null;
    }
}
