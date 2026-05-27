<?php

declare(strict_types=1);

namespace App\Domain\Ai\Context\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Feature 017 (US3) — agente de SUMARIZAÇÃO da conversa.
 *
 * Comprime os turnos antigos (anteriores à janela verbatim) num resumo curto +
 * etapa do funil, para economizar tokens (FR-002a/022). Roda no modelo mais
 * barato e recebe conteúdo já pseudonimizado.
 */
final class ConversationSummarizerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(public string $systemInstructions) {}

    public function instructions(): Stringable|string
    {
        return $this->systemInstructions;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'funnel_stage' => $schema->string()->required(),
        ];
    }
}
