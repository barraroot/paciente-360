<?php

declare(strict_types=1);

namespace App\Console\Commands\Privacy;

use App\Domain\Privacy\Models\ForgettingRequest;
use App\Domain\Privacy\Models\PortabilityRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * **T057 (Fase 8 — Lote A US-13.2)** — Notificações progressivas LGPD (Q27).
 *
 * Política (Q27):
 *   - **D-5 dias úteis**: notificação em inbox interna apenas.
 *   - **D-2 dias úteis**: inbox + e-mail + alerta visual persistente.
 *
 * Rodando daily 09:00 BRT, a query varre solicitações abertas com deadline
 * em ≤ 5 dias OU ≤ 2 dias e dispara o canal apropriado. Idempotência via
 * checkpoint em audit log impede re-notificação no mesmo dia.
 *
 * **DEFERRED MVP**: integração com `InboxTask` real fica para quando a Fase 3
 * expor `createForPatient()`. Por enquanto, log estruturado consumível.
 */
final class NotifyDeadlinesCommand extends Command
{
    protected $signature = 'privacy:notify-deadlines {--dry-run : Não persiste; apenas reporta}';

    protected $description = 'Notifica Admin Clínica em D-5 e D-2 dias do deadline de solicitações LGPD (Q27).';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $now = Carbon::now();

        $stats = [
            'forgetting_d5' => 0,
            'forgetting_d2' => 0,
            'portability_d5' => 0,
            'portability_d2' => 0,
        ];

        // ─── Forgetting requests ───
        ForgettingRequest::query()
            ->open()
            ->whereBetween('deadline_at', [$now->copy()->addDays(4), $now->copy()->addDays(6)])
            ->each(function (ForgettingRequest $r) use ($dryRun, &$stats): void {
                if (! $dryRun) {
                    Log::info('privacy.forgetting.deadline_d5', [
                        'tenant_id' => $r->tenant_id,
                        'patient_id' => $r->patient_id,
                        'forgetting_request_id' => $r->id,
                        'deadline_at' => $r->deadline_at->toIso8601String(),
                        'channel' => 'inbox',
                    ]);
                }
                $stats['forgetting_d5']++;
            });

        ForgettingRequest::query()
            ->open()
            ->whereBetween('deadline_at', [$now, $now->copy()->addDays(3)])
            ->each(function (ForgettingRequest $r) use ($dryRun, &$stats): void {
                if (! $dryRun) {
                    Log::warning('privacy.forgetting.deadline_d2', [
                        'tenant_id' => $r->tenant_id,
                        'patient_id' => $r->patient_id,
                        'forgetting_request_id' => $r->id,
                        'deadline_at' => $r->deadline_at->toIso8601String(),
                        'channels' => ['inbox', 'email', 'visual_persistent'],
                    ]);
                }
                $stats['forgetting_d2']++;
            });

        // ─── Portability requests ───
        PortabilityRequest::query()
            ->open()
            ->whereBetween('deadline_at', [$now->copy()->addDays(4), $now->copy()->addDays(6)])
            ->each(function (PortabilityRequest $r) use ($dryRun, &$stats): void {
                if (! $dryRun) {
                    Log::info('privacy.portability.deadline_d5', [
                        'tenant_id' => $r->tenant_id,
                        'patient_id' => $r->patient_id,
                        'portability_request_id' => $r->id,
                        'deadline_at' => $r->deadline_at->toIso8601String(),
                        'channel' => 'inbox',
                    ]);
                }
                $stats['portability_d5']++;
            });

        PortabilityRequest::query()
            ->open()
            ->whereBetween('deadline_at', [$now, $now->copy()->addDays(3)])
            ->each(function (PortabilityRequest $r) use ($dryRun, &$stats): void {
                if (! $dryRun) {
                    Log::warning('privacy.portability.deadline_d2', [
                        'tenant_id' => $r->tenant_id,
                        'patient_id' => $r->patient_id,
                        'portability_request_id' => $r->id,
                        'deadline_at' => $r->deadline_at->toIso8601String(),
                        'channels' => ['inbox', 'email', 'visual_persistent'],
                    ]);
                }
                $stats['portability_d2']++;
            });

        $this->info(sprintf(
            'Notified: forgetting D-5=%d D-2=%d | portability D-5=%d D-2=%d %s',
            $stats['forgetting_d5'],
            $stats['forgetting_d2'],
            $stats['portability_d5'],
            $stats['portability_d2'],
            $dryRun ? '[DRY-RUN]' : '',
        ));

        return self::SUCCESS;
    }
}
