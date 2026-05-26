<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Assignment\Events\ExecucaoIAFalhou;
use App\Domain\Ai\Assignment\Events\IAEscalouParaHumano;
use App\Domain\Ai\Assignment\Events\RespostaIAEnviada;
use App\Domain\Ai\Assignment\Models\AiConversationAssignment;
use App\Domain\Ai\Assignment\Services\AiConversationAssignmentService;
use App\Domain\Ai\Execution\Models\AiExecutionLog;
use App\Domain\Ai\Persona\Events\PersonaAtribuidaAConversa;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Channel\Adapters\OutboundMessage;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Services\MessageDispatchService;
use App\Support\Lgpd\PiiScrubber;
use App\Support\Metrics\AiMetricsContract;
use Illuminate\Support\Str;

/**
 * Orquestra a resposta da IA para uma conversa (US3): resolve persona,
 * monta contexto, gera (laravel/ai), aplica guardrails determinísticos,
 * envia ou escala, e registra log auditável (Princípios III/V).
 */
final class AiMessageProcessor
{
    public function __construct(
        private readonly AiConversationAssignmentService $assignments,
        private readonly AiContextBuilderService $contextBuilder,
        private readonly AiGuardrailEnforcer $enforcer,
        private readonly MessageDispatchService $dispatch,
        private readonly AiMetricsContract $metrics,
    ) {}

    public function process(Conversation $conversation): void
    {
        $conversation->loadMissing('channel');

        // IA pausada (campo da conversa) → não responde (FR-033).
        if ($conversation->ai_paused_until !== null && $conversation->ai_paused_until->isFuture()) {
            return;
        }

        $existing = $this->assignments->findActive($conversation->id);
        if ($existing !== null && $existing->isPaused()) {
            return;
        }

        $persona = $this->assignments->resolveForConversation($conversation);
        if ($persona === null) {
            return; // canal sem persona ativa — IA não atende (FR-011)
        }

        if ($existing === null) {
            event(new PersonaAtribuidaAConversa(
                $conversation->tenant_id,
                $conversation->id,
                $persona->id,
                $conversation->channel?->type ?? '',
            ));
        }

        $patientMessage = $this->latestInboundText($conversation);
        if ($patientMessage === null || trim($patientMessage) === '') {
            return;
        }

        $persona->loadMissing('model', 'guardrails');
        $context = $this->contextBuilder->build($persona, $patientMessage);
        $correlationId = (string) Str::uuid();
        $this->tagSentry($conversation->tenant_id, $correlationId);
        $startedAt = microtime(true);

        // Geração — em falha a exceção PROPAGA para o job (retry/backoff/escala — FR-030c).
        $agent = new PersonaAgent($context->instructions);
        $response = $agent->prompt(
            $context->prompt,
            provider: $persona->model->provider,
            model: $persona->model->internal_identifier,
        );

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $this->metrics->responseLatency($latencyMs / 1000);

        $output = $this->decodeStructured($response->text);
        $decision = $this->enforcer->evaluate($output);
        $intent = is_string($output['intencao'] ?? null) ? $output['intencao'] : 'outro';
        $confidence = isset($output['confidence']) ? (float) $output['confidence'] : null;

        if ($decision->shouldSend) {
            $message = $this->dispatch->send(
                $conversation,
                new OutboundMessage(
                    conversationExternalThreadId: (string) $conversation->external_thread_id,
                    contentType: 'text',
                    body: $decision->text,
                    idempotencyKey: "ai:{$conversation->tenant_id}:{$conversation->id}:{$correlationId}",
                ),
                senderUserId: null,
                senderType: 'ai',
            );

            $this->log($conversation, $persona, $correlationId, $context, $intent, $confidence, $decision, $message->id, 'success', $latencyMs, $response->usage ?? null);
            $this->metrics->message($conversation->tenant_id);

            event(new RespostaIAEnviada($conversation->tenant_id, $conversation->id, $persona->id, $message->id, $intent));

            return;
        }

        $this->escalate($conversation, $this->assignments->findActive($conversation->id), $decision);
        $this->log($conversation, $persona, $correlationId, $context, $intent, $confidence, $decision, null, 'escalated', $latencyMs, $response->usage ?? null);
        $this->metrics->escalation($conversation->tenant_id, $decision->reason);

        event(new IAEscalouParaHumano($conversation->tenant_id, $conversation->id, $persona->id, $decision->reason));
    }

    /**
     * Chamado pelo job ao esgotar as tentativas (FR-030c): estado de erro,
     * sem mensagem ao paciente, log de falha.
     */
    public function markFailed(Conversation $conversation, string $errorType): void
    {
        $assignment = $this->assignments->findActive($conversation->id);
        $personaId = $assignment?->ai_persona_id;

        $assignment?->update(['status' => AiConversationAssignment::STATUS_ERROR]);

        AiExecutionLog::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'ai_persona_id' => $personaId,
            'channel_type' => $conversation->channel?->type,
            'correlation_id' => (string) Str::uuid(),
            'status' => 'failed',
            'action' => null,
            'error_message' => $errorType,
        ]);

        event(new ExecucaoIAFalhou($conversation->tenant_id, $conversation->id, $personaId, $errorType));
    }

    /**
     * Anexa tenant + correlation_id ao escopo do Sentry (R11) para correlacionar
     * falhas de provedor/timeout. No-op quando o Sentry não está instalado.
     */
    private function tagSentry(int $tenantId, string $correlationId): void
    {
        if (! class_exists('\Sentry\State\Scope')) {
            return;
        }

        \Sentry\configureScope(function ($scope) use ($tenantId, $correlationId): void {
            $scope->setTag('ai.tenant', (string) $tenantId);
            $scope->setTag('ai.correlation_id', $correlationId);
        });
    }

    private function latestInboundText(Conversation $conversation): ?string
    {
        /** @var Message|null $message */
        $message = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'in')
            ->latest('id')
            ->first();

        return $message?->body;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeStructured(string $text): array
    {
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            // Saída não estruturada → força escalonamento seguro.
            return ['resposta' => '', 'intencao' => 'outro', 'confidence' => 0.0, 'needs_human' => true];
        }

        return $decoded;
    }

    private function escalate(Conversation $conversation, ?AiConversationAssignment $assignment, GuardrailDecision $decision): void
    {
        $assignment?->update([
            'status' => AiConversationAssignment::STATUS_PAUSED,
            'paused_at' => now(),
        ]);

        $updates = ['ai_paused_until' => now()->addCentury()];
        if ($decision->highPriority) {
            $updates['priority'] = 'alta';
        }

        $conversation->update($updates);
    }

    /**
     * Registra a execução (já pseudonimizada — Princípios I/III/V).
     */
    private function log(
        Conversation $conversation,
        AiPersona $persona,
        string $correlationId,
        AiContext $context,
        string $intent,
        ?float $confidence,
        GuardrailDecision $decision,
        ?int $messageId,
        string $status,
        int $latencyMs,
        mixed $usage,
    ): void {
        AiExecutionLog::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'response_message_id' => $messageId,
            'ai_persona_id' => $persona->id,
            'ai_model_id' => $persona->ai_model_id,
            'channel_type' => $conversation->channel?->type,
            'correlation_id' => $correlationId,
            'prompt_summary' => Str::limit($context->prompt, 2000),
            'context_summary' => ['rag_snippet_ids' => $context->ragSnippetIds],
            'classified_intent' => $intent,
            'confidence_score' => $confidence,
            'response_summary' => $decision->text !== null
                ? Str::limit((string) PiiScrubber::scrub($decision->text), 2000)
                : null,
            'action' => $decision->action,
            'status' => $status,
            'latency_ms' => $latencyMs,
            'input_tokens' => is_object($usage) ? ($usage->promptTokens ?? null) : null,
            'output_tokens' => is_object($usage) ? ($usage->completionTokens ?? null) : null,
        ]);
    }
}
