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

];
