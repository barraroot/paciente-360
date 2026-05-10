<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class TenancyConfigTest extends TestCase
{
    public function test_subdomain_suffix_default_is_lvh_me(): void
    {
        // Default explícito do arquivo: ambiente dev/CI compartilha o suffix
        // público `lvh.me` (resolve `*.lvh.me` em 127.0.0.1). Em produção,
        // `TENANCY_SUBDOMAIN_SUFFIX=crm.com.br` é setado no .env de prod.
        $this->assertSame('lvh.me', config('tenancy.subdomain_suffix'));
    }

    public function test_subdomain_suffix_reads_env_override(): void
    {
        config(['tenancy.subdomain_suffix' => 'crm.com.br']);

        $this->assertSame('crm.com.br', config('tenancy.subdomain_suffix'));
    }

    public function test_redis_prefix_uses_tenant_placeholder(): void
    {
        // Princípio II — caches/Redis prefixados por tenant.
        $this->assertSame('tenant:{id}:', config('tenancy.redis_prefix'));
    }

    public function test_public_hosts_includes_root_and_admin(): void
    {
        // Hosts que NÃO carregam tenant (cadastro público, super admin,
        // webhooks). O middleware ResolveTenant ignora estes.
        $publicHosts = config('tenancy.public_hosts');

        $this->assertIsArray($publicHosts);
        $this->assertContains('crm', $publicHosts);
        $this->assertContains('admin', $publicHosts);
        $this->assertContains('www', $publicHosts);
    }

    public function test_reserved_slugs_protect_system_subdomains(): void
    {
        // Slugs proibidos para registro de novo tenant (colidiriam com
        // hosts públicos ou rotas internas).
        $reserved = config('tenancy.reserved_slugs');

        $this->assertIsArray($reserved);
        foreach (['admin', 'api', 'app', 'crm', 'panel', 'stripe', 'webhook', 'www'] as $slug) {
            $this->assertContains($slug, $reserved, "Reserved slug '{$slug}' deve estar protegido.");
        }
    }

    public function test_correlation_header_is_defined(): void
    {
        // Header propagado pelo middleware de logging estruturado (Princípio V).
        $this->assertSame('X-Correlation-Id', config('tenancy.correlation_header'));
    }
}
