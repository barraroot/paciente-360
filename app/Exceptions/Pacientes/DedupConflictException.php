<?php

namespace App\Exceptions\Pacientes;

use App\Models\Paciente;
use Illuminate\Support\Collection;

/**
 * Lançada pelo `PacienteService::create()` quando existe(m) paciente(s)
 * com mesmo CPF no mesmo tenant e `ignorar_duplicata` não foi passado.
 *
 * Mapeada em `bootstrap/app.php` para HTTP 409 com payload de candidatos
 * e ações sugeridas.
 *
 * @see App\Services\Pacientes\DedupService
 * @see specs/002-crm-pacientes/spec.md AC-3.1.3
 */
final class DedupConflictException extends \RuntimeException
{
    /**
     * @param Collection<int, Paciente> $candidatos
     */
    public function __construct(
        public readonly Collection $candidatos,
        string $message = 'Duplicata detectada.',
    ) {
        parent::__construct($message);
    }
}
