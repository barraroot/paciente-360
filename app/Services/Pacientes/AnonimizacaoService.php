<?php

namespace App\Services\Pacientes;

use App\Events\Paciente\PacienteAnonimizado;
use App\Models\Paciente;
use App\Models\User;

/**
 * T113 — Serviço de anonimização de dados PII do paciente (FR-035 — LGPD).
 *
 * Zera todos os campos de dados pessoais identificáveis e marca
 * `anonimizado_em`. O paciente permanece na base para fins forenses
 * (audit_logs preservados), mas é oculto de todas as queries normais
 * pelo global scope `not_anonymized` em `Paciente`.
 *
 * @see App\Models\Paciente::scopeWithAnonymized()
 * @see specs/002-crm-pacientes/spec.md FR-035
 */
final class AnonimizacaoService
{
    /**
     * Anonimiza todos os campos PII do paciente e dispara o evento de auditoria.
     */
    public function anonymize(Paciente $paciente, User $executor): void
    {
        $paciente->fill([
            'cpf' => null,
            'documento_estrangeiro' => null,
            'email' => null,
            'telefone_primario' => null,
            'telefones_secundarios' => null,
            'endereco' => null,
            'data_nascimento' => null,
            'anonimizado_em' => now(),
        ]);

        $paciente->save();

        PacienteAnonimizado::dispatch($paciente, 'Anonimização solicitada via API');
    }
}
