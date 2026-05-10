<?php

namespace App\Listeners;

use App\Events\Contracts\Auditable;
use App\Models\AuditLog;
use App\Services\Audit\AuditAttributesBuilder;
use Illuminate\Support\Facades\Log;

/**
 * Listener único para eventos `Auditable` — persiste entradas em `audit_logs`.
 *
 * Registro: `EventServiceProvider::boot()` via
 * `Event::listen(Auditable::class, self::class)`.
 *
 * Em caso de falha de persistência, loga o erro via `Log::error()` e
 * NÃO relança a exceção — auditoria não pode interromper a request
 * principal (Princípio V: observabilidade não-obstrutiva).
 *
 * Extração de atributos delegada ao `AuditAttributesBuilder` para que
 * `AuditService::log()` produza entradas idênticas (critério T074).
 *
 * @see App\Events\Contracts\Auditable
 * @see App\Services\Audit\AuditAttributesBuilder
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
class PersistAuditLogListener
{
    public function __construct(
        private readonly AuditAttributesBuilder $builder,
    ) {}

    /**
     * Persiste o evento auditável em `audit_logs`.
     */
    public function __invoke(Auditable $event): void
    {
        try {
            $attributes = $this->builder->build($event);
            AuditLog::create($attributes);
        } catch (\Throwable $e) {
            Log::error('PersistAuditLogListener: failed to persist audit log', [
                'exception' => $e->getMessage(),
                'action' => $event->auditAction(),
            ]);
        }
    }
}
