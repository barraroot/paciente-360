<?php

declare(strict_types=1);

namespace App\Domain\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
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
 *
 * `#[MaxSteps(3)]` limita os round-trips de ferramenta por resposta (feature 017,
 * FR-032/SC-008).
 */
#[MaxSteps(3)]
final class PersonaAgent implements Agent, Conversational, HasProviderOptions, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * @param list<Message> $historyMessages janela verbatim mínima já pseudonimizada (feature 017, US1)
     * @param list<Tool> $tools ferramentas de dados ao vivo escopadas à conversa (feature 017, US5)
     */
    public function __construct(
        public string $systemInstructions,
        public array $historyMessages = [],
        public array $tools = [],
    ) {}

    /**
     * Ferramentas de dados ao vivo (US5) — vazio quando desabilitadas.
     *
     * @return iterable<Tool>
     */
    public function tools(): iterable
    {
        return $this->tools;
    }

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
     * Prompt caching do bloco estático no Anthropic (feature 017, US3) — corta
     * tokens de entrada repetidos turno a turno.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $name = $provider instanceof Lab ? $provider->value : (string) $provider;

        if ((bool) config('ai.matricial.prompt_caching', true) && str_contains(strtolower($name), 'anthropic')) {
            return ['cache_control' => ['type' => 'ephemeral']];
        }

        return [];
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
