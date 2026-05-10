<?php

namespace App\Events\Billing;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento auditável de configuração de hard cap de IA (T223).
 *
 * Disparado quando o Admin Clínica ou Financeiro define, altera ou remove
 * o limite manual de mensagens IA. Payload registra o valor anterior e o
 * novo (conforme minimização LGPD — apenas metadata numérica, sem conteúdo).
 */
final class HardCapConfigured implements Auditable
{
    use IsAuditable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly ?int $oldCap,
        public readonly ?int $newCap,
    ) {}

    public function auditAction(): string
    {
        return 'tenant.hard_cap.configured';
    }

    public function auditableModel(): ?Model
    {
        return $this->tenant;
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
            'old' => $this->oldCap,
            'new' => $this->newCap,
        ];
    }
}
