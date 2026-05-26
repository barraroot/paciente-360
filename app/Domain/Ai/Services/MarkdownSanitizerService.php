<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

/**
 * Sanitiza Markdown no back-end (FR-041, Princípio VII). O domínio é Markdown
 * puro: removemos HTML embutido, blocos executáveis, atributos de evento e
 * URLs perigosas em links/imagens. O preview no front-end ainda passa por
 * DOMPurify (defesa em profundidade).
 */
final class MarkdownSanitizerService
{
    /** Esquemas de URL não permitidos em links/imagens Markdown. */
    private const DANGEROUS_SCHEMES = ['javascript:', 'vbscript:', 'data:', 'file:'];

    /**
     * Sanitiza o conteúdo Markdown, retornando uma versão segura.
     */
    public function sanitize(string $markdown): string
    {
        if ($markdown === '') {
            return $markdown;
        }

        $clean = $markdown;

        // 1) Remove blocos executáveis inteiros (script/style/iframe/etc.) com conteúdo.
        $clean = (string) preg_replace(
            '#<\s*(script|style|iframe|object|embed|svg|math)[^>]*>.*?<\s*/\s*\1\s*>#is',
            '',
            $clean,
        );

        // 2) Remove qualquer tag HTML restante (raw HTML não é permitido no Markdown).
        $clean = (string) preg_replace('#</?[a-z][^>]*>#i', '', $clean);

        // 3) Neutraliza URLs perigosas em links/imagens Markdown: [txt](scheme:...) e ![alt](scheme:...).
        $clean = (string) preg_replace_callback(
            '#(!?\[[^\]]*\]\()\s*([^)\s]+)#i',
            function (array $m): string {
                $url = $m[2];
                $lower = strtolower(ltrim($url));

                foreach (self::DANGEROUS_SCHEMES as $scheme) {
                    if (str_starts_with($lower, $scheme)) {
                        return $m[1].'#';
                    }
                }

                return $m[1].$url;
            },
            $clean,
        );

        // 4) Remove resíduos de handlers inline (on*=...) caso tenham escapado.
        $clean = (string) preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $clean);

        return $clean;
    }

    /**
     * Indica se o conteúdo original contém construções inseguras
     * (útil para avisos no endpoint de validação).
     */
    public function containsUnsafe(string $markdown): bool
    {
        return $this->sanitize($markdown) !== $markdown;
    }
}
