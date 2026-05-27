<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

use App\Domain\Ai\Context\Models\ConversationSummary;
use App\Domain\Ai\Context\Services\AiContextBudget;
use App\Domain\Ai\Context\Services\ConversationHistoryAssembler;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\WorkContext\Services\AiWorkContextService;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Support\Lgpd\PiiScrubber;
use Throwable;

/**
 * Monta o contexto final da IA: instruções (guardrails mínimos + clínica +
 * persona + RAG + resumo da conversa) e o prompt do paciente PSEUDONIMIZADO
 * antes de qualquer envio ao provedor (FR-042, Princípios I/III).
 *
 * Feature 017 (US1): inclui a janela verbatim mínima do histórico e, quando há
 * turnos já resumidos, o resumo rolante compacto — nunca a "janela vazia".
 */
final class AiContextBuilderService
{
    public function __construct(
        private readonly AiGuardrailEnforcer $enforcer,
        private readonly AiKnowledgeRetrievalService $retrieval,
        private readonly ConversationHistoryAssembler $history,
        private readonly AiWorkContextService $workContext,
        private readonly AiContextBudget $budget,
    ) {}

    public function build(AiPersona $persona, string $patientMessage, ?Conversation $conversation = null, ?int $currentMessageId = null): AiContext
    {
        [$ragSnippets, $ragSnippetIds] = $this->retrieveRag($persona, $patientMessage);

        $workContext = $this->workContext->getForTenant($persona->tenant_id);
        $renderedWorkContext = $this->workContext->renderForPrompt($workContext);
        $workContextVersion = $workContext?->version;

        // Bloco FIXO (inegociável): guardrails + persona + work context + personalização.
        $fixedInstructions = $this->enforcer->composeInstructions(
            $persona,
            [],
            $renderedWorkContext !== '' ? $renderedWorkContext : null,
        );

        $historyMessages = $conversation !== null
            ? $this->history->assemble($conversation, $currentMessageId)
            : [];

        $summaryModel = $conversation !== null ? $this->loadSummary($conversation) : null;
        $summaryText = ($summaryModel !== null && filled($summaryModel->summary_text)) ? $summaryModel->summary_text : null;

        $scrubbedPrompt = (string) PiiScrubber::scrub($patientMessage);

        // Orçamento de tokens (FR-021/023): descarta RAG → resumo → janela antiga,
        // nunca os guardrails nem a mensagem atual.
        $ceiling = (int) config('ai.matricial.history.input_token_ceiling', 6000);
        $fit = $this->budget->fit($ceiling, $fixedInstructions, $scrubbedPrompt, $ragSnippets, $summaryText, $historyMessages);

        $instructions = $fixedInstructions;
        if ($fit['ragSnippets'] !== []) {
            $instructions .= "\n\n# Base de Conhecimento (use apenas o que for relevante)\n\n".implode("\n\n---\n\n", $fit['ragSnippets']);
        }

        $summaryVersion = null;
        if ($fit['summary'] !== null) {
            $instructions .= "\n\n# Resumo da conversa até aqui (use para manter o contexto, não repita perguntas já respondidas)\n\n".$fit['summary'];
            $summaryVersion = $summaryModel?->version;
        }

        return new AiContext(
            instructions: $instructions,
            prompt: $scrubbedPrompt,
            ragSnippetIds: array_slice($ragSnippetIds, 0, count($fit['ragSnippets'])),
            historyMessages: $fit['historyMessages'],
            summaryVersion: $summaryVersion,
            workContextVersion: $workContextVersion,
        );
    }

    private function loadSummary(Conversation $conversation): ?ConversationSummary
    {
        try {
            return ConversationSummary::query()
                ->where('conversation_id', $conversation->id)
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Recuperação semântica com degradação graciosa: uma falha de RAG nunca
     * impede a resposta — apenas omite os trechos.
     *
     * @return array{0: list<string>, 1: list<int>}
     */
    private function retrieveRag(AiPersona $persona, string $patientMessage): array
    {
        try {
            $hits = $this->retrieval->retrieve($persona, $patientMessage);
        } catch (Throwable) {
            return [[], []];
        }

        $snippets = array_map(static fn (array $hit): string => $hit['content'], $hits);
        $ids = array_map(static fn (array $hit): int => $hit['id'], $hits);

        return [$snippets, $ids];
    }
}
