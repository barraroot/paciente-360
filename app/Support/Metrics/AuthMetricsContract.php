<?php

namespace App\Support\Metrics;

/**
 * T092 — Contrato para as 4 métricas Prometheus do domínio Auth (Fase 4 Lote J).
 *
 * Permite mocking em testes e injeção/substituição em ambientes específicos
 * (no-op em CI quando o exporter não está provisionado).
 *
 * @see AuthMetrics
 */
interface AuthMetricsContract
{
    /**
     * Incrementa counter de tentativas de login.
     *
     * @param string $result `success`, `invalid_credentials`, `account_locked`,
     *                       `tenant_suspended`, `rate_limited`
     */
    public function loginTotal(string $result): void;

    /**
     * Incrementa counter de tokens emitidos com sucesso.
     */
    public function tokenEmitidoTotal(): void;

    /**
     * Incrementa counter de tokens revogados, por motivo.
     *
     * @param string $motivo `manual`, `logout_all`, `admin_force`, `expired`,
     *                       `suspicious_use`
     */
    public function tokenRevogadoTotal(string $motivo): void;

    /**
     * Atualiza gauge de tokens ativos (não-expirados / não-revogados).
     */
    public function activeTokens(int $count): void;
}
