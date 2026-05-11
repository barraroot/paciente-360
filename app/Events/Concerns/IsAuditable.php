<?php

namespace App\Events\Concerns;

use App\Models\Paciente;
use Illuminate\Database\Eloquent\Model;

/**
 * Implementações default para a interface `Auditable`.
 *
 * Use esta trait em eventos que implementam `Auditable` para evitar
 * boilerplate repetitivo. Os métodos podem ser sobrescritos na classe
 * concreta quando necessário.
 *
 * @see App\Events\Contracts\Auditable
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
trait IsAuditable
{
    /**
     * Por padrão, o payload é vazio. Sobrescreva para incluir dados.
     *
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [];
    }

    /**
     * Por padrão, não há model alvo.
     */
    public function auditableModel(): ?Model
    {
        return null;
    }

    /**
     * Resolve o `tenant_id` do container (tenant resolvido pelo middleware
     * `ResolveTenant`). Retorna `null` para ações de Super Admin sem tenant.
     */
    public function auditTenantId(): ?int
    {
        if (! app()->bound('tenant')) {
            return null;
        }

        $tenant = app('tenant');

        return (is_object($tenant) && isset($tenant->id)) ? (int) $tenant->id : null;
    }

    /**
     * Resolve o `user_id` do guard de autenticação padrão.
     * Retorna `null` para jobs/webhooks sem usuário autenticado.
     */
    public function auditUserId(): ?int
    {
        $id = auth()->id();

        return $id !== null ? (int) $id : null;
    }

    /**
     * Por padrão, não faz override do `actor_type` — o listener infere
     * automaticamente (`'user'` ou `'system'`).
     */
    public function auditActorType(): ?string
    {
        return null;
    }

    /**
     * Hook usado pelo `RegistraEventoTimelineListener` (T035 — Fase 2)
     * para resolver o `paciente_id` da linha em `eventos_timeline`.
     *
     * Default heurístico:
     *  - Se o evento tem prop pública `paciente` e ela tem `id`, usa.
     *  - Caso contrário, infere a partir de `auditableModel()`:
     *      - Se o model é uma `Paciente`, retorna seu id.
     *      - Se o model tem atributo `paciente_id` (ex.: `Anotacao`,
     *        `EventoTimeline`), retorna esse id.
     *  - Retorna `null` para eventos sem paciente associado (ex.:
     *    `PacientesImportados`, `PacientesExportados`).
     *
     * Eventos podem sobrescrever este método quando o paciente não vem
     * por `auditableModel()` (ex.: `LeadMovidoNoFunil` que carrega o
     * paciente pelo construtor).
     */
    public function relatedPacienteId(): ?int
    {
        if (property_exists($this, 'paciente') && is_object($this->paciente) && isset($this->paciente->id)) {
            return (int) $this->paciente->id;
        }

        $auditable = $this->auditableModel();

        if ($auditable === null) {
            return null;
        }

        if ($auditable instanceof Paciente) {
            return (int) $auditable->getKey();
        }

        $pacienteId = $auditable->getAttribute('paciente_id');

        return $pacienteId !== null ? (int) $pacienteId : null;
    }
}
