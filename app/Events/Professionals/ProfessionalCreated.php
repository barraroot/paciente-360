<?php

declare(strict_types=1);

namespace App\Events\Professionals;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Professional;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * **T009 (Spec 012)** — Profissional cadastrado via API tenant.
 *
 * Auditável. Listener `PersistAuditLogListener` (Fase 1) persiste em audit_logs.
 */
final class ProfessionalCreated implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(public readonly Professional $professional) {}

    public function auditAction(): string
    {
        return 'professional.created';
    }

    public function auditableModel(): ?Model
    {
        return $this->professional;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'name' => $this->professional->name,
            'council_type' => $this->professional->council_type,
            'council_number' => $this->professional->council_number,
            'council_state' => $this->professional->council_state,
            'has_user_link' => $this->professional->user_id !== null,
        ];
    }
}
