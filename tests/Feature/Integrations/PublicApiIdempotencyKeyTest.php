<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T239 (Fase 8 — Lote D US-11.2 + NFR-9)** — Idempotency-Key dedup 24h.
 *
 * Mesmo header em 2 POSTs distintos retorna mesmo response sem criar
 * recurso duplicado.
 */
class PublicApiIdempotencyKeyTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_same_idempotency_key_returns_replay(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('clinic-idemp');
        $user = $this->userForRole($tenant, 'admin-clinica');
        Sanctum::actingAs($user, ['*']);

        $payload = ['nome' => 'Maria Teste', 'telefone' => '11999990000'];

        $key = (string) Str::uuid();

        $first = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/public/v1/patients', $payload)
            ->assertStatus(201);

        $second = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/public/v1/patients', $payload)
            ->assertStatus(201)
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertDatabaseCount('pacientes', 1);
    }
}
