<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

use Laravel\Ai\Messages\Message;

/**
 * Contexto montado para uma execução da IA. `prompt` já está pseudonimizado
 * (Princípios I/III). `ragSnippetIds` registra quais trechos de base foram
 * usados (auditoria, sem conteúdo de PII).
 *
 * Feature 017: carrega também a janela verbatim de histórico (`historyMessages`,
 * já pseudonimizada) e as versões de resumo/work context que informaram a
 * resposta (auditoria FR-025).
 */
final readonly class AiContext
{
    /**
     * @param list<int> $ragSnippetIds
     * @param list<Message> $historyMessages
     */
    public function __construct(
        public string $instructions,
        public string $prompt,
        public array $ragSnippetIds = [],
        public array $historyMessages = [],
        public ?int $summaryVersion = null,
        public ?int $workContextVersion = null,
    ) {}
}
