<?php

namespace App\Domain\Prescription\Prescription;

enum CancellationReasonCategory: string
{
    case ErroEmissao = 'erro_emissao';

    case DesistenciaPaciente = 'desistencia_paciente';

    case Substituicao = 'substituicao';

    case Outro = 'outro';
}
