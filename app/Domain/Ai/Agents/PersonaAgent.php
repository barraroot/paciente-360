<?php

declare(strict_types=1);

namespace App\Domain\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
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
final class PersonaAgent implements Agent, HasStructuredOutput
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
            'resposta' => $schema->string(),
            'intencao' => $schema->string()->required(),
            'confidence' => $schema->number()->required(),
            'needs_human' => $schema->boolean()->required(),
        ];
    }
}
