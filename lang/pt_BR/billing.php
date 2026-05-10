<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used for billing, subscription management,
    | AI usage metering, and webhook processing throughout the application.
    |
    */

    'plan' => [
        'starter' => 'Starter',
        'pro' => 'Pro',
        'enterprise' => 'Enterprise',
    ],

    'checkout' => [
        'cta' => 'Assinar',
        'success' => 'Assinatura ativada. Bem-vindo!',
        'cancelled' => 'Você cancelou o pagamento. Nenhuma cobrança foi feita.',
        'failed' => 'O pagamento falhou. Verifique os dados do cartão.',
    ],

    'subscription' => [
        'active' => 'Ativa',
        'past_due' => 'Em atraso',
        'cancelled' => 'Cancelada',
        'trial' => 'Em teste',
        'upgrade_success' => 'Plano atualizado.',
        'downgrade_scheduled' => 'O downgrade será aplicado no próximo ciclo (:date).',
        'professionals_quantity' => '{1} 1 profissional|[2,*] :count profissionais',
    ],

    'ai_usage' => [
        'meter_label' => 'Uso de IA neste mês',
        'hard_cap_reached' => 'Você atingiu o limite manual de uso de IA neste ciclo.',
        'overage_warning' => 'Você ultrapassou a cota incluída do plano. Mensagens adicionais serão cobradas em :price por mensagem.',
        'threshold_80_subject' => 'Aviso: 80% da cota de IA utilizada',
        'threshold_80_body' => 'Sua clínica utilizou 80% da cota de mensagens IA incluída no plano neste mês. Fique atento ao consumo para evitar cobranças de excedente.',
        'threshold_100_subject' => 'Cota IA atingida — mensagens adicionais serão cobradas como excedente',
        'threshold_100_body' => 'Sua clínica utilizou 100% da cota de mensagens IA incluída no plano. Mensagens adicionais serão cobradas como excedente conforme tabela do plano.',
    ],

    'hard_cap_triggered' => [
        'subject' => 'Limite de IA atingido',
        'greeting' => 'Olá,',
        'body' => 'O limite manual de :cap mensagens foi atingido no ciclo :month (:count mensagens consumidas). Novas mensagens de IA estão bloqueadas até o próximo ciclo ou até que o limite seja revisado.',
        'cta_label' => 'Gerenciar limite de IA',
    ],

    'webhook' => [
        'duplicate_event_ignored' => 'Evento duplicado ignorado.',
    ],

    'tenant_restricted' => 'Sua assinatura está em atraso há mais de 7 dias. Regularize para reativar este recurso.',

    'payment_failed' => [
        'subject' => 'Falha na cobrança da sua assinatura',
        'greeting' => 'Olá,',
        'body' => 'A última cobrança da sua assinatura falhou. Atualize seu método de pagamento para continuar usando o Paciente360.',
        'cta_label' => 'Atualizar pagamento',
        'body_outro' => 'Se já regularizou, ignore este e-mail.',
    ],

];
