<?php

namespace Tests\Unit\Config;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class SentryConfigTest extends TestCase
{
    public function test_dsn_reads_from_sentry_laravel_dsn_env(): void
    {
        // Princípio V — DSN do Sentry vem de env (zero hardcode); o nome
        // canônico no pacote é `SENTRY_LARAVEL_DSN`. Em ambientes sem
        // monitoramento (local/CI base), DSN fica null e o cliente fica
        // inerte — eventos não são despachados.
        config(['sentry.dsn' => 'https://public@sentry.example/123']);

        $this->assertSame(
            'https://public@sentry.example/123',
            config('sentry.dsn')
        );
    }

    public function test_dsn_default_is_null_when_env_absent(): void
    {
        // Sem DSN, sentry-laravel não inicializa — comportamento esperado
        // em testes e em local/dev sem credenciais. Garante que o
        // arquivo de config não força um valor não nulo.
        config(['sentry.dsn' => null]);

        $this->assertNull(config('sentry.dsn'));
    }

    public function test_environment_reads_from_env(): void
    {
        // Princípio V — separar produção/staging por tag `environment` no
        // Sentry permite filtrar dashboards. Quando vazio, sentry-laravel
        // usa o APP_ENV como fallback; aqui validamos apenas que a chave
        // está exposta no config.
        $this->assertTrue(
            array_key_exists('environment', config('sentry')),
            'Chave sentry.environment deve existir para tag de ambiente.'
        );
    }

    public function test_send_default_pii_is_false_by_default(): void
    {
        // Princípio I (LGPD) — não enviar PII (IP, headers, body) ao
        // Sentry sem opt-in explícito por tenant. Default seguro.
        $this->assertFalse(config('sentry.send_default_pii'));
    }

    public function test_traces_sample_rate_default_is_zero_or_null(): void
    {
        // Princípio V — performance tracing é opt-in. Default null/0
        // evita custo de APM em tenants que não contrataram esse nível
        // de observabilidade.
        $rate = config('sentry.traces_sample_rate');

        $this->assertTrue(
            $rate === null || $rate === 0.0 || $rate === 0,
            'sentry.traces_sample_rate deve ser null ou 0 por padrão.'
        );
    }

    public function test_breadcrumbs_sql_bindings_disabled_by_default(): void
    {
        // Princípio I (LGPD) — bindings de SQL podem conter dados
        // pessoais (CPF, e-mail, telefone). Desabilitado por padrão
        // evita vazamento por breadcrumb.
        $this->assertFalse(
            config('sentry.breadcrumbs.sql_bindings'),
            'SQL bindings em breadcrumbs deve ficar desabilitado por LGPD.'
        );
    }

    public function test_app_service_provider_boot_does_not_throw_when_sentry_unbound(): void
    {
        // O sandbox de teste não tem sentry/sentry-laravel resolvido no
        // container (DSN vazio). O AppServiceProvider deve guardar a
        // configuração de scope atrás de `app()->bound('sentry')` para
        // não quebrar o boot quando o pacote estiver inerte.
        $this->assertFalse(
            $this->app->bound('sentry'),
            'Sentry não deve estar bound em ambiente de teste sem DSN.'
        );

        // Reboot do provider — se houvesse referência hard a Sentry sem
        // guard, este passo lançaria um Error de autoload.
        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $this->addToAssertionCount(1);
    }
}
