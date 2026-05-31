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

        /*
         | Evolution API v2 — WhatsApp NÃO oficial (Baileys), feature 014.
         | Servidor AUTO-HOSPEDADO pela plataforma (nunca input do tenant).
         | api_url / api_key são globais; cada canal de tenant vira uma instância.
         | webhook_secret valida os webhooks recebidos do Evolution (apikey header).
         */
        'evolution' => [
            'api_url' => env('EVOLUTION_API_URL'),
            'api_key' => env('EVOLUTION_API_KEY'),
            'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET'),
            'http_timeout_seconds' => (int) env('EVOLUTION_HTTP_TIMEOUT_SECONDS', 15),
            // Base pública que o servidor Evolution usa para entregar webhooks à app.
            // Em Docker local, aponte para o host interno (ex.: http://laravel.test);
            // em produção, a URL pública HTTPS. Se vazio, usa a rota nomeada (APP_URL).
            'webhook_base_url' => env('EVOLUTION_WEBHOOK_BASE_URL'),
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

    /*
    |--------------------------------------------------------------------------
    | Áudio multimodal (feature 018, US4 + US5)
    |--------------------------------------------------------------------------
    |
    | STT inbound (WhatsApp + Instagram Direct; widget fica fora — FR-026).
    | TTS outbound sob gatilho explícito do paciente (Q3=A, FR-031/033).
    | Provedores plugáveis via interfaces App\Domain\Messaging\Audio.
    | Consentimento LGPD: STT/TTS reusam ConsentFinalidade::Comunicacao
    | (Q-clarify-2=B, FR-055); retenção do áudio bruto além do prazo padrão
    | exige nova ConsentFinalidade::Transcricao (FR-055a).
    |
    */

    'audio' => [
        'stt' => [
            'provider' => env('MESSAGING_STT_PROVIDER', 'openai_whisper'),
            'openai_api_key' => env('MESSAGING_STT_OPENAI_API_KEY'),
            'timeout_s' => (int) env('MESSAGING_STT_TIMEOUT_S', 15),
            'max_duration_s' => (int) env('MESSAGING_STT_MAX_DURATION_S', 120),
            'default_language' => env('MESSAGING_STT_DEFAULT_LANGUAGE', 'pt'),
        ],

        'tts' => [
            'provider' => env('MESSAGING_TTS_PROVIDER', 'elevenlabs'),
            'elevenlabs_api_key' => env('MESSAGING_TTS_ELEVENLABS_API_KEY'),
            'elevenlabs_model' => env('MESSAGING_TTS_ELEVENLABS_MODEL', 'eleven_turbo_v2_5'),
            'max_text_length' => (int) env('MESSAGING_TTS_MAX_TEXT_LENGTH', 2000),
            'enabled' => (bool) env('MESSAGING_TTS_ENABLED', true),
            'format' => env('MESSAGING_TTS_FORMAT', 'mp3'),
        ],

        // Gatilhos explícitos de preferência por áudio (Q3=A, R11). Match por
        // substring após normalização (lowercase + sem acento). Adicione mais
        // se identificar padrões no campo.
        'outbound' => [
            'triggers' => [
                'nao sei ler', 'nao consigo ler',
                'manda audio', 'me responda em audio', 'me responda por audio',
                'responde por audio', 'manda por audio', 'envia audio',
                'to dirigindo', 'estou dirigindo', 'to andando', 'estou andando',
                'so posso ouvir', 'so consigo ouvir',
            ],
        ],

        // Retenção LGPD-aware do áudio bruto (Feature 018 Polish T210/T212,
        // FR-055a/b/c). `default_days` = janela em que o WAV/OGG fica no
        // storage SEM precisar de consent extra. Após isso, o PurgeJob apaga
        // SE paciente NÃO tem `ConsentFinalidade::Transcricao` ativo. A
        // transcrição em texto permanece indefinidamente (sem voz biométrica).
        // `extended_days` é o teto MAX com consent — informativo na UI; o
        // schedule de purge é responsabilidade do operador via política
        // de tenant futura.
        'retention' => [
            'default_days' => (int) env('MESSAGING_AUDIO_RETENTION_DEFAULT_DAYS', 90),
            'extended_days' => (int) env('MESSAGING_AUDIO_RETENTION_EXTENDED_DAYS', 365),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limit anti-abuso (feature 018, US1 — FR-008a..d, Q-clarify-5=C)
    |--------------------------------------------------------------------------
    |
    | 2 camadas reusando RateLimiter Laravel (registrado em RouteServiceProvider).
    | Excedido → cooldown auditável (CooldownService). NÃO é caminho de MVP —
    | implementado no Polish (T200-T207). Gate de produção: ativo antes de
    | promover MVP a tenants pagantes.
    |
    */

    'rate' => [
        'per_conversation' => (int) env('MESSAGING_RATE_PER_CONVERSATION', 30),
        'per_identifier' => (int) env('MESSAGING_RATE_PER_IDENTIFIER', 100),
        'window_minutes' => (int) env('MESSAGING_RATE_WINDOW_MINUTES', 10),
    ],

    'cooldown' => [
        'minutes' => (int) env('MESSAGING_COOLDOWN_MINUTES', 15),
    ],

];
