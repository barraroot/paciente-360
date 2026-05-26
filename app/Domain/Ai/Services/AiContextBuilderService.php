<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Support\Lgpd\PiiScrubber;

/**
 * Monta o contexto final da IA: instruções (guardrails mínimos + clínica +
 * persona + RAG) e o prompt do paciente PSEUDONIMIZADO antes de qualquer
 * envio ao provedor (FR-042, Princípios I/III).
 *
 * O hook de RAG fica vazio até a US4 (recuperação semântica das bases).
 */
final class AiContextBuilderService
{
    public function __construct(private readonly AiGuardrailEnforcer $enforcer) {}

    public function build(AiPersona $persona, string $patientMessage): AiContext
    {
        // RAG (US4): recuperar trechos relevantes das bases ativas associadas.
        $ragSnippets = [];
        $ragSnippetIds = [];

        $instructions = $this->enforcer->composeInstructions($persona, $ragSnippets);

        // Pseudonimiza PII do paciente antes de enviar ao LLM (CPF, telefone, etc.).
        $scrubbedPrompt = (string) PiiScrubber::scrub($patientMessage);

        return new AiContext($instructions, $scrubbedPrompt, $ragSnippetIds);
    }
}
