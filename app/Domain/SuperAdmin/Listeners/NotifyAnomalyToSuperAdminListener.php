<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Listeners;

use App\Domain\SuperAdmin\Events\AnomaliaDetectada;
use App\Domain\SuperAdmin\Models\AnomalySeverity;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * **T134 (Fase 8 — Lote B US-12.3)** — Notifica Super Admin (AC-12.3.4 / Q22).
 *
 * Política de notificação (Q22):
 *   - Sempre: log estruturado + inbox interna (entrada criada implicitamente
 *     no painel Filament — AnomaliesPage lê de `anomalies_detected`)
 *   - Severity=critical: ADICIONALMENTE envia e-mail crítico ao endereço
 *     configurado em `config('finalization.super_admin_alert_email')` ou
 *     fallback para `config('mail.from.address')`
 *
 * **MVP — e-mail real DEFERRED**: por enquanto, log marker permite ao
 * Sentry/log aggregator disparar PagerDuty/similar via integração externa.
 * Mail::raw para o endereço configurado fica ativo para validação manual.
 *
 * Auto-discovered. ShouldQueue para não bloquear cron de detecção.
 */
final class NotifyAnomalyToSuperAdminListener implements ShouldQueue
{
    public function handle(AnomaliaDetectada $event): void
    {
        // 1. Log estruturado — consumido por inbox interna + aggregator externo.
        $logChannel = $event->severity === AnomalySeverity::Critical ? 'critical' : 'warning';
        Log::log($logChannel, 'super_admin.anomaly.detected', [
            'anomaly_id' => $event->anomalyId,
            'categoria' => $event->categoria->value,
            'tenant_id' => $event->tenantId,
            'severity' => $event->severity->value,
            'threshold_breached' => $event->thresholdBreached,
            'detected_at' => $event->detectedAt->toIso8601String(),
        ]);

        // 2. E-mail crítico apenas em severity=critical.
        if ($event->severity !== AnomalySeverity::Critical) {
            return;
        }

        $alertEmail = config('finalization.super_admin_alert_email', config('mail.from.address'));

        if (! is_string($alertEmail) || $alertEmail === '') {
            Log::warning('super_admin.anomaly.email_skip_no_address', ['anomaly_id' => $event->anomalyId]);

            return;
        }

        $body = sprintf(
            "Anomalia crítica detectada na plataforma Paciente360.\n\n".
            "Categoria: %s\nTenant: %s\nSeveridade: %s\n\nDetectada em: %s\n\nDetalhes:\n%s\n\n".
            "Acesse o painel Super Admin → Anomalias para reconhecer e investigar.",
            $event->categoria->label(),
            $event->tenantId !== null ? "#{$event->tenantId}" : 'Global (plataforma)',
            strtoupper($event->severity->value),
            $event->detectedAt->toIso8601String(),
            json_encode($event->thresholdBreached, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        try {
            Mail::raw($body, function ($message) use ($alertEmail, $event): void {
                $message->to($alertEmail)
                    ->subject(sprintf(
                        '[CRÍTICO] Anomalia %s detectada — Paciente360',
                        $event->categoria->label(),
                    ));
            });
        } catch (\Throwable $e) {
            // Falha de e-mail não pode quebrar o detector — apenas log.
            Log::error('super_admin.anomaly.email_send_failed', [
                'anomaly_id' => $event->anomalyId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
