<?php

/**
 * T095/T096 — Configuração de Content Security Policy (Fase 4 Lote J).
 *
 * Centraliza os hosts permitidos em `connect-src` da CSP estrita de produção.
 * Em local/test/staging a CSP é permissiva (Vite HMR) — esta config é ignorada.
 *
 * Auditoria do bundle produzido por `npm run build` (T095):
 *  - Zero `eval()` ou `new Function(...)`
 *  - Zero scripts inline em Blade views
 *  - `innerHTML` usado apenas em código Vue runtime / vuedraggable (framework controlado)
 *
 * @see app/Http/Middleware/SetSecurityHeaders.php
 */
return [

    /*
     * Host do Reverb WebSocket. Default: prod alvo do Paciente360.
     * Override por ambiente via env CSP_REVERB_HOST (ex.:
     * "wss://reverb-staging.crm.com.br").
     */
    'reverb_host' => env('CSP_REVERB_HOST', 'wss://reverb.crm.com.br'),

    /*
     * Host das mídias (S3 presigned URLs). Default cobre AWS S3 padrão;
     * para CloudFront/CDN próprio, override via env CSP_MEDIA_HOST.
     */
    'media_host' => env('CSP_MEDIA_HOST', 'https://*.amazonaws.com'),

    /*
     * Host da API (deploy decoupled — SPA em app.crm.com.br, API em api.crm.com.br).
     * Necessário em connect-src porque a SPA faz fetch cross-origin para a API.
     */
    'api_host' => env('CSP_API_HOST', 'https://api.crm.com.br'),

    /*
     * Hosts Google (Fase 5 — Sincronização Google Calendar US-6.7).
     *
     * connect-src precisa cobrir:
     *  - https://accounts.google.com — fluxo OAuth (authorize + token endpoint)
     *  - https://oauth2.googleapis.com — token refresh
     *  - https://www.googleapis.com — Google Calendar API v3 (events.insert/list/watch, calendars.insert)
     *
     * Override por ambiente via env CSP_GOOGLE_HOSTS (string com hosts separados por espaço).
     * Default cobre os 3 endpoints documentados em research.md R1+R3.
     */
    'google_hosts' => array_values(array_filter(explode(' ', env(
        'CSP_GOOGLE_HOSTS',
        'https://accounts.google.com https://oauth2.googleapis.com https://www.googleapis.com'
    )))),

];
