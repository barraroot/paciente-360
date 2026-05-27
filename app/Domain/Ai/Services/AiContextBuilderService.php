<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

use App\Domain\Ai\Context\Models\ConversationSummary;
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
    ) {}

    public function build(AiPersona $persona, string $patientMessage, ?Conversation $conversation = null, ?int $currentMessageId = null): AiContext
    {
        [$ragSnippets, $ragSnippetIds] = $this->retrieveRag($persona, $patientMessage);

        $workContext = $this->workContext->getForTenant($persona->tenant_id);
        $renderedWorkContext = $this->workContext->renderForPrompt($workContext);
        $workContextVersion = $workContext?->version;

        $instructions = $this->enforcer->composeInstructions(
            $persona,
            $ragSnippets,
            $renderedWorkContext !== '' ? $renderedWorkContext : null,
        );

        $historyMessages = [];
        $summaryVersion = null;

        if ($conversation !== null) {
            $historyMessages = $this->history->assemble($conversation, $currentMessageId);

            $summary = $this->loadSummary($conversation);
            if ($summary !== null && filled($summary->summary_text)) {
                $instructions .= "\n\n# Resumo da conversa até aqui (use para manter o contexto, não repita perguntas já respondidas)\n\n".$summary->summary_text;
                $summaryVersion = $summary->version;
            }
        }

        // Pseudonimiza PII do paciente antes de enviar ao LLM (CPF, telefone, etc.).
        $scrubbedPrompt = (string) PiiScrubber::scrub($patientMessage);

        return new AiContext(
            instructions: $instructions,
            prompt: $scrubbedPrompt,
            ragSnippetIds: $ragSnippetIds,
            historyMessages: $historyMessages,
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
