<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used for tenant registration, onboarding,
    | and account status messages throughout the application.
    |
    */

    'register' => [
        'success' => 'Clínica cadastrada com sucesso. Bem-vindo ao Paciente360!',
        'cnpj_taken' => 'Este CNPJ já está cadastrado.',
        'slug_taken' => 'Este endereço já está em uso. Escolha outro.',
        'slug_invalid' => 'O endereço deve conter apenas letras minúsculas, números e hífens.',
        'slug_reserved' => 'Este endereço é reservado e não pode ser usado.',
        'terms_required' => 'Você precisa aceitar os termos de uso para continuar.',
    ],

    'suspended' => [
        'title' => 'Conta suspensa',
        'message' => 'Sua clínica está suspensa por inadimplência. Regularize o pagamento para reativar.',
    ],

    'overdue' => [
        'banner' => 'Sua assinatura está em atraso desde :since. Regularize para evitar suspensão.',
    ],

    'onboarding' => [
        'step_completed' => 'Etapa concluída.',
        'all_steps_completed' => 'Onboarding finalizado!',
        'steps' => [
            'clinic_data' => 'Dados da clínica',
            'first_professional' => 'Primeiro profissional',
            'channel_connection' => 'Conexão de canal',
            'schedule_setup' => 'Configuração de agenda',
            'ai_knowledge_base' => 'Base de conhecimento de IA',
        ],
    ],

    'welcome' => [
        'subject' => 'Bem-vindo ao Paciente360',
        'greeting' => 'Olá, :name!',
        'body_intro' => 'Sua clínica :clinic foi criada com sucesso.',
        'cta_label' => 'Acessar painel',
        'body_outro' => 'Você está em período de avaliação de 14 dias. Aproveite para configurar profissionais, canais e agenda.',
    ],

];
