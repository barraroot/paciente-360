<?php

namespace App\Support\Tags;

use Transliterator;

/**
 * T038 — Normaliza nomes de tag para deduplicação case/accent-insensitive.
 *
 * `Diabético` e `diabetico` (e `DIABETICO`, `Diabetico  ` etc.) precisam
 * colapsar para a mesma chave. O UNIQUE `(tenant_id, nome_normalizado)`
 * em `tags` confia neste resultado.
 *
 * Regras:
 *  - Trim externo.
 *  - Lower (Unicode mb_strtolower com UTF-8).
 *  - Remoção de acentos via `Transliterator` (`Latin-ASCII`); fallback
 *    `iconv` quando `intl` não estiver carregado.
 *  - Múltiplos espaços internos → 1 espaço.
 *  - Prefixo `sys:` é preservado intacto após normalização (tags sistêmicas
 *    são reservadas e não passam pelo lower padrão do usuário).
 */
final class TagNormalizer
{
    public static function normalize(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return '';
        }

        // Tags sistêmicas (`sys:`) já vêm canônicas — preservamos o prefixo
        // mas normalizamos o resto.
        $isSystem = str_starts_with($trimmed, 'sys:');
        if ($isSystem) {
            $body = substr($trimmed, 4);
            $bodyNorm = self::applyNormalization($body);

            return 'sys:'.$bodyNorm;
        }

        return self::applyNormalization($trimmed);
    }

    private static function applyNormalization(string $value): string
    {
        // Lower preservando UTF-8.
        $lower = mb_strtolower($value, 'UTF-8');

        // Remover acentos: Transliterator é o caminho preferido.
        if (class_exists(Transliterator::class)) {
            $tr = Transliterator::create('Any-Latin; Latin-ASCII');
            if ($tr instanceof Transliterator) {
                $transliterated = $tr->transliterate($lower);
                if (is_string($transliterated)) {
                    $lower = $transliterated;
                }
            }
        } else {
            // Fallback iconv para ambientes sem intl.
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
            if ($converted !== false) {
                $lower = $converted;
            }
        }

        // Reduzir espaços múltiplos a um só.
        $collapsed = preg_replace('/\s+/u', ' ', $lower);

        return $collapsed !== null ? trim($collapsed) : trim($lower);
    }
}
