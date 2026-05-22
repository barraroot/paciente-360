<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Events\WebhookFalhou;
use App\Domain\Integrations\Events\WebhookReagendado;
use App\Domain\Integrations\Jobs\DispatchWebhookJob;
use App\Domain\Integrations\Jobs\MoveToDeadLetterJob;
use App\Domain\Integrations\Models\WebhookDeadLetter;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T207 (Fase 8 — Lote D US-11.1)** — AC-11.1.5 + AC-11.1.6.
 *
 * DLQ recebe rows após esgotar retries; admin reenvia manualmente via
 * endpoint POST /dlq/{dlq}/resend.
 */
class WebhookDeadLetterTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_move_to_dlq_job_creates_dead_letter_and_emits_event(): void
    {
        Event::fake([WebhookFalhou::class]);

        [$tenant] = $this->tenantAndUserForRole('clinic-dlq-1', 'admin-clinica');

        $endpoint = WebhookEndpoint::factory()->create(['tenant_id' => $tenant->id]);
        $delivery = WebhookDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_endpoint_id' => $endpoint->id,
            'attempts' => 5,
            'last_error' => 'HTTP 500',
        ]);

        (new MoveToDeadLetterJob($delivery->id))->handle();

        $this->assertDatabaseHas('webhook_dead_letter', [
            'tenant_id' => $tenant->id,
            'original_delivery_id' => $delivery->id,
        ]);

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_DEAD_LETTER, $delivery->status);

        Event::assertDispatched(WebhookFalhou::class);
    }

    public function test_admin_can_resend_from_dlq(): void
    {
        Queue::fake();
        Event::fake([WebhookReagendado::class]);

        [$tenant, $admin] = $this->tenantAndUserForRole('clinic-dlq-2', 'admin-clinica');

        $endpoint = WebhookEndpoint::factory()->create(['tenant_id' => $tenant->id]);
        $delivery = WebhookDelivery::factory()->failed()->create([
            'tenant_id' => $tenant->id,
            'webhook_endpoint_id' => $endpoint->id,
        ]);
        $dlq = WebhookDeadLetter::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_endpoint_id' => $endpoint->id,
            'original_delivery_id' => $delivery->id,
        ]);

        $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->postJson("/api/v1/integrations/webhooks/dlq/{$dlq->id}/resend")
            ->assertStatus(202)
            ->assertJsonStructure(['message', 'delivery_id']);

        $dlq->refresh();
        $this->assertNotNull($dlq->resent_at);

        Queue::assertPushed(DispatchWebhookJob::class);
        Event::assertDispatched(WebhookReagendado::class);
    }
}
