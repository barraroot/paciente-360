<?php

return [
    'sidebar' => [
        'aria_label' => 'Navegação principal',
        'dashboard' => 'Dashboard',
        'agenda' => [
            'title' => 'Agenda',
            'calendar' => 'Calendário',
            'waitlist' => 'Lista de espera',
            'types' => 'Tipos de consulta',
            'schedule' => 'Horários',
            'sync' => 'Sincronização Google',
        ],
        'pacientes' => [
            'title' => 'Pacientes',
            'list' => 'Lista',
            'funil' => 'Funil Kanban',
            'import' => 'Importação',
            'mesclagem' => 'Mesclagem',
        ],
        'inbox' => [
            'title' => 'Inbox',
            'conversations' => 'Conversas',
            'channels' => 'Canais',
            'assignment_rules' => 'Regras de atribuição',
            'quick_replies' => 'Respostas rápidas',
        ],
        'prescriptions' => 'Receituários',
        'campaigns' => 'Campanhas',
        'reports' => [
            'title' => 'Relatórios',
            'executive' => 'Dashboard Executivo',
            'operational' => 'Operacional',
            'clinical' => 'Clínico',
        ],
        'integrations' => [
            'title' => 'Integrações',
            'webhooks' => 'Webhooks',
            'dlq' => 'Dead Letter Queue',
            'api_tokens' => 'Tokens API',
        ],
        'privacy' => [
            'title' => 'Privacidade & LGPD',
            'consents' => 'Consentimentos',
            'forgetting' => 'Direito ao Esquecimento',
            'portability' => 'Portabilidade de Dados',
        ],
        'settings' => [
            'title' => 'Configurações',
            'sessions' => 'Sessões / Tokens',
            'users' => 'Usuários',
            'audit' => 'Audit Logs',
            'plans' => 'Planos',
            'subscription' => 'Assinatura',
            'ai_usage' => 'Uso de IA',
        ],
    ],
    'topbar' => [
        'search_placeholder' => 'Buscar (em breve)',
        'search_disabled_title' => 'Busca global será implementada em breve.',
        'notifications_aria_label' => 'Notificações',
        'notifications_disabled_title' => 'Notificações serão implementadas em breve.',
        'open_user_menu' => 'Abrir menu do usuário',
        'open_sidebar' => 'Abrir menu lateral',
        'close_sidebar' => 'Fechar menu lateral',
        'collapse_sidebar' => 'Recolher barra lateral',
        'expand_sidebar' => 'Expandir barra lateral',
    ],
    'user_menu' => [
        'profile' => 'Meu perfil',
        'sessions' => 'Sessões e tokens',
        'logout' => 'Sair',
    ],
    'drawer' => [
        'aria_label' => 'Navegação lateral',
    ],
    'empty_state' => [
        'no_modules_title' => 'Sem acesso a módulos',
        'no_modules_message' => 'Sua conta não tem acesso a nenhum módulo do painel. Contate o administrador da clínica.',
    ],
    'panel_home' => [
        'welcome' => 'Bem-vindo de volta, :name.',
        'subtitle' => 'Use o menu lateral para começar.',
        'shortcuts_heading' => 'Atalhos rápidos',
    ],
];
