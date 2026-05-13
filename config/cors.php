<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    | Fase 4 (Token Auth Migration): supports_credentials = false porque a
    | API tenant é agora stateless (Bearer token). Filament usa guard 'web'
    | com session cookie em domínio separado — não passa por estas rotas CORS.
    |
    | CORS_ALLOWED_ORIGINS: lista separada por vírgula de origens permitidas.
    | Em dev: localhost:5173 (Vite HMR) + localhost:3000 (alternativo).
    | Em prod: app.crm.com.br (SPA CDN) — injetar via env de ambiente.
    |
    */

    'paths' => ['api/*', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:3000')),

    'allowed_origins_patterns' => [
        '#^https?://.*\.lvh\.me(:\d+)?$#',
        '#^https?://lvh\.me(:\d+)?$#',
        '#^https?://.*\.crm\.com\.br$#',
    ],

    'allowed_headers' => ['*'],

    // X-Request-Id: correlação de request entre SPA e API.
    // Authorization: necessário para CORS preflight com Bearer (Fase 4).
    'exposed_headers' => ['X-Request-Id', 'Authorization'],

    // 3600 segundos = 1 hora de cache do preflight OPTIONS.
    'max_age' => 3600,

    // false: API tenant é stateless (Bearer token). Cookie de sessão
    // é exclusivo do Filament (guard 'web', domínio separado).
    'supports_credentials' => false,

];
