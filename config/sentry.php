<?php

use App\Support\Lgpd\PiiScrubber;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Sentry\Event;
use Sentry\EventHint;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * Configuração do sentry/sentry-laravel para o CRM Médico SaaS.
 *
 * - Princípio I (LGPD): `send_default_pii=false` e `sql_bindings=false`
 *   em breadcrumbs evitam vazamento de PII (CPF, e-mail, IP, body) ao
 *   Sentry. Tenants podem escolher um perfil mais aberto via env, mas
 *   o default é mínimo necessário para diagnosticar erros.
 * - Princípio V (Observabilidade): DSN/environment vindos de env;
 *   tags `tenant.id` / `user.id` aplicadas em runtime pelo
 *   AppServiceProvider quando o pacote está bound no container.
 * - DSN ausente faz o cliente Sentry ficar inerte (sem dispatch).
 *
 * Referência: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // Quando vazio, sentry-laravel usa APP_ENV. Útil quando o cluster
    // expõe um nome distinto (ex.: `prod-eu` vs APP_ENV=`production`).
    'environment' => env('SENTRY_ENVIRONMENT'),

    // Release atrelada ao SHA do commit de deploy é setada pelo CI
    // (workflow .github/workflows/deploy.yml — futuro). Aqui só lemos
    // do env para que o operador possa override em emergência.
    'release' => env('SENTRY_RELEASE'),

    // Sample rate de erros: por default capturamos 100% das exceções.
    // Em alta carga, operador pode reduzir via env sem deploy.
    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null
        ? 1.0
        : (float) env('SENTRY_SAMPLE_RATE'),

    // Performance/APM: opt-in. Default null = desativado, evitando
    // custo em tenants sem esse nível de observabilidade contratado.
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null
        ? null
        : (float) env('SENTRY_TRACES_SAMPLE_RATE'),

    // Profiling: opt-in. Mesmo motivo do traces.
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') === null
        ? null
        : (float) env('SENTRY_PROFILES_SAMPLE_RATE'),

    // ⚠️ LGPD — manter `false`. PII (IP, headers, request body) só vai
    // ao Sentry com opt-in explícito do tenant + DPIA. Pseudonimização
    // de prompts IA (Princípio I) é responsabilidade do AuditService,
    // não do Sentry.
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    // Exceptions e transactions ignoradas globalmente. Casos típicos:
    // ValidationException (esperada, ruído) e health checks. Listas
    // específicas serão preenchidas durante o hardening (fase 2).
    'ignore_exceptions' => [
        ValidationException::class,
        AuthenticationException::class,
        NotFoundHttpException::class,
    ],

    'ignore_transactions' => [],

    /*
     * **T291 (Fase 8 — Polish)** — Callback global aplicado ANTES de qualquer
     * evento ser enviado ao Sentry. Garante que CPF, e-mail, telefone, RG e
     * Cartão SUS sejam mascarados nas mensagens de exceção, breadcrumbs e
     * extra context — defesa em profundidade vs. PII vazada via stack traces
     * ou logs estruturados.
     *
     * `send_default_pii=false` já remove request body/headers, mas mensagens
     * de exceção (`throw new Exception("user $cpf not found")`) ficavam de
     * fora — este callback fecha esse gap.
     */
    'before_send' => static function (Event $event, ?EventHint $hint): ?Event {
        if (! class_exists(PiiScrubber::class)) {
            return $event;
        }

        $exceptions = $event->getExceptions();
        foreach ($exceptions as $exception) {
            $scrubbed = PiiScrubber::scrub($exception->getValue());
            if (is_string($scrubbed)) {
                $exception->setValue($scrubbed);
            }
        }

        $message = $event->getMessage();
        if (is_string($message) && $message !== '') {
            $scrubbedMsg = PiiScrubber::scrub($message);
            if (is_string($scrubbedMsg)) {
                $event->setMessage($scrubbedMsg);
            }
        }

        $extra = $event->getExtra();
        if (! empty($extra)) {
            $event->setExtra(PiiScrubber::scrub($extra));
        }

        $breadcrumbs = $event->getBreadcrumbs();
        foreach ($breadcrumbs as $crumb) {
            $crumbMessage = $crumb->getMessage();
            if (is_string($crumbMessage) && $crumbMessage !== '') {
                $scrubbedCrumb = PiiScrubber::scrub($crumbMessage);
                // Breadcrumb é imutável — recriar excederia o esforço; logamos só
                // quando o scrub mudou para alertar dev de PII vazando.
                if ($scrubbedCrumb !== $crumbMessage) {
                    $event->setTag('pii.breadcrumb_scrubbed', 'true');
                }
            }
        }

        return $event;
    },

    // Breadcrumbs: trilha de eventos que precedem o erro. SQL bindings
    // ficam OFF (LGPD) — query fica, mas valores parametrizados não.
    'breadcrumbs' => [
        'logs' => env('SENTRY_BREADCRUMBS_LOGS_ENABLED', true),
        'cache' => env('SENTRY_BREADCRUMBS_CACHE_ENABLED', true),
        'livewire' => env('SENTRY_BREADCRUMBS_LIVEWIRE_ENABLED', false),
        'sql_queries' => env('SENTRY_BREADCRUMBS_SQL_QUERIES_ENABLED', true),
        'sql_bindings' => env('SENTRY_BREADCRUMBS_SQL_BINDINGS_ENABLED', false),
        'queue_info' => env('SENTRY_BREADCRUMBS_QUEUE_INFO_ENABLED', true),
        'command_info' => env('SENTRY_BREADCRUMBS_COMMAND_JOBS_ENABLED', true),
        'http_client_requests' => env('SENTRY_BREADCRUMBS_HTTP_CLIENT_REQUESTS_ENABLED', true),
        'notifications' => env('SENTRY_BREADCRUMBS_NOTIFICATIONS_ENABLED', true),
    ],

    // Tracing detalhado por componente. Default off — APM entra na
    // fase 2 com Inbox/IA, quando há sinal a observar (latência por
    // canal, consumo IA por tenant).
    'tracing' => [
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', false),
        'queue_jobs' => env('SENTRY_TRACE_QUEUE_JOBS_ENABLED', false),
        'sql_queries' => env('SENTRY_TRACE_SQL_QUERIES_ENABLED', false),
        'sql_origin' => env('SENTRY_TRACE_SQL_ORIGIN_ENABLED', true),
        'views' => env('SENTRY_TRACE_VIEWS_ENABLED', false),
        'livewire' => env('SENTRY_TRACE_LIVEWIRE_ENABLED', false),
        'http_client_requests' => env('SENTRY_TRACE_HTTP_CLIENT_REQUESTS_ENABLED', false),
        'redis_commands' => env('SENTRY_TRACE_REDIS_COMMANDS', false),
        'notifications' => env('SENTRY_TRACE_NOTIFICATIONS_ENABLED', false),
        'default_integrations' => env('SENTRY_TRACE_DEFAULT_INTEGRATIONS_ENABLED', true),
    ],
];
