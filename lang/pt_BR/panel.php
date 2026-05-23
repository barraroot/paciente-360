<?php

return [
    'errors' => [
        'section_load_failed' => 'Não foi possível carregar esta seção.',
        'global_error' => 'Não foi possível atualizar. Tente novamente em instantes.',
    ],

    'attention' => [
        'conversation_escalated' => [
            'title' => 'Conversa aguardando atendimento humano',
            'description' => ':patient escalou para humano há :minutes minutos.',
        ],
        'prescription_expiring' => [
            'title' => 'Receita prestes a vencer',
            'description' => 'Receita de :patient vence em :days dia|Receita de :patient vence em :days dias',
        ],
        'paciente_funil_stale' => [
            'title' => 'Lead sem contato recente',
            'description' => ':patient está no estágio :stage há :hours horas sem follow-up.',
        ],
        'confirmation_pending' => [
            'title' => 'Confirmação manual pendente',
            'description' => 'Consulta de :patient (:time) precisa de confirmação manual.',
        ],
        'webhook_dlq' => [
            'title' => 'Webhook em fila morta',
            'description' => 'Evento :event_type falhou em :endpoint nas últimas 24 horas.',
        ],
    ],

    'activity' => [
        'paciente_created' => ':actor criou paciente :target',
        'paciente_updated' => ':actor atualizou paciente :target',
        'paciente_merged' => ':actor mesclou paciente :target',
        'appointment_created' => ':actor agendou consulta com :target',
        'appointment_confirmed' => ':actor confirmou consulta de :target',
        'appointment_realizada' => ':actor marcou consulta de :target como realizada',
        'appointment_cancelada' => ':actor cancelou consulta de :target',
        'appointment_rescheduled' => ':actor reagendou consulta de :target',
        'prescription_created' => ':actor emitiu receita para :target',
        'prescription_renewed' => ':actor renovou receita de :target',
        'conversation_assigned' => ':actor assumiu conversa com :target',
        'conversation_closed' => ':actor encerrou conversa com :target',
        'tag_created' => ':actor criou tag :target',
        'funil_stage_updated' => ':actor moveu :target no funil',
    ],
];
