<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Domain\Ai\Coalescing\Services\ConversationTurnCoordinator;
use App\Domain\Ai\Coalescing\Services\PassiveDebounceScheduler;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Permission\PermissionRegistrar;

/**
 * **T069 (Fase 18 — US1)** — fim da janela de debounce passivo.
 *
 * Fluxo:
 *  1. Restaura contexto de tenant (worker isolation — Princípio II).
 *  2. Lê estado atual do turno (versão + msgs).
 *  3. Se versão do job != versão atual → outro `FlushCoalescedTurnJob` mais
 *     novo já foi agendado (mensagem chegou durante a janela e re-agendou);
 *     este job é SUPERSEDED → no-op.
 *  4. Se atingiu max_turn_s (FR-003) OU max_reprocesses (FR-004) → força flush.
 *  5. Caso contrário, despacha `ProcessAiResponseJob` com `(conv, tenant,
 *     turn_version)` para a fila `ai` processar.
 */
final class FlushCoalescedTurnJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $tenantId,
        public readonly int $turnVersion,
    ) {
        $this->onQueue(config('ai.matricial.queue', 'ai'));
    }

    public function handle(
        ConversationTurnCoordinator $coordinator,
        PassiveDebounceScheduler $scheduler,
    ): void {
        $this->restoreTenant();

        $currentVersion = $coordinator->currentVersion($this->conversationId);
        if ($currentVersion === 0) {
            // Estado já foi limpo (dispatch outbound ocorreu) — no-op.
            return;
        }

        if ($currentVersion !== $this->turnVersion) {
            // Job superseded: mensagem nova chegou após o agendamento deste flush
            // e re-agendou outro FlushCoalescedTurnJob com versão maior. Ignora.
            return;
        }

        // Dispatcha o processamento da IA.
        ProcessAiResponseJob::dispatch(
            $this->conversationId,
            $this->tenantId,
            $this->turnVersion,
        );
    }

    private function restoreTenant(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant === null) {
            return;
        }
        app()->instance('tenant', $tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    }
}
