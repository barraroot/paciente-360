<?php

use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Watchers;

/*
 * Configuração do laravel/telescope para o CRM Médico SaaS.
 *
 * - Princípio VII (Segurança): `enabled=false` por default. Só ligamos
 *   explicitamente em local/staging via TELESCOPE_ENABLED=true. Em
 *   produção a env precisa permanecer ausente/false — o pacote não
 *   registra rotas e GET /telescope retorna 404.
 * - Princípio I (LGPD): MailWatcher, RequestWatcher (com headers/body) e
 *   QueryWatcher capturam dados sensíveis. Os defaults aqui omitem body
 *   de request e parâmetros de query; o operador pode habilitar caso a
 *   caso em staging quando precisar de diagnóstico.
 * - Princípio V (Observabilidade): mesmo desabilitado em produção, o
 *   Telescope continua sendo a ferramenta primária de inspeção em dev
 *   (queries, jobs, mails, cache, exceptions, etc.).
 *
 * Referência: https://laravel.com/docs/13.x/telescope#configuration
 */

return [
    'domain' => env('TELESCOPE_DOMAIN'),

    'path' => env('TELESCOPE_PATH', 'telescope'),

    'driver' => env('TELESCOPE_DRIVER', 'database'),

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'pgsql'),
            'chunk' => 1000,
        ],
    ],

    /*
     * Gate de admissão. Em produção, o `enabled` abaixo bloqueia primeiro
     * (rotas nem registradas). Em local/staging, o middleware Authorize
     * delega ao gate `viewTelescope` definido no
     * App\Providers\TelescopeServiceProvider — que exige role super_admin.
     */
    'enabled' => env('TELESCOPE_ENABLED', false),

    'middleware' => [
        'web',
        Authorize::class,
    ],

    /*
     * Esconde tags listadas (cookies, headers de auth, payloads de webhook
     * com assinatura HMAC). Defaults seguros para LGPD; operador pode
     * adicionar mais via env futuro.
     */
    'only_paths' => [
        // Vazio = monitora tudo dentro do `path`. Listar caminhos
        // específicos restringe ainda mais (útil em staging com
        // muito tráfego).
    ],

    'ignore_paths' => [
        'livewire*',
        'nova-api*',
        'pulse*',
        'horizon*',
        // Health checks e webhooks externos não devem inflar o histórico.
        'up',
        'stripe/webhook',
    ],

    'ignore_commands' => [],

    'watchers' => [
        Watchers\BatchWatcher::class => env('TELESCOPE_BATCH_WATCHER', true),

        Watchers\CacheWatcher::class => [
            'enabled' => env('TELESCOPE_CACHE_WATCHER', true),
            'hidden' => [],
        ],

        Watchers\ClientRequestWatcher::class => env('TELESCOPE_CLIENT_REQUEST_WATCHER', true),

        Watchers\CommandWatcher::class => [
            'enabled' => env('TELESCOPE_COMMAND_WATCHER', true),
            'ignore' => [],
        ],

        Watchers\DumpWatcher::class => [
            'enabled' => env('TELESCOPE_DUMP_WATCHER', true),
            'always' => env('TELESCOPE_DUMP_WATCHER_ALWAYS', false),
        ],

        Watchers\EventWatcher::class => [
            'enabled' => env('TELESCOPE_EVENT_WATCHER', true),
            'ignore' => [],
        ],

        Watchers\ExceptionWatcher::class => env('TELESCOPE_EXCEPTION_WATCHER', true),

        Watchers\GateWatcher::class => [
            'enabled' => env('TELESCOPE_GATE_WATCHER', true),
            'ignore_abilities' => [],
            'ignore_packages' => true,
            'ignore_paths' => [],
        ],

        Watchers\JobWatcher::class => env('TELESCOPE_JOB_WATCHER', true),

        Watchers\LogWatcher::class => [
            'enabled' => env('TELESCOPE_LOG_WATCHER', true),
            'level' => 'error',
        ],

        // ⚠️ LGPD — MailWatcher captura corpo de e-mail (pode conter
        // dados de paciente). Default OFF; ligar pontualmente em staging
        // apenas quando rastreando bug de notificação.
        Watchers\MailWatcher::class => env('TELESCOPE_MAIL_WATCHER', false),

        Watchers\ModelWatcher::class => [
            'enabled' => env('TELESCOPE_MODEL_WATCHER', true),
            'events' => ['eloquent.*'],
            'hydrations' => true,
        ],

        Watchers\NotificationWatcher::class => env('TELESCOPE_NOTIFICATION_WATCHER', true),

        // ⚠️ LGPD — QueryWatcher pode capturar valores parametrizados
        // (CPF, e-mail, telefone). Mantemos ON em dev/staging com `slow`
        // alto (500ms) para limitar volume; bindings só com opt-in.
        Watchers\QueryWatcher::class => [
            'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
            'ignore_packages' => true,
            'ignore_paths' => [],
            'slow' => 500,
        ],

        Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', true),

        // ⚠️ LGPD — RequestWatcher captura headers e body. Mantemos ON em
        // dev/staging mas o `Authorize` middleware barra produção e o
        // gate só libera Super Admin.
        Watchers\RequestWatcher::class => [
            'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
            'size_limit' => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 64),
            'ignore_http_methods' => [],
            'ignore_status_codes' => [],
        ],

        Watchers\ScheduleWatcher::class => env('TELESCOPE_SCHEDULE_WATCHER', true),
        Watchers\ViewWatcher::class => env('TELESCOPE_VIEW_WATCHER', true),
    ],
];
