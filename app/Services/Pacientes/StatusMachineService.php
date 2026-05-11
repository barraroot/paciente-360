<?php

namespace App\Services\Pacientes;

use App\Events\Paciente\PacienteStatusAlterado;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * T119 — Stub da máquina de estados de status de paciente (US-3.1).
 *
 * Implementação mínima que valida transições permitidas e aplica o novo
 * status. Será refinada em US5/T241 com máquina formal completa.
 *
 * Transições permitidas:
 *  - `lead → ativo, bloqueado`
 *  - `ativo → inativo, bloqueado`
 *  - `inativo → ativo, bloqueado`
 *  - `bloqueado → ativo` (requer Admin Clínica)
 *  - `* → bloqueado` (requer Admin Clínica)
 *
 * @see specs/002-crm-pacientes/spec.md Q6
 */
final class StatusMachineService
{
    /** @var array<string, list<string>> */
    public const TRANSICOES = [
        'lead' => ['ativo', 'bloqueado'],
        'ativo' => ['inativo', 'bloqueado'],
        'inativo' => ['ativo', 'bloqueado'],
        'bloqueado' => ['ativo'],
    ];

    /** @var list<string> Destinos que requerem Admin Clínica */
    private const REQUER_ADMIN = ['bloqueado'];

    /** @var list<string> Origens que requerem Admin Clínica para sair */
    private const ORIGEM_REQUER_ADMIN = ['bloqueado'];

    /**
     * Executa a transição de status com validação de permissão.
     *
     * @throws ValidationException
     */
    public function transition(Paciente $paciente, string $novoStatus, User $executor, ?string $motivo = null): Paciente
    {
        $statusAtual = $paciente->status;
        $transicoesPermitidas = self::TRANSICOES[$statusAtual] ?? [];

        if (! in_array($novoStatus, $transicoesPermitidas, true)) {
            throw ValidationException::withMessages([
                'status' => [__('paciente.status_transicao_invalida')],
            ]);
        }

        // Transições para/de bloqueado requerem Admin Clínica
        $requerAdmin = in_array($novoStatus, self::REQUER_ADMIN, true)
            || in_array($statusAtual, self::ORIGEM_REQUER_ADMIN, true);

        if ($requerAdmin && ! $executor->hasRole('admin-clinica')) {
            abort(403, 'Apenas Admin Clínica pode bloquear ou desbloquear pacientes.');
        }

        $paciente->update(['status' => $novoStatus]);

        PacienteStatusAlterado::dispatch($paciente, $statusAtual, $novoStatus, $motivo);

        return $paciente->refresh();
    }
}
