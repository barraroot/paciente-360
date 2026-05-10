<?php

/*
|--------------------------------------------------------------------------
| Billing — Paciente360
|--------------------------------------------------------------------------
|
| Política de cobrança recorrente do SaaS multi-tenant. Os parâmetros
| aqui codificam decisões de produto fechadas pela constituição
| (v1.2.0) e pelos requisitos funcionais FR-013, FR-014, FR-019:
|
|   - Modelo híbrido: plano base por profissional ativo + cota mensal
|     de mensagens IA inclusas + cobrança por mensagem excedente.
|   - Gateway: Stripe (Cashier).
|   - Falhas: 3 tentativas de cobrança → estado "Inadimplente".
|   - Carência: 7 dias antes de aplicar graceful degradation.
|   - Suspensão manual elegível após 37 dias totais (7 + 30).
|
| Mudanças nestes valores exigem amendment da constituição (escopo
| MVP fechado).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Credenciais Stripe
    |--------------------------------------------------------------------------
    |
    | Lidas SEMPRE do ambiente — nunca commitadas. Princípio VII
    | (Segurança Operacional). `webhook_secret` é usado para validar
    | assinatura HMAC dos webhooks recebidos em `/stripe/webhook`
    | (FR-013, idempotência via `events.id`).
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Moeda padrão
    |--------------------------------------------------------------------------
    |
    | MVP é Brasil-only — cobrança em Real. Internacionalização de
    | cobrança fica para amendment futuro.
    |
    */

    'currency' => env('CASHIER_CURRENCY', 'brl'),

    /*
    |--------------------------------------------------------------------------
    | Tentativas de cobrança antes de marcar Inadimplente
    |--------------------------------------------------------------------------
    |
    | FR-014 — após 3 falhas consecutivas de cobrança, o tenant entra
    | em estado "Inadimplente" e inicia a contagem de carência.
    |
    */

    'payment_retries' => env('BILLING_PAYMENT_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Dias de carência antes da degradação seletiva
    |--------------------------------------------------------------------------
    |
    | FR-014 — 7 dias após `overdue_since` antes de bloquear IA,
    | campanhas, integrações externas e configurações administrativas
    | pesadas. Login, inbox manual, agenda e prontuário continuam
    | acessíveis.
    |
    */

    'grace_days' => env('BILLING_GRACE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Dias até elegibilidade para suspensão manual
    |--------------------------------------------------------------------------
    |
    | FR-014 — 7 dias de carência + 30 dias adicionais em degradação
    | = 37 dias. A partir desse marco o Super Admin pode suspender o
    | tenant manualmente. Suspensão automática NÃO está no escopo do
    | MVP.
    |
    */

    'suspension_eligible_days' => env('BILLING_SUSPENSION_ELIGIBLE_DAYS', 37),

    /*
    |--------------------------------------------------------------------------
    | Hard cap default de gasto IA por tenant
    |--------------------------------------------------------------------------
    |
    | FR-019 — `null` significa "sem teto" (excedente cobrado
    | normalmente). Admin Clínica pode opt-in para um valor em centavos
    | de BRL via UI; ao atingir o cap, IA degrada para template
    | estático + escalonamento humano.
    |
    */

    'hard_cap_default' => env('BILLING_HARD_CAP_DEFAULT', null),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | Path do endpoint Stripe (rota será registrada em T072). Mantido
    | aqui para que a config seja a fonte de verdade do path —
    | mudanças não exigem refatoração do webhook handler.
    |
    */

    'webhook' => [
        'path' => env('BILLING_WEBHOOK_PATH', 'stripe/webhook'),
        'tolerance' => (int) env('BILLING_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rotas Premium (bloqueadas em inadimplência > 7 dias)
    |--------------------------------------------------------------------------
    |
    | Lista de nomes de rota que ficam inacessíveis quando
    | `tenant.restrictions_applied_at` está preenchido (FR-014).
    |
    | Ampliada a cada fase que introduz funcionalidades restringíveis:
    |  - Fase 2 (Inbox): ai.messages.*, campaigns.*
    |  - Fase 3 (Integrações): integrations.*
    |
    | Login e dados históricos (pacientes, agenda, audit) NÃO entram aqui
    | — princípio constitucional de preservar acesso a dados existentes.
    |
    */

    'premium_routes' => [],

];
