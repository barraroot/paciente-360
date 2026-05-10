<?php

namespace App\Events\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contrato para eventos auditáveis (FR-035 — Princípio I e V).
 *
 * Qualquer evento que implementar esta interface será automaticamente
 * interceptado pelo `PersistAuditLogListener`, que persiste uma entrada
 * imutável em `audit_logs`.
 *
 * Use a trait `App\Events\Concerns\IsAuditable` para obter implementações
 * default de `auditTenantId()` e `auditUserId()` via container/auth.
 *
 * Exemplos de `auditAction()`: `tenant.registered`, `user.login.failed`,
 * `plan.upgraded`, `invitation.sent`.
 *
 * @see App\Events\Concerns\IsAuditable
 * @see App\Listeners\PersistAuditLogListener
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
interface Auditable
{
    /**
     * Ação auditada em snake_case dot-notation.
     * Ex.: `'tenant.registered'`, `'user.login.failed'`.
     */
    public function auditAction(): string;

    /**
     * Snapshot de dados relevantes ao evento (mínimo necessário — LGPD minimização).
     * Ex.: `['old' => [...], 'new' => [...]]`.
     *
     * @return array<string, mixed>
     */
    public function auditPayload(): array;

    /**
     * Model alvo da ação, se aplicável (ex.: o `User` que foi criado).
     * Null quando a ação não tem alvo Eloquent concreto.
     */
    public function auditableModel(): ?Model;

    /**
     * Override do `tenant_id` a ser gravado. Quando `null`, o listener
     * usa `app('tenant')?->id` do container.
     */
    public function auditTenantId(): ?int;

    /**
     * Override do `user_id` a ser gravado. Quando `null`, o listener
     * usa `auth()->id()`.
     */
    public function auditUserId(): ?int;

    /**
     * Override do `actor_type`. Quando `null`, o listener infere:
     * `'user'` se `user_id !== null`, caso contrário `'system'`.
     * Use `'webhook'` para handlers de webhooks externos.
     */
    public function auditActorType(): ?string;
}
