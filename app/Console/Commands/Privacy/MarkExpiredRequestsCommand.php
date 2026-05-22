<?php

declare(strict_types=1);

namespace App\Console\Commands\Privacy;

use App\Domain\Privacy\Models\ForgettingRequest;
use App\Domain\Privacy\Models\ForgettingStatus;
use App\Domain\Privacy\Models\PortabilityRequest;
use App\Domain\Privacy\Models\PortabilityStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * **T058 (Fase 8 — Lote A US-13.2)** — Marca solicitações vencidas sem resposta.
 *
 * Pré-requisito de AC-13.2.7 — transição automática de status para `expired`
 * quando `deadline_at < now()` e ainda não foi executada/negada.
 *
 * Roda daily 00:30 BRT (antes da janela útil) — assim quando admin abre o
 * painel ao iniciar o dia, as solicitações já estão sinalizadas. Cron via
 * routes/console.php — já agendado em T009 como `privacy:mark-expired`.
 *
 * **Sem execução automática de anonimização** — Princípio I exige que a
 * decisão de anonimizar seja humana (Admin Clínica). Vencido só vira alerta
 * crítico (R-8-7) — não conclui o esquecimento.
 */
final class MarkExpiredRequestsCommand extends Command
{
    protected $signature = 'privacy:mark-expired {--dry-run}';

    protected $description = 'Marca solicitações LGPD vencidas (deadline < now) como expired (AC-13.2.7).';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $now = Carbon::now();

        $forgettingExpired = ForgettingRequest::query()
            ->open()
            ->where('deadline_at', '<', $now)
            ->get();

        $portabilityExpired = PortabilityRequest::query()
            ->open()
            ->where('deadline_at', '<', $now)
            ->get();

        if (! $dryRun) {
            foreach ($forgettingExpired as $r) {
                $r->update(['status' => ForgettingStatus::Expired]);
                Log::critical('privacy.forgetting.expired', [
                    'tenant_id' => $r->tenant_id,
                    'patient_id' => $r->patient_id,
                    'forgetting_request_id' => $r->id,
                    'deadline_at' => $r->deadline_at->toIso8601String(),
                    'days_overdue' => (int) $r->deadline_at->diffInDays($now),
                ]);
            }

            foreach ($portabilityExpired as $r) {
                $r->update(['status' => PortabilityStatus::Expired]);
                Log::critical('privacy.portability.expired', [
                    'tenant_id' => $r->tenant_id,
                    'patient_id' => $r->patient_id,
                    'portability_request_id' => $r->id,
                    'deadline_at' => $r->deadline_at->toIso8601String(),
                    'days_overdue' => (int) $r->deadline_at->diffInDays($now),
                ]);
            }
        }

        $this->info(sprintf(
            'Marked expired: forgetting=%d portability=%d %s',
            $forgettingExpired->count(),
            $portabilityExpired->count(),
            $dryRun ? '[DRY-RUN]' : '',
        ));

        return self::SUCCESS;
    }
}
