<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Support\Lgpd\PiiScrubber;
use Throwable;

/**
 * Monta o contexto final da IA: instruções (guardrails mínimos + clínica +
 * persona + RAG) e o prompt do paciente PSEUDONIMIZADO antes de qualquer
 * envio ao provedor (FR-042, Princípios I/III).
 *
 * RAG (US4): recupera os trechos mais relevantes das bases ATIVAS associadas
 * à persona, filtrados por tenant na própria query de similaridade (G8).
 */
final class AiContextBuilderService
{
    public function __construct(
        private readonly AiGuardrailEnforcer $enforcer,
        private readonly AiKnowledgeRetrievalService $retrieval,
    ) {}

    public function build(AiPersona $persona, string $patientMessage): AiContext
    {
        [$ragSnippets, $ragSnippetIds] = $this->retrieveRag($persona, $patientMessage);

        $instructions = $this->enforcer->composeInstructions($persona, $ragSnippets);

        // Pseudonimiza PII do paciente antes de enviar ao LLM (CPF, telefone, etc.).
        $scrubbedPrompt = (string) PiiScrubber::scrub($patientMessage);

        return new AiContext($instructions, $scrubbedPrompt, $ragSnippetIds);
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
