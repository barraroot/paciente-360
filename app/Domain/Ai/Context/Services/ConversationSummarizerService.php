<?php

declare(strict_types=1);

namespace App\Domain\Ai\Context\Services;

use App\Domain\Ai\Context\Agents\ConversationSummarizerAgent;
use App\Domain\Ai\Context\Models\ConversationSummary;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Support\Lgpd\PiiScrubber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Feature 017 (US3) — mantém o resumo rolante incremental de uma conversa.
 *
 * Só roda quando há turnos ANTERIORES à janela verbatim ainda não cobertos pelo
 * resumo (FR-002b/022) — caso contrário reusa o resumo existente, sem chamar o
 * modelo. Protegido por lock Redis por conversa (evita corrida — G3).
 */
final class ConversationSummarizerService
{
    public function maybeSummarize(Conversation $conversation, AiPersona $persona, ?int $currentMessageId = null): ?ConversationSummary
    {
        $window = (int) config('ai.matricial.history.window_messages', 6);

        $history = Message::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('sender_type', ['patient', 'ai', 'user'])
            ->where('content_type', 'text')
            ->whereNotNull('body')
            ->when($currentMessageId !== null, fn ($q) => $q->where('id', '<', $currentMessageId))
            ->orderBy('id')
            ->get();

        // Tudo cabe na janela verbatim → nada a resumir (sem overhead — FR-002b).
        if ($history->count() <= $window) {
            return null;
        }

        $older = $history->slice(0, $history->count() - $window)->values();
        $targetCoverId = (int) $older->last()->id;

        $existing = ConversationSummary::query()
            ->where('conversation_id', $conversation->id)
            ->first();

        // Resumo já cobre tudo o que saiu da janela → reusa (sem chamada ao modelo).
        if ($existing !== null && (int) $existing->covered_up_to_message_id >= $targetCoverId) {
            return $existing;
        }

        $lock = Cache::lock("ai:summary:{$conversation->id}", 10);
        if (! $lock->get()) {
            return $existing;
        }

        try {
            $newMessages = $existing !== null
                ? $older->filter(fn (Message $m): bool => (int) $m->id > (int) $existing->covered_up_to_message_id)->values()
                : $older;

            $transcript = $this->buildTranscript($newMessages);
            $generated = $this->summarize($persona, $existing?->summary_text, $transcript);

            return ConversationSummary::query()->updateOrCreate(
                ['conversation_id' => $conversation->id],
                [
                    'tenant_id' => $conversation->tenant_id,
                    'summary_text' => $generated['summary'],
                    'funnel_stage' => $generated['funnel_stage'],
                    'covered_up_to_message_id' => $targetCoverId,
                    'version' => ($existing->version ?? 0) + 1,
                ],
            );
        } catch (Throwable) {
            // Sumarização nunca quebra a resposta — degrada para o resumo atual.
            return $existing;
        } finally {
            $lock->release();
        }
    }

    private function buildTranscript(Collection $messages): string
    {
        return $messages->map(function (Message $m): string {
            $role = $m->direction === 'in' ? 'Paciente' : 'Atendente';

            return "{$role}: ".(string) PiiScrubber::scrub((string) $m->body);
        })->implode("\n");
    }

    /**
     * @return array{summary: string, funnel_stage: string}
     */
    private function summarize(AiPersona $persona, ?string $previousSummary, string $transcript): array
    {
        $maxTokens = (int) config('ai.matricial.history.summary_max_tokens', 400);

        $instructions = <<<MD
        Você condensa uma conversa de atendimento de clínica em um resumo MUITO curto
        (até ~{$maxTokens} tokens) em pt-BR, preservando apenas os fatos-chave:
        queixa principal, respostas de qualificação, cidade/local escolhido, preço já
        informado, e a intenção atual do paciente. NÃO invente. NÃO inclua dados
        sensíveis. Devolva também a etapa do funil em `funnel_stage` (um de:
        greeting, qualifying, value, pricing, location, slot, reservation, escalated).
        MD;

        $prompt = $previousSummary !== null && $previousSummary !== ''
            ? "Resumo anterior:\n{$previousSummary}\n\nNovas mensagens:\n{$transcript}\n\nAtualize o resumo incorporando as novas mensagens."
            : "Mensagens:\n{$transcript}\n\nProduza o resumo.";

        $persona->loadMissing('model');

        $response = (new ConversationSummarizerAgent($instructions))->prompt(
            $prompt,
            provider: $persona->model->provider,
            model: $persona->model->internal_identifier,
        );

        $decoded = json_decode($response->text, true);

        return [
            'summary' => is_array($decoded) && is_string($decoded['summary'] ?? null) ? $decoded['summary'] : '',
            'funnel_stage' => is_array($decoded) && is_string($decoded['funnel_stage'] ?? null) ? $decoded['funnel_stage'] : 'qualifying',
        ];
    }
}
