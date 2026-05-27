<?php

declare(strict_types=1);

namespace App\Domain\Ai\Context\Services;

use Laravel\Ai\Messages\Message as AiMessage;

/**
 * Feature 017 (US3, FR-021/023) — orçamento de tokens do contexto montado.
 *
 * Estima tokens (~4 chars/token, mesmo heurístico do AiEmbeddingService) e, quando
 * o contexto excede o teto, descarta conteúdo por prioridade:
 *   1º) trechos de RAG (do menos relevante);
 *   2º) o resumo rolante;
 *   3º) as mensagens MAIS ANTIGAS da janela.
 * NUNCA descarta os guardrails mínimos nem a mensagem atual do paciente.
 */
final class AiContextBudget
{
    /**
     * @param list<string> $ragSnippets
     * @param list<AiMessage> $historyMessages
     * @return array{ragSnippets: list<string>, summary: ?string, historyMessages: list<AiMessage>}
     */
    public function fit(
        int $ceiling,
        string $fixedInstructions,
        string $prompt,
        array $ragSnippets,
        ?string $summary,
        array $historyMessages,
    ): array {
        // Itens inegociáveis: guardrails/persona/work context + mensagem atual.
        $used = $this->estimate($fixedInstructions) + $this->estimate($prompt);

        // 1) RAG (do menos relevante = fim da lista) sai primeiro.
        while ($ragSnippets !== [] && $used + $this->estimateMany($ragSnippets) + $this->estimate((string) $summary) + $this->estimateMessages($historyMessages) > $ceiling) {
            array_pop($ragSnippets);
        }

        $used += $this->estimateMany($ragSnippets);

        // 2) Resumo sai em seguida.
        if ($summary !== null && $used + $this->estimate($summary) + $this->estimateMessages($historyMessages) > $ceiling) {
            // Tenta manter o resumo apenas se couber sem a janela inteira; senão, derruba.
            if ($used + $this->estimate($summary) > $ceiling) {
                $summary = null;
            }
        }

        if ($summary !== null) {
            $used += $this->estimate($summary);
        }

        // 3) Mensagens mais ANTIGAS da janela saem por último (preserva as recentes).
        while ($historyMessages !== [] && $used + $this->estimateMessages($historyMessages) > $ceiling) {
            array_shift($historyMessages);
        }

        return [
            'ragSnippets' => array_values($ragSnippets),
            'summary' => $summary,
            'historyMessages' => array_values($historyMessages),
        ];
    }

    public function estimate(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * @param list<string> $items
     */
    private function estimateMany(array $items): int
    {
        return array_sum(array_map(fn (string $i): int => $this->estimate($i), $items));
    }

    /**
     * @param list<AiMessage> $messages
     */
    private function estimateMessages(array $messages): int
    {
        return array_sum(array_map(fn (AiMessage $m): int => $this->estimate((string) $m->content), $messages));
    }
}
