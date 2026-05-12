<?php

namespace Tests\Feature\Fase3\US3_Widget;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T203 — Feature tests para endpoint de snippet JS do widget.
 *
 * GET /api/v1/inbox/widget-configs/{channel_id}/snippet
 * Retorna text/plain com a tag <script> para embutir no site do cliente.
 */
class SnippetEndpointTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    private Channel $webChannel;

    private WebWidgetConfig $widgetConfig;

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->adminUser] = $this->tenantAndUserForRole('clinica-snippet', 'admin-clinica');

        $this->publicKey = str_pad('abc123', 64, '0', STR_PAD_RIGHT);

        $this->webChannel = Channel::factory()
            ->forTenant($this->tenant)
            ->web()
            ->create();

        $this->widgetConfig = WebWidgetConfig::factory()
            ->forTenant($this->tenant)
            ->create([
                'channel_id' => $this->webChannel->id,
                'public_key' => $this->publicKey,
            ]);
    }

    private function snippetUrl(): string
    {
        return $this->tenantUrl($this->tenant, "/inbox/widget-configs/{$this->webChannel->id}/snippet");
    }

    /** @test */
    public function test_snippet_endpoint_returns_script_tag_with_public_key(): void
    {
        $response = $this->get($this->snippetUrl());

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $content = $response->getContent();

        // Must contain the public key in the src URL
        $this->assertStringContainsString($this->publicKey, $content);
        $this->assertStringContainsString('<script', $content);
        $this->assertStringContainsString('async', $content);
        $this->assertStringContainsString('/widget/v1/', $content);
        $this->assertStringContainsString('.js', $content);
    }

    /** @test */
    public function test_snippet_uses_configured_widget_public_domain(): void
    {
        config(['messaging.widget.public_domain' => 'widget.crm.com.br']);
        config(['messaging.widget.public_protocol' => 'https']);

        $response = $this->get($this->snippetUrl());

        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('widget.crm.com.br', $content);
    }

    /** @test */
    public function test_snippet_requires_authentication(): void
    {
        // Attempt without any authentication
        $this->app['auth']->forgetGuards();

        $response = $this->getJson($this->snippetUrl());

        $this->assertContains($response->status(), [401, 403], 'Snippet endpoint must require authentication');
    }

    /** @test */
    public function test_snippet_endpoint_unauthenticated_returns_401(): void
    {
        // Force un-auth by forgetting the current auth guards
        $this->app['auth']->forgetGuards();

        $response = $this->getJson($this->snippetUrl());

        $this->assertContains($response->status(), [401, 403]);
    }
}
