<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class TelescopeConfigTest extends TestCase
{
    public function test_enabled_default_is_false(): void
    {
        // Princípio VII (Segurança) — Telescope inerte por padrão. Só liga
        // explicitamente em local/staging via TELESCOPE_ENABLED=true.
        // Quando false, o pacote não registra rotas (`/telescope` → 404).
        $this->assertFalse(
            (bool) config('telescope.enabled'),
            'Telescope deve estar desabilitado por padrão.'
        );
    }

    public function test_path_is_telescope_by_default(): void
    {
        // Critério de aceitação T013: GET `/telescope` em produção → 404.
        // O 404 vem do `enabled=false` (rotas não registradas), mas o path
        // canônico continua sendo `telescope` para quando ligado em dev.
        $this->assertSame('telescope', config('telescope.path'));
    }

    public function test_driver_is_database_by_default(): void
    {
        // Driver `database` cobre dev/staging sem dependência extra. Em
        // testes podemos sobrescrever para `null` (no-op) caso queiramos
        // medir overhead do recorder.
        $this->assertSame('database', config('telescope.driver'));
    }

    public function test_storage_database_connection_is_configurable(): void
    {
        // Telescope usa a conexão default por padrão; deixar configurável
        // permite isolar gravações em uma conexão dedicada (debug DB)
        // sem poluir o schema do tenant.
        $this->assertArrayHasKey('database', config('telescope.storage'));
    }

    public function test_master_switch_blocks_routes_in_production(): void
    {
        // Reforço explícito: independente da env TELESCOPE_ENABLED, o
        // arquivo de config não pode hardcodar `enabled=true`. O default
        // do env() precisa ser false para que produção fique segura por
        // omissão (defesa em profundidade — Princípio VII).
        $configPath = config_path('telescope.php');
        $this->assertFileExists($configPath);

        $contents = file_get_contents($configPath);
        $this->assertMatchesRegularExpression(
            "/env\\(\\s*'TELESCOPE_ENABLED'\\s*,\\s*false\\s*\\)/",
            $contents,
            'config/telescope.php deve ter TELESCOPE_ENABLED com default false.'
        );
    }

    public function test_telescope_provider_class_file_exists(): void
    {
        // O App\Providers\TelescopeServiceProvider precisa existir como
        // arquivo (mesmo sem o pacote vendor resolvido) para que, quando
        // `laravel/telescope` for instalado, o gate de Super Admin e o
        // guard de ambiente já estejam no lugar.
        $providerPath = app_path('Providers/TelescopeServiceProvider.php');

        $this->assertFileExists(
            $providerPath,
            'TelescopeServiceProvider deve existir em app/Providers.'
        );
    }

    public function test_telescope_provider_extends_package_base(): void
    {
        // O provider deve estender Laravel\Telescope\TelescopeApplicationServiceProvider.
        // Sem extensão, o `gate()` e `hideSensitiveRequestDetails()` não
        // são chamados pelo pacote, e o painel ficaria aberto.
        $contents = file_get_contents(app_path('Providers/TelescopeServiceProvider.php'));

        $this->assertStringContainsString(
            'Laravel\\Telescope\\TelescopeApplicationServiceProvider',
            $contents,
            'Provider deve herdar de TelescopeApplicationServiceProvider.'
        );
    }

    public function test_telescope_provider_blocks_non_dev_environments(): void
    {
        // Princípio VII — production NUNCA deve ter Telescope ligado. O
        // provider precisa ter um early-return baseado em
        // `app()->environment(['local', 'staging'])` no register().
        $contents = file_get_contents(app_path('Providers/TelescopeServiceProvider.php'));

        $this->assertStringContainsString(
            "environment(['local', 'staging'])",
            $contents,
            'Provider deve restringir registro a local/staging.'
        );
    }

    public function test_telescope_provider_gate_restricts_to_super_admin(): void
    {
        // Acesso ao painel exige role `super_admin` (Spatie). Mesmo em
        // staging, usuário comum recebe 403. Em local sem auth, gate
        // libera quando APP_ENV=local (decisão R8 do research).
        $contents = file_get_contents(app_path('Providers/TelescopeServiceProvider.php'));

        $this->assertStringContainsString(
            'viewTelescope',
            $contents,
            'Gate viewTelescope deve estar definido.'
        );

        $this->assertStringContainsString(
            'super_admin',
            $contents,
            'Gate viewTelescope deve checar role super_admin.'
        );
    }

    public function test_telescope_provider_is_registered_only_when_package_present(): void
    {
        // bootstrap/providers.php registra o TelescopeServiceProvider
        // condicionalmente via class_exists(), evitando autoload error
        // quando o vendor não está instalado (CI base, sandbox).
        $contents = file_get_contents(base_path('bootstrap/providers.php'));

        $this->assertStringContainsString(
            'class_exists',
            $contents,
            'bootstrap/providers.php deve registrar Telescope com guard class_exists.'
        );

        $this->assertStringContainsString(
            'TelescopeApplicationServiceProvider',
            $contents,
            'bootstrap/providers.php deve referenciar a base do Telescope no guard.'
        );
    }
}
