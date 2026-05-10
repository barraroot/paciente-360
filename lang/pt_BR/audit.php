<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Painel de Auditoria — Paciente360 (US-2.4 — FR-035..038)
    |--------------------------------------------------------------------------
    |
    | Strings de UI/erro do painel de auditoria. Os nomes de "action"
    | (ex.: tenant.registered, user.login.succeeded) NÃO são traduzidos
    | aqui — são identificadores estáveis usados em filtros e métricas.
    | A camada de apresentação faz o mapping action→label se necessário.
    |
    */

    'title' => 'Log de auditoria',
    'description' => 'Histórico imutável de ações realizadas na sua clínica.',

    'filters' => [
        'from' => 'Data inicial',
        'to' => 'Data final',
        'action' => 'Ação',
        'actor_user_id' => 'Usuário (autor)',
        'per_page' => 'Itens por página',
        'reset' => 'Limpar filtros',
    ],

    'columns' => [
        'created_at' => 'Data/Hora',
        'actor' => 'Autor',
        'action' => 'Ação',
        'auditable' => 'Recurso',
        'ip' => 'IP',
        'request_id' => 'Req. ID',
    ],

    'actor' => [
        'system' => 'Sistema',
        'webhook' => 'Webhook',
        'deleted_user' => 'Usuário removido',
    ],

    'export' => [
        'button' => 'Exportar CSV',
        'filename_prefix' => 'audit-logs',
        'unauthorized' => 'Você não tem permissão para exportar o log de auditoria.',
    ],

    'errors' => [
        'forbidden' => 'Acesso ao log de auditoria é restrito a Admin Clínica e Financeiro.',
        'invalid_date_range' => 'Intervalo de datas inválido.',
    ],

    'empty' => 'Nenhum evento encontrado para os filtros selecionados.',

    'retention' => [
        'hot' => 'Logs com até 2 anos ficam disponíveis para consulta direta.',
        'cold' => 'Logs entre 2 e 5 anos são recuperados sob demanda (SLA 5 dias úteis).',
        'deletion' => 'Após 5 anos os registros são apagados (LGPD Art. 16).',
    ],

];
