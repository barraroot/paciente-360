<?php

declare(strict_types=1);

namespace App\Events\Professionals;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Professional;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * **T009 (Spec 012)** — Profissional editado.
 *
 * Auditável. Payload inclui diff de campos alterados.
 */
final class ProfessionalUpdated implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    /**
     * @param array<string, array{old: mixed, new: mixed}> $changes
     */
    public function __construct(
        public readonly Professional $professional,
        public readonly array $changes = [],
    ) {}

    public function auditAction(): string
    {
        return 'professional.updated';
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
            'changes' => $this->changes,
        ];
    }
}
