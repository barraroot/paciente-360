<?php

namespace App\Events\Billing;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\AiUsageMeter;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento auditável de hard cap de IA atingido (T223).
 *
 * Disparado quando `messages_count >= hard_cap` e o cap ainda não havia
 * sido acionado no ciclo atual. Payload registra apenas counts (LGPD).
 */
final class HardCapTriggered implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly AiUsageMeter $meter,
    ) {}

    public function auditAction(): string
    {
        return 'tenant.hard_cap.triggered';
    }

    public function auditableModel(): ?Model
    {
        return $this->meter;
    }

    public function auditTenantId(): ?int
    {
        return (int) $this->tenant->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'year_month' => $this->meter->year_month,
            'messages_count' => $this->meter->messages_count,
            'hard_cap' => $this->meter->hard_cap,
        ];
    }
}
