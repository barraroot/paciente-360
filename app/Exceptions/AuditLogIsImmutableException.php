<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lançada pelo Model `AuditLog` quando há tentativa de UPDATE ou DELETE.
 *
 * Registros de auditoria são imutáveis por design (LGPD — Princípio I;
 * Observabilidade — Princípio V). A imutabilidade é garantida em duas
 * camadas:
 *  1. Esta exceção (Model boot): falha antes de atingir o banco de dados.
 *  2. Trigger PostgreSQL `audit_logs_immutable()`: defesa em profundidade
 *     caso o Model seja bypassado via `DB::table()`.
 *
 * @see App\Models\AuditLog
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
final class AuditLogIsImmutableException extends RuntimeException
{
    public function __construct(string $operation = 'update')
    {
        parent::__construct(
            "AuditLog records are immutable — {$operation} is not allowed. "
            .'Use AuditLog::create() to insert new entries.'
        );
    }
}
