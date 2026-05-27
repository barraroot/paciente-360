<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Domain\Ai\Context\Services\AiContextBudget;
use Laravel\Ai\Messages\Message as AiMessage;
use Laravel\Ai\Messages\MessageRole;
use PHPUnit\Framework\TestCase;

/**
 * Feature 017 (US3, FR-021/023) — teto de tokens e shedding por prioridade.
 */
final class AiContextBudgetTest extends TestCase
{
    private AiContextBudget $budget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->budget = new AiContextBudget;
    }

    /**
     * @return list<AiMessage>
     */
    private function history(int $n): array
    {
        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            $out[] = new AiMessage(MessageRole::User, "msg-{$i}-".str_repeat('x', 36));
        }

        return $out;
    }

    public function test_keeps_everything_when_within_ceiling(): void
    {
        $fit = $this->budget->fit(
            ceiling: 10000,
            fixedInstructions: str_repeat('F', 40),
            prompt: str_repeat('P', 40),
            ragSnippets: [str_repeat('R', 40), str_repeat('R', 40)],
            summary: str_repeat('S', 40),
            historyMessages: $this->history(3),
        );

        $this->assertCount(2, $fit['ragSnippets']);
        $this->assertNotNull($fit['summary']);
        $this->assertCount(3, $fit['historyMessages']);
    }

    public function test_sheds_rag_before_history(): void
    {
        // base = 10 (fixed) + 10 (prompt) = 20. rag 2x10, summary 10, history 3x~10 = 30.
        $fit = $this->budget->fit(
            ceiling: 50,
            fixedInstructions: str_repeat('F', 40),
            prompt: str_repeat('P', 40),
            ragSnippets: [str_repeat('R', 40), str_repeat('R', 40)],
            summary: str_repeat('S', 40),
            historyMessages: $this->history(3),
        );

        // RAG é a primeira a sair; a janela recente é preservada o máximo possível.
        $this->assertSame([], $fit['ragSnippets']);
        $this->assertNotEmpty($fit['historyMessages']);
        $this->assertLessThanOrEqual(3, count($fit['historyMessages']));
    }

    public function test_never_keeps_droppable_content_over_tiny_ceiling_but_does_not_error(): void
    {
        $fit = $this->budget->fit(
            ceiling: 5,
            fixedInstructions: str_repeat('F', 40),
            prompt: str_repeat('P', 40),
            ragSnippets: [str_repeat('R', 40)],
            summary: str_repeat('S', 40),
            historyMessages: $this->history(3),
        );

        // Tudo que é descartável é removido; guardrails e prompt (entradas) permanecem.
        $this->assertSame([], $fit['ragSnippets']);
        $this->assertNull($fit['summary']);
        $this->assertSame([], $fit['historyMessages']);
    }
}
