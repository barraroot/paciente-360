<?php

declare(strict_types=1);

namespace App\Support\Lgpd;

/**
 * **T013** — Detector de PII em strings para auditoria runtime (Q29).
 *
 * Usado pelo job semanal `privacy:audit-pseudonymization-weekly` (T075) para
 * varrer amostra de 1% dos eventos persistidos contra padrões regex de PII.
 *
 * **Diferença do {@see PiiScrubber}**: o Scrubber **substitui** PII no momento
 * do log/Sentry; o Detector **identifica** PII para gerar tickets de auditoria
 * sem expor os valores reais (apenas o nome do padrão e a posição).
 *
 * **Falsos positivos esperados** (research.md §3.3):
 *  - Sequências de 11 dígitos podem casar telefone sem ser telefone (ex.: id externo).
 *  - RG sem máscara casa qualquer 9 dígitos.
 *
 * Mitigação: relatório gera ticket, NÃO bloqueia evento. Time revisa falsos
 * positivos e adiciona whitelist em `config/finalization.php` se necessário.
 *
 * @see specs/008-finalizacao-mvp/research.md §3.3
 */
final class PiiDetector
{
    /**
     * Padrões nomeados. Adicionar/remover aqui requer mirror em {@see PiiScrubber::PATTERNS}.
     *
     * @var array<string, string>
     */
    public const PATTERNS = [
        'cpf' => '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/',
        'email' => '/[\w.+-]+@[\w-]+\.[\w.-]+/',
        'sus_card' => '/\b\d{15}\b/',
        'phone_br' => '/\(?\b\d{2}\)?\s?9?\d{4,5}-?\d{4}\b/',
        'rg_sp_rj' => '/\b\d{2}\.?\d{3}\.?\d{3}-?[\dXx]\b/',
    ];

    /**
     * Detecta PII em uma string. Retorna lista de findings sem expor o
     * valor matched (apenas nome do padrão e offset).
     *
     * @return list<array{pattern: string, offset: int, length: int}>
     */
    public static function detect(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $findings = [];

        foreach (self::PATTERNS as $patternName => $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($matches[0] as $match) {
                /** @var array{0: string, 1: int} $match */
                $findings[] = [
                    'pattern' => $patternName,
                    'offset' => $match[1],
                    'length' => strlen($match[0]),
                ];
            }
        }

        return $findings;
    }

    /**
     * Varre recursivamente uma estrutura (array/objeto serializável) e retorna
     * findings com o caminho até o campo onde o PII foi encontrado.
     *
     * @param array<string, mixed>|object|string $payload
     * @return list<array{pattern: string, field_path: string, offset: int, length: int}>
     */
    public static function detectInPayload(mixed $payload, string $pathPrefix = ''): array
    {
        if (is_string($payload)) {
            $findings = self::detect($payload);

            return array_map(
                static fn (array $f): array => [
                    'pattern' => $f['pattern'],
                    'field_path' => $pathPrefix === '' ? '$' : $pathPrefix,
                    'offset' => $f['offset'],
                    'length' => $f['length'],
                ],
                $findings,
            );
        }

        if (is_array($payload)) {
            $all = [];
            foreach ($payload as $key => $value) {
                $childPath = $pathPrefix === '' ? (string) $key : $pathPrefix.'.'.$key;
                $all = array_merge($all, self::detectInPayload($value, $childPath));
            }

            return $all;
        }

        // int/float/bool/null/object não-string: skip.
        return [];
    }
}
