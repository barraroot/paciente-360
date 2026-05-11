<?php

namespace App\Policies;

use App\Models\Anotacao;
use App\Models\Paciente;
use App\Models\User;

/**
 * T032 — Policy de autorização para `Anotacao` (spec § 2.4).
 *
 * Implementa visibilidade granular por tipo de anotação:
 *  - `geral` → todos com `paciente.note.view:geral`.
 *  - `clinica` → apenas Médico + Admin Clínica (`paciente.note.view:clinica`).
 *  - `comportamental` → todos com `paciente.note.view:comportamental`.
 *  - `financeira` → apenas Admin Clínica (`paciente.note.view:financeira`).
 *
 * Sem update/delete: anotações são imutáveis (T026 — exception em boot).
 * Retratação acontece via `create` de nova anotação com
 * `retratacao_de_anotacao_id`.
 */
final class AnotacaoPolicy
{
    /**
     * Super Admin nunca vê PII clínica (FR-038).
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return false;
        }

        return null;
    }

    /**
     * Visibilidade base: poder listar anotações de um paciente requer
     * `paciente.view` (mesmo gate da PacientePolicy::view).
     */
    public function viewAny(User $user, Paciente $paciente): bool
    {
        return $user->can('paciente.view');
    }

    /**
     * Visibilidade granular: além de poder ver o paciente, precisa ter
     * a ability do tipo específico da anotação.
     */
    public function view(User $user, Anotacao $anotacao): bool
    {
        if (! $user->can('paciente.view')) {
            return false;
        }

        return $user->can("paciente.note.view:{$anotacao->tipo}");
    }

    public function create(User $user, Paciente $paciente): bool
    {
        return $user->can('paciente.view') && $user->can('paciente.note.write');
    }
}
