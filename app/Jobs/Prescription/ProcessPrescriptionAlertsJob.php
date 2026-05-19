<?php

namespace App\Jobs\Prescription;

use App\Domain\Prescription\Alert\AlertStatus;
use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Domain\Prescription\Alert\PrescriptionAlertIdempotencyKey;
use App\Models\Tenant;
use App\Support\Metrics\PrescriptionMetricsContract;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;

/**
 * T102 — Job principal de processamento de alertas de vencimento.
 *
 * Pode operar em modo:
 *  - Cross-tenant (padrão, produção): itera todos os tenants.
 *  - Single-tenant ($tenant passado): para testes e retry granular.
 *
 * Idempotência em duas camadas (research §4):
 *  1. Redis lock por chave `PrescriptionAlertIdempotencyKey::for(...)` TTL 90000s (~25h).
 *  2. DB UNIQUE `(prescription_id, alert_type)` como gate final.
 *
 * Para cada alert pendente elegível:
 *  - Verifica Redis lock — se já existe → skip (idempotência hit).
 *  - Caso contrário → seta lock + despacha DispatchPrescriptionAlertJob.
 */
class ProcessPrescriptionAlertsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        private readonly ?Tenant $tenant = null,
        private readonly bool $retryBlocked = false,
    ) {
        $this->onQueue('prescription-alerts');
    }

    public function handle(PrescriptionMetricsContract $metrics): void
    {
        $today = CarbonImmutable::today();
        $tenants = $this->resolveTenants();

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant, $today, $metrics);
        }
    }

    /**
     * @return array<Tenant>|iterable<Tenant>
     */
    private function resolveTenants(): iterable
    {
        if ($this->tenant !== null) {
            return [$this->tenant];
        }

        return Tenant::all();
    }

    private function processTenant(Tenant $tenant, CarbonImmutable $today, PrescriptionMetricsContract $metrics): void
    {
        $statuses = [AlertStatus::Pending->value];

        if ($this->retryBlocked) {
            $statuses[] = AlertStatus::BlockedNoTemplate->value;
            $statuses[] = AlertStatus::BlockedNoChannel->value;
        }

        $alerts = PrescriptionAlert::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', $statuses)
            ->whereDate('scheduled_for', '<=', $today->toDateString())
            ->limit(1000)
            ->get();

        $count = 0;

        foreach ($alerts as $alert) {
            // Sentry tags (T114)
            try {
                if (function_exists('\Sentry\configureScope')) {
                    \Sentry\configureScope(function ($scope) use ($alert): void {
                        $scope->setTag('prescription.id', (string) $alert->prescription_id);
                        $scope->setTag('alert.type', $alert->alert_type->value);
                    });
                }
            } catch (\Throwable) {
                // Sentry ausente em testes — não bloquear
            }

            // Redis lock idempotente
            $key = PrescriptionAlertIdempotencyKey::for(
                $alert->prescription_id,
                $alert->alert_type,
                $today,
            );

            $locked = Redis::set($key, 1, 'EX', 90000, 'NX');

            if ($locked === null || $locked === false) {
                // Já processado hoje — skip por idempotência
                $metrics->alertIdempotencyHit($tenant->id, $alert->alert_type->value);

                continue;
            }

            // Despacha job granular por alerta
            Bus::dispatch(new DispatchPrescriptionAlertJob($alert->id));
            $count++;
        }

        if ($count > 0) {
            $metrics->alertsProcessed($tenant->id, $count);
        }
    }

    /** @return array<string> */
    public function tags(): array
    {
        return ['prescription-alerts-process'];
    }
}
