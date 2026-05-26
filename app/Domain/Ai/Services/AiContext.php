<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

/**
 * Contexto montado para uma execução da IA. `prompt` já está pseudonimizado
 * (Princípios I/III). `ragSnippetIds` registra quais trechos de base foram
 * usados (auditoria, sem conteúdo de PII).
 */
final readonly class AiContext
{
    /**
     * @param list<int> $ragSnippetIds
     */
    public function __construct(
        public string $instructions,
        public string $prompt,
        public array $ragSnippetIds = [],
    ) {}
}
