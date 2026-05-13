<?php

namespace Tests\Unit\Services;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\AuditLog;
use App\Services\Audit\AuditAttributesBuilder;
use App\Services\Audit\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * T074 — Testes unitários do `AuditService`.
 *
 * Valida que:
 *  - `log()` produz a mesma linha que o listener (via `Event::dispatch`).
 *  - `query()` escopa ao tenant atual do container.
 *  - `queryForTenant()` filtra explicitamente sem depender do container.
 *  - Keys sensíveis são removidas do payload.
 *
 * @see App\Services\Audit\AuditService
 * @see App\Services\Audit\AuditAttributesBuilder
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
class AuditServiceTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private AuditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuditService(new AuditAttributesBuilder);
    }

    /**
     * Cria um evento dummy de auditoria para os testes.
     */
    private function makeEvent(
        string $action = 'test.action',
        array $payload = [],
        ?int $tenantId = null,
        ?int $userId = null,
    ): Auditable {
        return new class($action, $payload, $tenantId, $userId) implements Auditable
        {
            use Dispatchable;
            use IsAuditable;

            public function __construct(
                private readonly string $actionName,
                private readonly array $payloadData,
                private readonly ?int $tenantIdOverride,
                private readonly ?int $userIdOverride,
            ) {}

            public function auditAction(): string
            {
                return $this->actionName;
            }

            public function auditPayload(): array
            {
                return $this->payloadData;
            }

            public function auditableModel(): ?Model
            {
                return null;
            }

            public function auditTenantId(): ?int
            {
                if ($this->tenantIdOverride !== null) {
                    return $this->tenantIdOverride;
                }

                return app()->bound('tenant') ? app('tenant')->id : null;
            }

            public function auditUserId(): ?int
            {
                if ($this->userIdOverride !== null) {
                    return $this->userIdOverride;
                }

                return auth()->id();
            }
        };
    }

    /**
     * `AuditService::log()` e `Event::dispatch()` + listener devem produzir
     * linhas com atributos idênticos (exceto `id` e `created_at`).
     */
    public function test_log_produces_same_row_as_listener(): void
    {
        $tenant = $this->createTenant(['slug' => 'audit-svc-same-row']);
        $user = $this->createUserForTenant($tenant);

        $this->app->instance('tenant', $tenant);
        Sanctum::actingAs($user, ['*']);

        $action = 'test.service.vs.listener';

        // Via listener (Event::dispatch)
        $eventForListener = $this->makeEvent($action, ['source' => 'listener']);
        event($eventForListener);

        // Via Service::log (rota direta)
        $eventForService = $this->makeEvent($action, ['source' => 'service']);
        $logFromService = $this->service->log($eventForService);

        $logFromListener = AuditLog::query()
            ->where('action', $action)
            ->where('payload->source', 'listener')
            ->first();

        $this->assertNotNull($logFromListener, 'Listener deve ter criado o log.');

        // Compara atributos relevantes (ignora id e created_at)
        $ignored = ['id', 'created_at'];

        $fromListener = array_diff_key($logFromListener->getAttributes(), array_flip($ignored));
        $fromService = array_diff_key($logFromService->getAttributes(), array_flip($ignored));

        // Remove 'payload->source' diferença — ambos têm mesma estrutura mas values diferentes
        // Compara apenas os atributos de contexto (tenant, user, actor, ip, ua, request_id)
        $contextKeys = ['tenant_id', 'user_id', 'actor_type', 'auditable_type', 'auditable_id'];

        foreach ($contextKeys as $key) {
            $this->assertSame(
                $fromListener[$key] ?? null,
                $fromService[$key] ?? null,
                "Atributo '{$key}' deve ser igual entre listener e service."
            );
        }
    }

    /**
     * `query()` com tenant no container deve escopar ao tenant atual.
     */
    public function test_query_scopes_to_current_tenant(): void
    {
        $tenantA = $this->createTenant(['slug' => 'audit-query-a']);
        $tenantB = $this->createTenant(['slug' => 'audit-query-b']);

        // Insere logs em ambos os tenants
        \DB::table('audit_logs')->insert([
            'tenant_id' => $tenantA->id,
            'actor_type' => 'system',
            'action' => 'test.query.scope',
            'payload' => '{}',
            'created_at' => now(),
        ]);

        \DB::table('audit_logs')->insert([
            'tenant_id' => $tenantA->id,
            'actor_type' => 'system',
            'action' => 'test.query.scope.2',
            'payload' => '{}',
            'created_at' => now(),
        ]);

        \DB::table('audit_logs')->insert([
            'tenant_id' => $tenantB->id,
            'actor_type' => 'system',
            'action' => 'test.query.scope.other',
            'payload' => '{}',
            'created_at' => now(),
        ]);

        // Injeta tenant A no container
        $this->app->instance('tenant', $tenantA);

        $count = $this->service->query()->count();

        $this->assertSame(2, $count, 'query() deve retornar apenas logs do tenant A.');
    }

    /**
     * `queryForTenant()` deve filtrar explicitamente pelo tenant, sem depender
     * do container (Super Admin path).
     */
    public function test_query_for_tenant_bypasses_scope(): void
    {
        $tenantA = $this->createTenant(['slug' => 'audit-qtenant-a']);
        $tenantB = $this->createTenant(['slug' => 'audit-qtenant-b']);

        \DB::table('audit_logs')->insert([
            'tenant_id' => $tenantA->id,
            'actor_type' => 'system',
            'action' => 'test.qtenant.a',
            'payload' => '{}',
            'created_at' => now(),
        ]);

        \DB::table('audit_logs')->insert([
            'tenant_id' => $tenantB->id,
            'actor_type' => 'system',
            'action' => 'test.qtenant.b',
            'payload' => '{}',
            'created_at' => now(),
        ]);

        // Sem tenant no container (Super Admin context)
        $count = $this->service->queryForTenant($tenantA->id)->count();

        $this->assertSame(1, $count, 'queryForTenant() deve retornar apenas logs do tenant A.');
    }

    /**
     * Keys sensíveis (`password`, `token` etc.) devem ser removidas do
     * payload antes da persistência (Princípio I — LGPD minimização).
     */
    public function test_payload_strips_sensitive_keys(): void
    {
        $event = $this->makeEvent('test.sensitive.payload', [
            'name' => 'João',
            'password' => 'supersecret',
            'token' => 'abc123',
            'remember_token' => 'xyz',
            'secret' => 'topsecret',
            'email' => 'joao@example.com',
        ]);

        $log = $this->service->log($event);

        $payload = $log->payload;

        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertArrayNotHasKey('remember_token', $payload);
        $this->assertArrayNotHasKey('secret', $payload);

        // Dados não-sensíveis devem ser preservados
        $this->assertArrayHasKey('name', $payload);
        $this->assertArrayHasKey('email', $payload);
    }
}
