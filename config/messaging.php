<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Messaging Providers
    |--------------------------------------------------------------------------
    |
    | Configuração dos provedores de mensageria externos.
    | Twilio: WhatsApp Business (NC-1/Q1 — substitui Meta Cloud API direta).
    | Meta: Instagram Direct via Graph API direta (Twilio não suporta IG DM).
    |
    */

    'providers' => [

        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'whatsapp_from_default' => env('TWILIO_WHATSAPP_FROM_DEFAULT', 'whatsapp:+14155238886'),
            'content_api_version' => env('TWILIO_CONTENT_API_VERSION', '2010-04-01'),
        ],

        'meta' => [
            'app_id' => env('META_APP_ID'),
            'app_secret' => env('META_APP_SECRET'),
            'graph_api_version' => env('META_GRAPH_API_VERSION', 'v21.0'),
            'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Retenção de Dados (LGPD — Princípio I)
    |--------------------------------------------------------------------------
    |
    | Configuração de retenção de mensagens, mídia e eventos de webhook.
    | Valores válidos para message_months: [6, 60] — padrão 24.
    | Valores válidos para media_months: [6, 24] — padrão 12.
    | webhook_events_days: purge de eventos processados (não auditáveis).
    |
    */

    'retention' => [
        'message_months' => (int) env('MESSAGING_RETENTION_MESSAGE_MONTHS', 24),
        'media_months' => (int) env('MESSAGING_RETENTION_MEDIA_MONTHS', 12),
        'webhook_events_days' => (int) env('MESSAGING_RETENTION_WEBHOOK_EVENTS_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-resolve de Conversas
    |--------------------------------------------------------------------------
    |
    | Conversas sem resposta são resolvidas automaticamente após este período.
    | Válido range: [24, 168] horas (1h a 7 dias). Padrão: 72h.
    |
    */

    'auto_resolve' => [
        'hours' => (int) env('MESSAGING_AUTO_RESOLVE_HOURS', 72),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pausa de IA (Modo Humano Assume)
    |--------------------------------------------------------------------------
    |
    | Tempo de pausa da IA quando atendente humano assume uma conversa.
    | Válido range: [5, 240] minutos. Padrão: 30 minutos.
    | Fase 4 consumirá ai_paused_until + ConversaAssumidaPorHumano event.
    |
    */

    'ai_pause' => [
        'minutes' => (int) env('MESSAGING_AI_PAUSE_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Atribuição Automática
    |--------------------------------------------------------------------------
    |
    | Limites para auto-assign de conversas por atendente.
    | max_per_user: máximo de conversas abertas por atendente (default 15).
    | user_idle_minutes: minutos sem atividade para considerar atendente inativo (default 5).
    |
    */

    'auto_assign' => [
        'max_per_user' => (int) env('MESSAGING_AUTO_ASSIGN_MAX_PER_USER', 15),
        'user_idle_minutes' => (int) env('MESSAGING_AUTO_ASSIGN_USER_IDLE_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Widget Público
    |--------------------------------------------------------------------------
    |
    | Configuração do bundle JS embutível em sites de terceiros.
    | public_domain: domínio onde o widget JS é servido.
    | public_protocol: http (dev) ou https (prod).
    |
    */

    'widget' => [
        'public_domain' => env('WIDGET_PUBLIC_DOMAIN', 'widget.lvh.me'),
        'public_protocol' => env('WIDGET_PUBLIC_PROTOCOL', 'https'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker (Redis-backed — research R6)
    |--------------------------------------------------------------------------
    |
    | Implementação própria em App\Domain\Messaging\Infrastructure\CircuitBreaker.
    | threshold: falhas consecutivas para abrir o circuito.
    | window_seconds: janela de observação das falhas.
    | recovery_seconds: tempo em half-open antes de tentar fechar.
    |
    */

    'circuit_breaker' => [
        'threshold' => (int) env('MESSAGING_CB_THRESHOLD', 5),
        'window_seconds' => (int) env('MESSAGING_CB_WINDOW_SECONDS', 60),
        'recovery_seconds' => (int) env('MESSAGING_CB_RECOVERY_SECONDS', 30),
    ],

];
