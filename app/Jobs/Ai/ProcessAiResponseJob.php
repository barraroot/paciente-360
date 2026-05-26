<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Domain\Ai\Services\AiMessageProcessor;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Processa a resposta da IA de forma assíncrona (fila `ai`) — não bloqueia o
 * webhook (FR-028). Em falha, re-tenta com backoff; ao esgotar, `failed()`
 * marca a conversa em erro e escala para humano sem enviar nada (FR-030c).
 */
final class ProcessAiResponseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $tenantId,
    ) {
        $this->onQueue(config('ai.matricial.queue', 'ai'));
        $this->tries = (int) config('ai.matricial.retry.attempts', 3);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return (array) config('ai.matricial.retry.backoff', [10, 30, 60]);
    }

    public function handle(AiMessageProcessor $processor): void
    {
        $conversation = $this->resolveConversation();
        if ($conversation === null) {
            return;
        }

        $processor->process($conversation);
    }

    public function failed(Throwable $exception): void
    {
        $conversation = $this->resolveConversation();
        if ($conversation === null) {
            return;
        }

        app(AiMessageProcessor::class)->markFailed($conversation, class_basename($exception));
    }

    private function resolveConversation(): ?Conversation
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant === null) {
            return null;
        }

        // Restaura o contexto de tenant no worker (Princípio II).
        app()->instance('tenant', $tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        return Conversation::find($this->conversationId);
    }
}
