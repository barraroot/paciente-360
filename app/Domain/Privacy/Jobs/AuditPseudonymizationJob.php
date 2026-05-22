<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Jobs;

use App\Domain\Privacy\Services\PseudonymizationAuditor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * **T074 (Fase 8 — Lote A US-13.3)** — Job que executa o replay semanal (Q29).
 *
 * Enfileirado pelo cron `privacy:audit-pseudonymization-weekly` (T075). Fila
 * dedicada `privacy` (concurrency 2). Tries=1 — falha vira ticket Sentry,
 * não retry (executar 2× sobre amostra random poluiria a estatística).
 *
 * Timeout 600s para cobrir tenants com volume alto de audit_logs.
 */
final class AuditPseudonymizationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;
    public string $queue = 'privacy';

    public function __construct(
        public readonly int $samplePercent = 1,
    ) {}

    public function handle(PseudonymizationAuditor $auditor): void
    {
        $auditor->runRuntimeReplay($this->samplePercent);
    }
}
