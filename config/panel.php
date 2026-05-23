<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Cache TTL (segundos)
    |--------------------------------------------------------------------------
    | Tempo de cache no Redis para o payload de /api/v1/panel/home. Default
    | 30s — equilíbrio entre frescor e redução de carga em DB. Configurável
    | por ambiente para tuning rápido (staging com 5s para acelerar testes).
    */
    'cache_ttl_seconds' => (int) env('PANEL_HOME_CACHE_TTL', 30),

    /*
    |--------------------------------------------------------------------------
    | Auto-refresh interval (segundos)
    |--------------------------------------------------------------------------
    | Intervalo de auto-refresh do frontend quando a aba está visível.
    | Default 120s (2 min) per FR-027. Frontend lê via API ou via config Vite.
    */
    'autorefresh_seconds' => (int) env('PANEL_HOME_AUTOREFRESH', 120),

    /*
    |--------------------------------------------------------------------------
    | Upcoming appointments window (minutos)
    |--------------------------------------------------------------------------
    | Janela de filtro para "Próximas consultas". Q2 da clarification fixa
    | em 6 horas (360 minutos) no MVP. Mantido em config para abrir caminho
    | a configurabilidade per-tenant em spec futura sem refactor.
    */
    'upcoming_window_minutes' => (int) env('PANEL_HOME_UPCOMING_WINDOW', 360),

    /*
    |--------------------------------------------------------------------------
    | Limites de listas
    |--------------------------------------------------------------------------
    | Constantes derivadas da spec — fixos no MVP, expostos aqui para teste
    | e para clareza.
    */
    'limits' => [
        'upcoming_appointments' => 5,
        'attention_items' => 5,
        'recent_activity' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Janelas de detecção de alertas (Attention Items)
    |--------------------------------------------------------------------------
    */
    'attention' => [
        'conversation_escalated_minutes' => 10,
        'prescription_expiring_days' => 7,
        'paciente_funil_stale_hours' => 48,
        'webhook_dlq_lookback_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Estágios de funil considerados para alerta de paciente parado
    |--------------------------------------------------------------------------
    | Q3 da clarification: apenas estágios ativos não-terminais. Excluídos:
    | 'agendado' (já tem ação prevista), 'concluído' e 'perdido' (terminais).
    */
    'funil_alert_stages' => [
        'lead',
        'qualificando',
        'interessado',
        'agendamento',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allow-list de event types para timeline de atividade recente
    |--------------------------------------------------------------------------
    | FR-019 / Q LGPD: apenas eventos não-sensíveis aparecem na timeline.
    | Eventos como `paciente.viewed` (visualização) NÃO entram para evitar
    | vazamento de "Maria olhou o prontuário de João".
    */
    'recent_activity_allowlist' => [
        'paciente.created',
        'paciente.updated',
        'paciente.merged',
        'appointment.created',
        'appointment.confirmed',
        'appointment.realizada',
        'appointment.cancelada',
        'appointment.rescheduled',
        'prescription.created',
        'prescription.renewed',
        'conversation.assigned',
        'conversation.closed',
        'tag.created',
        'funil_stage.updated',
    ],
];
