<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Assignment\Events\ExecucaoIAFalhou;
use App\Domain\Ai\Assignment\Events\IAEscalouParaHumano;
use App\Domain\Ai\Assignment\Events\RespostaIAEnviada;
use App\Domain\Ai\Assignment\Models\AiConversationAssignment;
use App\Domain\Ai\Assignment\Services\AiConversationAssignmentService;
use App\Domain\Ai\Context\Services\ConversationSummarizerService;
use App\Domain\Ai\Execution\Models\AiExecutionLog;
use App\Domain\Ai\Execution\Models\AiToolInvocation;
use App\Domain\Ai\Persona\Events\PersonaAtribuidaAConversa;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Tools\Support\ConversationToolFactory;
use App\Domain\Ai\Tools\Support\ToolContext;
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
        private readonly OutboundNameInjector $nameInjector,
        private readonly ConversationSummarizerService $summarizer,
        private readonly ConversationToolFactory $toolFactory,
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

        $inbound = $this->latestInbound($conversation);
        if ($inbound === null || trim((string) $inbound->body) === '') {
            return;
        }
        $patientMessage = (string) $inbound->body;

        $persona->loadMissing('model', 'guardrails');

        // US3 — mantém o resumo rolante atualizado (só roda quando há turnos
        // além da janela; reusa caso contrário). Nunca quebra a resposta.
        $this->summarizer->maybeSummarize($conversation, $persona, $inbound->id);

        $context = $this->contextBuilder->build($persona, $patientMessage, $conversation, $inbound->id);
        $correlationId = (string) Str::uuid();
        $this->tagSentry($conversation->tenant_id, $correlationId);
        $startedAt = microtime(true);

        // Eco/loop (FR-005): paciente colou a mensagem anterior da própria IA.
        $instructions = $context->instructions;
        if ($this->isEchoOfLastAiMessage($conversation, $patientMessage, $inbound->id)) {
            $instructions .= "\n\n# Observação\n\nO paciente reenviou/colou a sua mensagem anterior. NÃO repita a mesma pergunta — avance a conversa de forma natural.";
        }

        // Ferramentas de dados ao vivo escopadas à conversa (US5) — isolamento de
        // tenant/contato no data layer; vazio quando desabilitadas.
        $tools = $this->toolFactory->make(new ToolContext(
            tenantId: $conversation->tenant_id,
            conversationId: $conversation->id,
            patientId: $conversation->patient_id,
            contactPhone: $conversation->external_thread_id,
            correlationId: $correlationId,
        ));

        // Geração — em falha a exceção PROPAGA para o job (retry/backoff/escala — FR-030c).
        $agent = new PersonaAgent($instructions, $context->historyMessages, $tools);
        $response = $agent->prompt(
            $context->prompt,
            provider: $persona->model->provider,
            model: $persona->model->internal_identifier,
        );

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $this->metrics->responseLatency($latencyMs / 1000);
        $this->metrics->toolRoundTrips($conversation->tenant_id, $this->toolRoundTrips($correlationId) ?? 0);

        $output = $this->decodeStructured($response->text);
        $decision = $this->enforcer->evaluate($output);
        $intent = is_string($output['intencao'] ?? null) ? $output['intencao'] : 'outro';
        $confidence = isset($output['confidence']) ? (float) $output['confidence'] : null;

        if ($decision->shouldSend) {
            // Substitui {{primeiro_nome}} pelo nome real só na saída (FR-017) —
            // o nome nunca foi ao provedor; o log mantém a versão com marcador.
            $conversation->loadMissing('patient');
            $outboundBody = $this->nameInjector->inject((string) $decision->text, $conversation->patient?->nome);

            $message = $this->dispatch->send(
                $conversation,
                new OutboundMessage(
                    conversationExternalThreadId: (string) $conversation->external_thread_id,
                    contentType: 'text',
                    body: $outboundBody,
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

    /**
     * @return list<string>|null
     */
    private function toolNames(string $correlationId): ?array
    {
        $names = AiToolInvocation::query()
            ->where('correlation_id', $correlationId)
            ->pluck('tool_name')
            ->unique()
            ->values()
            ->all();

        return $names !== [] ? $names : null;
    }

    private function toolRoundTrips(string $correlationId): ?int
    {
        $count = AiToolInvocation::query()
            ->where('correlation_id', $correlationId)
            ->count();

        return $count > 0 ? $count : null;
    }

    private function latestInbound(Conversation $conversation): ?Message
    {
        /** @var Message|null $message */
        $message = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'in')
            ->latest('id')
            ->first();

        return $message;
    }

    /**
     * Detecta se a mensagem do paciente é (quase) idêntica à última resposta da
     * própria IA — o eco visto em conversas reais (FR-005). Comparação
     * determinística e barata (sem custo de modelo).
     */
    private function isEchoOfLastAiMessage(Conversation $conversation, string $patientMessage, int $currentMessageId): bool
    {
        /** @var Message|null $lastAi */
        $lastAi = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('id', '<', $currentMessageId)
            ->where('sender_type', 'ai')
            ->latest('id')
            ->first();

        if ($lastAi === null || $lastAi->body === null) {
            return false;
        }

        $normalize = static fn (string $value): string => preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';

        return $normalize($patientMessage) === $normalize((string) $lastAi->body);
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
            'summary_version' => $context->summaryVersion,
            'work_context_version' => $context->workContextVersion,
            'tools_used' => $this->toolNames($correlationId),
            'tool_round_trips' => $this->toolRoundTrips($correlationId),
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
