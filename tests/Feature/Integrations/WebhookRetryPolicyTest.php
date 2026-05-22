<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Jobs\DispatchWebhookJob;
use App\Domain\Integrations\Jobs\MoveToDeadLetterJob;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T206 (Fase 8 — Lote D US-11.1)** — AC-11.1.3 — Retry policy 5x.
 *
 * Simula 5xx HTTP repetidamente e valida que após `max_attempts` a delivery
 * vira `dead_letter` via `MoveToDeadLetterJob`.
 */
class WebhookRetryPolicyTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_500_response_schedules_retry(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('boom', 500)]);

        [$tenant] = $this->tenantAndUserForRole('clinic-retry-1', 'admin-clinica');

        $endpoint = WebhookEndpoint::factory()->create([
            'tenant_id' => $tenant->id,
            'events_subscribed' => ['paciente.criado'],
        ]);

        $delivery = WebhookDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_endpoint_id' => $endpoint->id,
            'attempts' => 0,
            'max_attempts' => 5,
        ]);

        (new DispatchWebhookJob($delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->next_attempt_at);
        $this->assertSame(WebhookDelivery::STATUS_RETRYING, $delivery->status);

        Queue::assertPushed(DispatchWebhookJob::class);
    }

    public function test_after_max_attempts_moves_to_dlq(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('still failing', 500)]);

        [$tenant] = $this->tenantAndUserForRole('clinic-retry-2', 'admin-clinica');

        $endpoint = WebhookEndpoint::factory()->create([
            'tenant_id' => $tenant->id,
            'events_subscribed' => ['paciente.criado'],
        ]);

        $delivery = WebhookDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_endpoint_id' => $endpoint->id,
            'attempts' => 4,
            'max_attempts' => 5,
        ]);

        (new DispatchWebhookJob($delivery->id))->handle();

        Queue::assertPushed(MoveToDeadLetterJob::class);
    }
}
