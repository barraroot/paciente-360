<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * **T121 (Fase 8 — Lote B US-12.2)** — Plano comercial criado (versão 1).
 *
 * Emitido pelo PlanVersioningService quando Super Admin cria um plano novo.
 * Auditável; payload carrega apenas referências (plan_id + version + nome),
 * snapshot completo fica na tabela `plan_versions`.
 */
final class PlanoCriado implements Auditable
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $planId,
        public readonly int $planVersionId,
        public readonly int $version,
        public readonly string $planName,
        public readonly ?int $createdByUserId,
        public readonly Carbon $createdAt,
    ) {}

    public function auditAction(): string
    {
        return 'plan.created';
    }

    public function auditPayload(): array
    {
        return [
            'plan_id' => $this->planId,
            'plan_version_id' => $this->planVersionId,
            'version' => $this->version,
            'plan_name' => $this->planName,
            'created_at' => $this->createdAt->toIso8601String(),
        ];
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    public function auditTenantId(): ?int
    {
        return null; // global
    }

    public function auditUserId(): ?int
    {
        return $this->createdByUserId;
    }
}
