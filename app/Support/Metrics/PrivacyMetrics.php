<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * **T080 (Fase 8 — Lote A US-13.3)** — Métricas Prometheus do módulo Privacidade.
 *
 * Conforme plan.md §7 — exportadas no endpoint `/metrics`:
 *   - consent_recorded_total{finalidade,channel}
 *   - consent_revoked_total{finalidade,channel}
 *   - forgetting_requests_total{status}
 *   - portability_requests_total{status}
 *   - pseudonymization_audit_findings_total{severity}
 *
 * Estende {@see AbstractModuleMetrics} (T005) — strategy defensiva contra
 * ausência de `promphp/prometheus_client_php`.
 */
final class PrivacyMetrics extends AbstractModuleMetrics
{
    /**
     * Counter: consentimento concedido por (finalidade, canal).
     */
    public function consentRecorded(string $finalidade, string $channel): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_privacy_consent_recorded_total',
            labels: ['finalidade' => $finalidade, 'channel' => $channel],
            help: 'Total de consentimentos concedidos por finalidade e canal.',
        );
    }

    /**
     * Counter: consentimento revogado por (finalidade, canal).
     */
    public function consentRevoked(string $finalidade, string $channel): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_privacy_consent_revoked_total',
            labels: ['finalidade' => $finalidade, 'channel' => $channel],
            help: 'Total de consentimentos revogados por finalidade e canal.',
        );
    }

    /**
     * Counter: solicitação de esquecimento por status.
     */
    public function forgettingRequest(string $status): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_privacy_forgetting_requests_total',
            labels: ['status' => $status],
            help: 'Total de solicitações de Direito ao Esquecimento por status.',
        );
    }

    /**
     * Counter: solicitação de portabilidade por status.
     */
    public function portabilityRequest(string $status): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_privacy_portability_requests_total',
            labels: ['status' => $status],
            help: 'Total de solicitações de Portabilidade por status.',
        );
    }

    /**
     * Counter: findings de auditoria de pseudonimização por severidade.
     * Spike em `critical` deve disparar alerta Sentry (Princípio I).
     */
    public function pseudonymizationAuditFinding(string $severity): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_privacy_pseudonymization_audit_findings_total',
            labels: ['severity' => $severity],
            help: 'Total de findings da auditoria de pseudonimização por severidade.',
        );
    }

    /**
     * Counter: exportações de auditoria de privacidade por escopo (consents/forgetting/portability/audit).
     */
    public function privacyAuditExported(string $scope, string $format): void
    {
        $this->recordCounterOrLog(
            name: 'paciente360_privacy_audit_exported_total',
            labels: ['scope' => $scope, 'format' => $format],
            help: 'Total de exportações de auditoria de privacidade por escopo e formato.',
        );
    }
}
