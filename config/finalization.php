<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Fase 8 — Finalização do MVP
|--------------------------------------------------------------------------
|
| Configurações globais para os 5 módulos da Fase 8:
|   1. Campanhas       (Épico 9)
|   2. Relatórios      (Épico 10)
|   3. Integrações     (Épico 11)
|   4. Super Admin     (Épico 12)
|   5. Privacidade     (Épico 13)
|
| Defaults conservadores. Valores específicos por tenant ficam em
| `plans.daily_campaign_limit` / `plans.api_rate_limit_per_minute` /
| `plans.webhook_max_endpoints` (introduzidos no Lote B).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Integrações — API Pública + Webhooks (Lote D)
    |--------------------------------------------------------------------------
    */

    // Q18 — OAuth 2.0 Client Credentials opt-in (Passport instalado lazy).
    // Default false; toggle por tenant via `tenant.settings.api.oauth_enabled`.
    'oauth_enabled' => env('FINALIZATION_OAUTH_ENABLED', false),

    // Q16 — Política de retry de webhooks.
    'webhook_max_retries' => env('FINALIZATION_WEBHOOK_MAX_RETRIES', 5),

    // Q16 — Retenção da Dead Letter Queue.
    'webhook_dlq_retention_days' => env('FINALIZATION_WEBHOOK_DLQ_RETENTION_DAYS', 30),

    // Backoff em segundos por tentativa (índice 0 = tentativa 2).
    // Cadência: 30s, 2min, 10min, 1h, 6h.
    'webhook_retry_backoff_seconds' => [30, 120, 600, 3600, 21600],

    // Timeout para HTTP do webhook (segundos).
    'webhook_http_timeout_seconds' => env('FINALIZATION_WEBHOOK_TIMEOUT_SECONDS', 10),

    // Cap defensivo anti-DDoS por IP (todas as APIs públicas).
    'api_public_ip_hard_cap_per_minute' => env('FINALIZATION_API_PUBLIC_IP_CAP', 10000),

    // Q15 — Defaults por plano quando snapshot do plano não tem o valor.
    // Tier mais restritivo (básico) é o fallback seguro.
    'api_rate_limit_default_per_minute' => env('FINALIZATION_API_RATE_LIMIT_DEFAULT', 100),
    'daily_campaign_limit_default' => env('FINALIZATION_DAILY_CAMPAIGN_LIMIT_DEFAULT', 200),
    'webhook_max_endpoints_default' => env('FINALIZATION_WEBHOOK_MAX_ENDPOINTS_DEFAULT', 5),

    /*
    |--------------------------------------------------------------------------
    | Privacidade — LGPD (Lote A)
    |--------------------------------------------------------------------------
    */

    // Q28 — TTL da URL assinada de portabilidade.
    'pdf_signed_url_ttl_days' => env('FINALIZATION_PDF_SIGNED_URL_TTL_DAYS', 7),

    // LGPD Art. 19 — prazo de resposta ao titular.
    'lgpd_response_deadline_business_days' => 15,

    // Q27 — Notificações progressivas de proximidade do prazo (dias úteis).
    'lgpd_notification_warnings' => [
        'inbox_only' => 5,        // D-5: notificação inbox-only.
        'inbox_email_visual' => 2, // D-2: inbox + e-mail + alerta visual persistente.
    ],

    // Q29 — Auditoria semanal de pseudonimização: % de eventos amostrados.
    'pseudonymization_audit_sample_percent' => env('FINALIZATION_AUDIT_SAMPLE_PERCENT', 1),

    // Q29 — Eventos consumidos pela IA (whitelist para gate de CI T012).
    // Atualizar quando a fase IA real registrar consumers; vazio até lá.
    'ai_consumed_events' => [],

    /*
    |--------------------------------------------------------------------------
    | Campanhas (Lote C)
    |--------------------------------------------------------------------------
    */

    // Q6 — Polling do cliente durante dispatch da campanha (segundos).
    'campaign_polling_interval_seconds' => env('FINALIZATION_CAMPAIGN_POLLING_SECONDS', 30),

    // Lookback default em dias para considerar `/sair` recente (não envia campanha).
    'campaign_sair_lookback_hours' => 24,

    // Cache TTL do status Meta de template HSM (minutos).
    'campaign_template_meta_status_cache_minutes' => 30,

    /*
    |--------------------------------------------------------------------------
    | Relatórios (Lote E)
    |--------------------------------------------------------------------------
    */

    // R-8-5 — Threshold para banner "dados desatualizados" no dashboard.
    // 5400s = 90 min de atraso da agregação horária.
    'metric_aggregation_lag_alert_seconds' => env('FINALIZATION_METRIC_LAG_ALERT_SECONDS', 5400),

    // Q9 — Janela acima da qual usar agregações pré-computadas (horas).
    // Janelas ≤ 24h usam queries on-demand.
    'report_aggregation_threshold_hours' => 24,

    // Limite máximo de janela permitida em relatório (mês).
    'report_max_window_months' => 12,

    /*
    |--------------------------------------------------------------------------
    | Super Admin (Lote B)
    |--------------------------------------------------------------------------
    */

    // Q19 — TTL máximo de uma sessão de impersonate (R-8-3).
    'impersonate_session_max_minutes' => env('FINALIZATION_IMPERSONATE_MAX_MINUTES', 120),

    // Q22 — Cooldown entre alertas da mesma categoria de anomalia ao Super Admin.
    'anomaly_alert_cooldown_minutes' => 30,

    // Q22 — Thresholds das 4 categorias monitoradas.
    'anomaly_thresholds' => [
        'conversion_drop_percent' => 20.0,           // queda > 20% WoW
        'ai_usage_spike_multiplier' => 10.0,         // > 10× média histórica do tenant
        'webhook_failure_rate_percent' => 50.0,      // > 50% em 1h com vol ≥ 10
        'payment_overdue_days_critical' => 30,       // inadimplência > 30 dias
    ],

    // Q20 — Política de retenção pós-cancelamento (em dias).
    // Aplicada por `super-admin:apply-retention-policy`.
    'retention_policy' => [
        'config_days' => 30,           // configurações do tenant
        'patient_data_days' => 90,     // dados de paciente (30d undo + 60d grace)
        'audit_logs_days' => 365,      // LGPD Art. 16 (1 ano)
        'controlled_prescriptions_days' => 730,  // Portaria 344/98 (2 anos)
        'billing_records_days' => 1825,         // Lei 12.682/2012 (5 anos)
    ],
];
