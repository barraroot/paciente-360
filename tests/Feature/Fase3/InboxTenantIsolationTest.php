<?php

namespace Tests\Feature\Fase3;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * T058 — Skeleton de isolamento de tenant para endpoints da Fase 3 (Inbox Omnichannel).
 *
 * Cada US adicionará entradas no `provideAuthenticatedEndpoints()` conforme implementada:
 *  - T089: US1 — channels CRUD + templates sync
 *  - T134: US4 — conversations + messages
 *  - T161: US5 — assignment
 *  - T182: US6 — takeover
 *  - T199: US2 — Instagram webhook + connect
 *  - T227: US3 — widget config admin
 *  - T245: US7 — quick-replies
 *
 * Princípio II — isolamento multi-tenant NON-NEGOTIABLE.
 */
class InboxTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Provider de endpoints autenticados desta fase.
     * Cada US (4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7) adicionará entradas conforme implementadas.
     *
     * @return array<string, array{method:string, uri:string, ability:string, payload?:array<string,mixed>}>
     */
    public static function provideAuthenticatedEndpoints(): array
    {
        return [
            // T089 adicionará endpoints US1 (channels CRUD + templates sync)
            // T134 adicionará endpoints US4 (conversations + messages)
            // T161 adicionará endpoints US5 (assignment)
            // T182 adicionará endpoints US6 (takeover)
            // T199 adicionará endpoints US2 (Instagram webhook + connect)
            // T227 adicionará endpoints US3 (widget config admin)
            // T245 adicionará endpoints US7 (quick-replies)
            'stub_placeholder' => [['method' => 'GET', 'uri' => '/_stub', 'ability' => 'stub']],
        ];
    }

    /**
     * @param array{method:string, uri:string, ability:string, payload?:array<string,mixed>} $endpoint
     */
    #[DataProvider('provideAuthenticatedEndpoints')]
    #[Test]
    public function test_endpoint_isolates_tenants(array $endpoint): void
    {
        // Stub — assertion real: user de tenant A não acessa recurso de tenant B (404/403).
        // Cada US adicionará verificações concretas conforme endpoints são implementados.
        $this->markTestSkipped('Endpoints serão adicionados conforme cada US é implementada (T089, T134, T161, T182, T199, T227, T245).');
    }
}
