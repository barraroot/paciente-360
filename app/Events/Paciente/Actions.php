<?php

namespace App\Events\Paciente;

/**
 * Constantes de ação para eventos do CRM de Pacientes (Fase 2).
 *
 * Centraliza as strings canônicas para evitar typos e facilitar
 * filtros em audit_logs/timeline.
 *
 * @see specs/002-crm-pacientes/spec.md § 2
 */
final class Actions
{
    public const PACIENTE_CRIADO = 'paciente.criado';

    public const PACIENTE_ATUALIZADO = 'paciente.atualizado';

    public const PACIENTE_STATUS_ALTERADO = 'paciente.status_alterado';

    public const PACIENTE_MESCLADO = 'paciente.mesclado';

    public const PACIENTE_MESCLAGEM_REVERTIDA = 'paciente.mesclagem_revertida';

    public const PACIENTE_ANONIMIZADO = 'paciente.anonimizado';

    public const PACIENTE_TAG_APLICADA = 'paciente.tag_aplicada';

    public const PACIENTE_TAG_REMOVIDA = 'paciente.tag_removida';

    public const LEAD_MOVIDO_NO_FUNIL = 'lead.movido_no_funil';

    public const PACIENTE_ANOTACAO_CRIADA = 'paciente.anotacao_criada';

    public const PACIENTE_ANOTACAO_RETRATADA = 'paciente.anotacao_retratada';

    public const PACIENTES_IMPORTADOS = 'paciente.imported';

    public const PACIENTES_EXPORTADOS = 'paciente.exported';
}
