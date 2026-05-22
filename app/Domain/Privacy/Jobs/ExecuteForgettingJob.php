<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Jobs;

use App\Domain\Privacy\Models\ForgettingRequest;
use App\Domain\Privacy\Services\ForgettingExecutor;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * **T047 (Fase 8 — Lote A US-13.2)** — Job que aplica o esquecimento assincronamente.
 *
 * Uso opcional: na maioria dos casos, o admin executa o esquecimento via
 * UI síncrona (controller chama `ForgettingExecutor::execute()` direto).
 * Este job existe para:
 *   - Esquecimento agendado (ex.: bulk após cancelamento de tenant — Lote B Q20).
 *   - Retry policy quando execução falhar em DB transient errors.
 *
 * Fila dedicada `privacy` (concurrency 2 — research §1).
 */
final class ExecuteForgettingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public string $queue = 'privacy';

    public function __construct(
        public readonly int $forgettingRequestId,
        public readonly int $executedByUserId,
    ) {}

    public function handle(ForgettingExecutor $executor): void
    {
        $request = ForgettingRequest::query()->find($this->forgettingRequestId);

        if ($request === null) {
            // Solicitação foi deletada entre o enqueue e o execute — abort silencioso.
            return;
        }

        $user = User::query()->find($this->executedByUserId);
        if ($user === null) {
            $this->fail(new \RuntimeException("User #{$this->executedByUserId} não encontrado para executar esquecimento."));

            return;
        }

        $executor->execute($request, $user);
    }
}
