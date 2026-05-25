<?php

namespace Tests\Feature\Channels;

use App\Domain\Messaging\Channel\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * Gate G7 (US2) — webhook do Evolution rejeita apikey inválida e ignora
 * instância desconhecida sem criar dados.
 */
class EvolutionWebhookAuthTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config()->set('messaging.providers.evolution.webhook_secret', 'wh-secret');
    }

    public function test_webhook_without_apikey_is_rejected(): void
    {
        $this->postJson('/api/v1/webhooks/evolution/any', [
            'event' => 'connection.update',
            'data' => ['state' => 'open'],
        ])->assertStatus(401);
    }

    public function test_webhook_with_wrong_apikey_is_rejected(): void
    {
        $this->postJson('/api/v1/webhooks/evolution/any', [
            'event' => 'connection.update',
            'data' => ['state' => 'open'],
        ], ['apikey' => 'wrong'])->assertStatus(401);
    }

    public function test_unknown_instance_is_noop_with_200(): void
    {
        $this->postJson('/api/v1/webhooks/evolution/ghost-instance', [
            'event' => 'connection.update',
            'instance' => 'ghost-instance',
            'data' => ['state' => 'open'],
        ], ['apikey' => 'wh-secret'])->assertOk();

        $this->assertSame(0, Channel::withoutGlobalScopes()->count());
    }
}
