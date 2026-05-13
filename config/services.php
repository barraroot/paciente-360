<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Google Calendar OAuth + Watch channels (Fase 5 — Sincronização US-6.7).
     *
     * client_id/secret: credenciais do OAuth app criado no Google Cloud Console.
     * redirect_uri: callback público (FQDN com HTTPS em prod) — registrado no GCP.
     * webhook_base_url: base URL para watch channels (sub-path /webhooks/google-calendar/{channel_id}).
     * sync_window_days: janela de eventos sincronizados (clarify nº 10 — default 60d rolling).
     * watch_channel_renew_hours: cron renova canais que expiram nas próximas N horas (Google TTL ~7d).
     */
    'google_calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
        'webhook_base_url' => env('GOOGLE_CALENDAR_WEBHOOK_BASE_URL'),
        'sync_window_days' => (int) env('AGENDA_CALENDAR_SYNC_WINDOW_DAYS', 60),
        'watch_channel_renew_hours' => (int) env('AGENDA_WATCH_CHANNEL_RENEW_HOURS', 48),
    ],

];
