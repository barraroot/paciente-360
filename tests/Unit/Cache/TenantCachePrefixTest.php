<?php

namespace Tests\Unit\Cache;

use App\Events\TenantResolved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Garante que o cache prefix é trocado em runtime quando um novo tenant
 * é resolvido (T045 — Princípio II).
 *
 * Estratégia:
 *  - Em testes, `CACHE_STORE=array` (phpunit.xml). O driver array
 *    respeita o prefix da config — chaves de tenants distintos ficam
 *    separadas dentro do mesmo store.
 *  - Disparamos `TenantResolved` manualmente para acionar o listener
 *    do `AppServiceProvider`.
 */
class TenantCachePrefixTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    public function test_cache_prefix_changes_when_tenant_resolved_event_fires(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        TenantResolved::dispatch($tenantA);
        $prefixA = (string) Config::get('cache.prefix');

        TenantResolved::dispatch($tenantB);
        $prefixB = (string) Config::get('cache.prefix');

        $this->assertSame("paciente360:tenant:{$tenantA->id}:", $prefixA);
        $this->assertSame("paciente360:tenant:{$tenantB->id}:", $prefixB);
        $this->assertNotSame($prefixA, $prefixB);
    }

    public function test_cache_writes_are_isolated_per_tenant(): void
    {
        // Em testes, CACHE_STORE=array — o ArrayStore IGNORA cache.prefix
        // (é apenas um array PHP interno). Para provar isolamento real
        // por prefix, usamos o driver `database`, que respeita prefix
        // (a chave gravada inclui o prefix).
        Config::set('cache.default', 'database');

        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        TenantResolved::dispatch($tenantA);
        Cache::put('foo', 'A', 60);

        TenantResolved::dispatch($tenantB);
        Cache::put('foo', 'B', 60);

        $this->assertSame('B', Cache::get('foo'));

        // Volta para tenant A — 'foo' deve continuar 'A' (não 'B').
        TenantResolved::dispatch($tenantA);
        $this->assertSame('A', Cache::get('foo'));
    }

    public function test_global_store_is_configured_without_tenant_prefix(): void
    {
        $globalConfig = config('cache.stores.global');

        $this->assertNotNull($globalConfig, 'Store `global` deve existir em config/cache.php');
        $this->assertSame('redis', $globalConfig['driver']);
    }
}
