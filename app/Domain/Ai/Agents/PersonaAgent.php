<?php

declare(strict_types=1);

namespace App\Domain\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente dinâmico (laravel/ai) configurado a partir de uma persona da clínica.
 *
 * As instruções (system prompt) são compostas em runtime pelo
 * AiGuardrailEnforcer/AiContextBuilderService; provider/model são passados na
 * chamada `prompt()` a partir do AiModel da persona. A saída é ESTRUTURADA
 * para permitir o pós-processamento determinístico de segurança (Princípio III).
 */
final class PersonaAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    /**
     * @param list<Message> $historyMessages janela verbatim mínima já pseudonimizada (feature 017, US1)
     */
    public function __construct(
        public string $systemInstructions,
        public array $historyMessages = [],
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->systemInstructions;
    }

    /**
     * Histórico recente da conversa (US1) — nunca a "janela vazia" de antes.
     *
     * @return iterable<Message>
     */
    public function messages(): iterable
    {
        return $this->historyMessages;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resposta' => $schema->string(),
            'intencao' => $schema->string()->required(),
            'confidence' => $schema->number()->required(),
            'needs_human' => $schema->boolean()->required(),
        ];
    }
}
