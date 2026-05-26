<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

/**
 * Decisão determinística do AiGuardrailEnforcer sobre uma resposta gerada.
 *
 * `action` mapeia para ai_execution_logs.action.
 */
final readonly class GuardrailDecision
{
    public function __construct(
        public string $action,        // sent | escalated_human | redirected_scheduling | flagged_review
        public bool $shouldSend,
        public ?string $text,         // texto a enviar (apenas quando shouldSend)
        public bool $escalate,
        public bool $highPriority,    // urgência/emergência → prioridade alta
        public string $reason,
    ) {}

    public static function send(string $text): self
    {
        return new self('sent', true, $text, false, false, 'within_guardrails');
    }

    public static function escalate(string $action, string $reason, bool $highPriority = false): self
    {
        return new self($action, false, null, true, $highPriority, $reason);
    }
}
