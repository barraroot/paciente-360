<?php

namespace App\Exceptions\Pacientes;

/**
 * Lançada quando `ignorar_duplicata=true` é passado com CPF que já existe
 * no mesmo tenant. Diferente de `DedupConflictException` (que sugere ações),
 * esta exceção instrui que não é possível criar paralelo com mesmo CPF.
 *
 * Mapeada para HTTP 422.
 *
 * @see specs/002-crm-pacientes/tasks.md T102
 */
final class CpfDuplicadoException extends \RuntimeException
{
    public function __construct(
        string $message = 'Não é possível criar paciente paralelo com mesmo CPF. Use Mesclar ou modifique o CPF.',
    ) {
        parent::__construct($message);
    }
}
