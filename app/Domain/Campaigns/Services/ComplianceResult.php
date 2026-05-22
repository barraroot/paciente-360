<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Services;

/**
 * **T144 (Fase 8 — Lote C US-9.3)** — Resultado de uma checagem do {@see CampaignComplianceGate}.
 *
 * Valores de `blockReason` consumidos pelo dispatcher para popular
 * `campaign_recipients.blocked_reason`:
 *   - `no_marketing_opt_in`     — paciente sem opt-in válido (Q24/Q25)
 *   - `no_template_approved`    — template não aprovado pela Meta ou sem unsubscribe
 *   - `outside_business_hours`  — fora do horário comercial do tenant (Q7)
 *   - `daily_limit_exceeded`    — limite diário do plano atingido (Q2)
 *   - `sair_received_24h`       — paciente enviou `/sair` nas últimas 24h
 *   - `no_reachable_channel`    — paciente sem canal conectado (edge case Módulo 1)
 */
final readonly class ComplianceResult
{
    public function __construct(
        public bool $passed,
        public ?string $blockReason = null,
        public ?string $details = null,
    ) {}

    public static function passed(): self
    {
        return new self(passed: true);
    }

    public static function blocked(string $reason, ?string $details = null): self
    {
        return new self(passed: false, blockReason: $reason, details: $details);
    }
}
