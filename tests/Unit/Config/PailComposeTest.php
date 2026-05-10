<?php

namespace Tests\Unit\Config;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class PailComposeTest extends TestCase
{
    /**
     * @return array{0: array<string, mixed>}
     */
    private function loadCompose(): array
    {
        $path = base_path('compose.yaml');
        $this->assertFileExists($path, 'compose.yaml deve existir na raiz do projeto.');

        /** @var array<string, mixed> $parsed */
        $parsed = Yaml::parseFile($path);
        $this->assertIsArray($parsed);
        $this->assertArrayHasKey('services', $parsed);

        return [$parsed];
    }

    public function test_pail_service_is_declared(): void
    {
        // Princípio V (Observabilidade) — Pail precisa estar disponível como
        // service Compose para streaming de logs sob demanda, sem exigir
        // terminal extra ou processo gerenciado manualmente.
        [$compose] = $this->loadCompose();

        $this->assertArrayHasKey(
            'pail',
            $compose['services'],
            'compose.yaml deve declarar um service "pail" para streaming de logs.'
        );
    }

    public function test_pail_service_reuses_sail_image(): void
    {
        // Reusar a imagem sail-8.5/app evita rebuild duplicado e garante
        // que Pail enxergue o mesmo PHP/extensões da app.
        [$compose] = $this->loadCompose();

        $this->assertSame(
            'sail-8.5/app',
            $compose['services']['pail']['image'] ?? null,
            'Service pail deve reusar a imagem sail-8.5/app.'
        );
    }

    public function test_pail_service_runs_artisan_pail(): void
    {
        // Aceitação T017: `pail` produz logs em tempo real. O command do
        // service precisa invocar `php artisan pail` (com flags opcionais).
        [$compose] = $this->loadCompose();

        $command = $compose['services']['pail']['command'] ?? null;
        $this->assertIsArray($command, 'command de pail deve ser uma lista.');
        $this->assertSame('php', $command[0] ?? null);
        $this->assertSame('artisan', $command[1] ?? null);
        $this->assertSame('pail', $command[2] ?? null);
    }

    public function test_pail_service_uses_optional_profile(): void
    {
        // Pail é opcional para `sail up`; cargar sempre forçaria um worker
        // extra desnecessário em quem não quer log streaming. Usar Compose
        // `profiles: [pail]` mantém o service inerte por padrão e exige
        // ativação explícita via `COMPOSE_PROFILES=pail` ou `--profile pail`.
        [$compose] = $this->loadCompose();

        $profiles = $compose['services']['pail']['profiles'] ?? null;
        $this->assertIsArray($profiles, 'pail deve declarar profiles.');
        $this->assertContains(
            'pail',
            $profiles,
            'pail deve usar profile "pail" para ativação opcional.'
        );
    }

    public function test_pail_service_mounts_project_volume(): void
    {
        // Sem o volume montado, Pail não enxerga `storage/logs/laravel.log`.
        [$compose] = $this->loadCompose();

        $volumes = $compose['services']['pail']['volumes'] ?? [];
        $this->assertContains(
            '.:/var/www/html',
            $volumes,
            'pail deve montar a raiz do projeto em /var/www/html.'
        );
    }

    public function test_pail_service_has_tty_for_streaming(): void
    {
        // Pail usa cursor control (limpa terminal, cores). Sem `tty: true`
        // o output sai cortado; sem `stdin_open: true` não dá para mandar
        // sinais (ctrl+c) limpos via `docker compose attach`.
        [$compose] = $this->loadCompose();

        $this->assertTrue(
            $compose['services']['pail']['tty'] ?? false,
            'pail deve declarar tty: true.'
        );
        $this->assertTrue(
            $compose['services']['pail']['stdin_open'] ?? false,
            'pail deve declarar stdin_open: true.'
        );
    }

    public function test_pail_service_depends_on_app(): void
    {
        // Pail só faz sentido com o container principal de pé (compartilha
        // o volume e os logs gerados por requests). depends_on garante a
        // ordem mesmo com profile ativado isoladamente.
        [$compose] = $this->loadCompose();

        $depends = $compose['services']['pail']['depends_on'] ?? [];
        $this->assertArrayHasKey(
            'laravel.test',
            $depends,
            'pail deve depender de laravel.test (container principal).'
        );
    }

    public function test_quickstart_documents_pail_command(): void
    {
        // O quickstart é a porta de entrada para novos devs; Pail precisa
        // estar referenciado como ferramenta de tail de logs.
        $quickstart = base_path('specs/001-fundacao-multitenant/quickstart.md');
        $this->assertFileExists($quickstart);

        $contents = file_get_contents($quickstart);
        $this->assertStringContainsString(
            'sail artisan pail',
            $contents,
            'quickstart.md deve documentar o comando sail artisan pail.'
        );
    }
}
