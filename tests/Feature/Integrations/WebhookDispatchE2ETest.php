<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Jobs\DispatchWebhookJob;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Integrations\Services\WebhookDispatcher;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T205 (Fase 8 — Lote D US-11.1)** — AC-11.1.1 → AC-11.1.4.
 *
 * Pipeline ponta a ponta: WebhookDispatcher cria WebhookDelivery e
 * enfileira DispatchWebhookJob com correlation_id.
 */
class WebhookDispatchE2ETest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_dispatcher_creates_delivery_and_enqueues_job(): void
    {
        Queue::fake();
        [$tenant, $admin] = $this->tenantAndUserForRole('clinic-wh-1', 'admin-clinica');

        $endpoint = WebhookEndpoint::factory()->create([
            'tenant_id' => $tenant->id,
            'events_subscribed' => ['agendamento.criado'],
            'is_active' => true,
        ]);

        $dispatcher = app(WebhookDispatcher::class);
        $dispatcher->dispatch(
            'agendamento.criado',
            'evt-uuid-001',
            ['appointment_id' => 42],
            $tenant->id,
            'corr-test-1',
        );

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'agendamento.criado',
            'event_id' => 'evt-uuid-001',
            'correlation_id' => 'corr-test-1',
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        Queue::assertPushedOn('webhooks', DispatchWebhookJob::class);
    }

    public function test_dispatcher_skips_idempotent_event(): void
    {
        Queue::fake();
        [$tenant] = $this->tenantAndUserForRole('clinic-wh-2', 'admin-clinica');

        $endpoint = WebhookEndpoint::factory()->create([
            'tenant_id' => $tenant->id,
            'events_subscribed' => ['agendamento.criado'],
        ]);

        $dispatcher = app(WebhookDispatcher::class);

        $dispatcher->dispatch('agendamento.criado', 'evt-dedup', ['x' => 1], $tenant->id);
        $dispatcher->dispatch('agendamento.criado', 'evt-dedup', ['x' => 2], $tenant->id);

        $this->assertSame(1, WebhookDelivery::query()
            ->where('webhook_endpoint_id', $endpoint->id)
            ->where('event_id', 'evt-dedup')
            ->count());
    }

    public function test_dispatcher_only_targets_subscribed_endpoints(): void
    {
        Queue::fake();
        [$tenant] = $this->tenantAndUserForRole('clinic-wh-3', 'admin-clinica');

        WebhookEndpoint::factory()->create([
            'tenant_id' => $tenant->id,
            'events_subscribed' => ['paciente.criado'],
        ]);

        $dispatcher = app(WebhookDispatcher::class);
        $dispatcher->dispatch('agendamento.criado', 'evt-x', ['a' => 1], $tenant->id);

        $this->assertDatabaseCount('webhook_deliveries', 0);
    }
}
