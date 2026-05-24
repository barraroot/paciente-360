<?php

declare(strict_types=1);

namespace App\Support\Lgpd;

/**
 * **T006** — Scrub universal de PII para integração com Sentry e logs estruturados.
 *
 * Aplica regex sobre strings (recursivamente em arrays) substituindo:
 *  - CPF (BR, com ou sem máscara) → `***.***.***-**`
 *  - Telefone BR (móvel ou fixo)   → `<phone>`
 *  - E-mail                         → `<email>`
 *  - RG SP/RJ                       → `<rg>`
 *  - Cartão SUS (15 dígitos)        → `<sus>`
 *
 * Os padrões espelham {@see PiiDetector::PATTERNS} (T013) — qualquer
 * padrão novo adicionado lá deve ser adicionado aqui também e vice-versa.
 *
 * **Uso esperado** (FR-13.12):
 *   - Sentry `before_send` callback no `AppServiceProvider`.
 *   - Logs estruturados que possam acidentalmente capturar PII em mensagens.
 *
 * **NÃO usar** para pseudonimização de payload do LLM — para isso, eventos
 * devem ser projetados explicitamente sem PII desde o design (padrão
 * {@see ContainsNoClinicalData} da Fase 7).
 *
 * @see specs/008-finalizacao-mvp/research.md §3.3
 */
final class PiiScrubber
{
    /**
     * Substituições aplicadas (ordem importa: padrões mais específicos primeiro).
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        // E-mail.
        '/[\w.+-]+@[\w-]+\.[\w.-]+/' => '<email>',
        // Cartão SUS (15 dígitos) — antes do telefone para não conflitar.
        '/\b\d{15}\b/' => '<sus>',
        // Telefone BR — móvel (com 9) ou fixo, com ou sem DDD. ANTES do CPF: um
        // celular bare de 11 dígitos (11999998888) também casa o padrão de CPF;
        // CPF formatado (com pontos/traço) não casa o de telefone, então a ordem
        // preserva ambos os rótulos. PII é mascarada em qualquer ordem.
        '/\(?\b\d{2}\)?\s?9?\d{4,5}-?\d{4}\b/' => '<phone>',
        // CPF — preserva máscara para indicar que era CPF, mas oculta dígitos.
        '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/' => '***.***.***-**',
        // RG SP/RJ — 8-9 dígitos com dígito verificador opcional (X/x/0-9).
        '/\b\d{2}\.?\d{3}\.?\d{3}-?[\dXx]\b/' => '<rg>',
    ];

    /**
     * Aplica scrub recursivamente em qualquer valor.
     */
    public static function scrub(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::scrubString($value);
        }

        if (is_array($value)) {
            return array_map(static fn ($item): mixed => self::scrub($item), $value);
        }

        // int, float, bool, null, object: não tocar.
        return $value;
    }

    /**
     * Aplica todas as regex em uma string.
     */
    private static function scrubString(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        foreach (self::PATTERNS as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $text);
            if ($result !== null) {
                $text = $result;
            }
        }

        return $text;
    }
}
