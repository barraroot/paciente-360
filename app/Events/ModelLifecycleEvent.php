<?php

namespace App\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento disparado pela trait `RecordsActivity` nos hooks Eloquent
 * (`created`, `updated`, `deleted`).
 *
 * A `action` segue o padrão `<model_singular>.<lifecycle>`, por exemplo:
 * `user.created`, `tenant.updated`, `professional.deleted`.
 *
 * O payload captura o snapshot do model no momento do evento:
 *  - `created`: apenas `new` (original está vazio).
 *  - `updated`: `old` + `new` com apenas os atributos alterados (`dirty`).
 *  - `deleted`: apenas `old` (atributos antes da deleção).
 *
 * @see App\Models\Concerns\RecordsActivity
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
final class ModelLifecycleEvent implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    /**
     * @param array<string, mixed> $payloadData
     */
    public function __construct(
        private readonly string $action,
        private readonly array $payloadData,
        private readonly ?Model $target,
    ) {}

    public function auditAction(): string
    {
        return $this->action;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return $this->payloadData;
    }

    public function auditableModel(): ?Model
    {
        return $this->target;
    }
}
