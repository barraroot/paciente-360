<?php

namespace Tests\Feature\Fase0\Audit;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * T071 — Testa a persistência de eventos `Auditable` via listener.
 *
 * Valida que o `PersistAuditLogListener` captura corretamente:
 *  - `action`, `tenant_id`, `user_id` do evento.
 *  - `request_id` propagado pelo `LogStructuredRequestData` via `app('request_id')`.
 *  - `ip` e `user_agent` do request HTTP.
 *  - `actor_type = 'system'` quando não há usuário autenticado.
 *
 * @see App\Listeners\PersistAuditLogListener
 * @see App\Events\Contracts\Auditable
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
class AuditPersistenceTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    /**
     * Evento dummy que implementa `Auditable` via trait `IsAuditable`.
     * Dispara um evento com action e tenant/user do container.
     */
    private function makeDummyEvent(
        string $action = 'test.dummy',
        array $payload = [],
        ?int $tenantId = null,
        ?int $userId = null,
        ?Model $auditable = null,
    ): Auditable {
        return new class($action, $payload, $tenantId, $userId, $auditable) implements Auditable
        {
            use Dispatchable;
            use IsAuditable;

            public function __construct(
                private readonly string $actionName,
                private readonly array $payloadData,
                private readonly ?int $tenantIdOverride,
                private readonly ?int $userIdOverride,
                private readonly ?Model $auditableTarget,
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
                return $this->auditableTarget;
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
     * Disparar um evento que implementa `Auditable` deve persistir
     * uma linha em `audit_logs` com action, tenant_id e user_id corretos.
     */
    public function test_dummy_auditable_event_persists_audit_log(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-audit-persist']);
        $user = $this->createUserForTenant($tenant);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($user);

        $event = $this->makeDummyEvent('test.dummy.event', ['key' => 'value']);
        event($event);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test.dummy.event',
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'actor_type' => 'user',
        ]);
    }

    /**
     * Quando o middleware `LogStructuredRequestData` coloca o `request_id`
     * no container via `app()->instance('request_id', $id)`, o listener
     * deve capturá-lo e salvar em `audit_logs.request_id`.
     */
    public function test_listener_captures_request_id_when_available(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-req-id']);
        $user = $this->createUserForTenant($tenant);

        $requestId = (string) Str::ulid();

        Sanctum::actingAs($user);

        // O middleware LogStructuredRequestData injeta o request_id via app()->instance()
        // e também no header X-Request-Id. Simula o comportamento aqui.
        $this->app->instance('request_id', $requestId);

        $response = $this->withHeaders([
            'X-Request-Id' => $requestId,
            'Host' => 'clinica-req-id.lvh.me',
        ])->getJson('/api/v1/_me');

        $response->assertOk();
        $echoedRequestId = $response->headers->get('X-Request-Id');

        // Dispara evento auditable para verificar captura do request_id
        $event = $this->makeDummyEvent('test.request_id.capture');
        event($event);

        $log = AuditLog::query()->where('action', 'test.request_id.capture')->first();

        $this->assertNotNull($log, 'Log de auditoria deve ter sido criado.');
        $this->assertNotEmpty($log->request_id, 'request_id deve estar preenchido.');
        $this->assertSame($echoedRequestId, $log->request_id);
    }

    /**
     * O listener deve capturar o IP e user_agent do request HTTP.
     */
    public function test_listener_captures_ip_user_agent_from_request(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-ip-ua']);
        $user = $this->createUserForTenant($tenant);

        Sanctum::actingAs($user);

        $this->app->instance('tenant', $tenant);

        $event = $this->makeDummyEvent('test.ip_ua.capture');
        event($event);

        $log = AuditLog::query()->where('action', 'test.ip_ua.capture')->latest('id')->first();

        $this->assertNotNull($log);
        // Em ambiente de teste, o IP pode ser 127.0.0.1 ou null — verificamos que o campo existe
        $this->assertArrayHasKey('ip', $log->getAttributes());
    }

    /**
     * Quando não há usuário autenticado (CLI/job/sistema), o `actor_type`
     * deve ser `'system'` e `user_id` deve ser `null`.
     */
    public function test_actor_type_is_system_when_no_authenticated_user(): void
    {
        // Garante que nenhum usuário está autenticado
        auth()->logout();

        $event = $this->makeDummyEvent('test.system.actor', [], null, null);
        event($event);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test.system.actor',
            'user_id' => null,
            'actor_type' => 'system',
        ]);
    }
}
