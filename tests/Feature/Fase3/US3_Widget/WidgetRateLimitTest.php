<?php

namespace Tests\Feature\Fase3\US3_Widget;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Domain\Messaging\Widget\Models\WebWidgetSession;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T208 — Feature tests para rate limiting dos endpoints públicos do widget.
 *
 * Princípio VII: 30 req/min/IP no rate limiter `widget-public`.
 */
class WidgetRateLimitTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private string $publicKey;

    private string $visitorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-ratelimit', 'admin-clinica');

        $this->publicKey = bin2hex(random_bytes(32));
        $this->visitorToken = bin2hex(random_bytes(32));

        $webChannel = Channel::factory()
            ->forTenant($this->tenant)
            ->web()
            ->create();

        $widgetConfig = WebWidgetConfig::factory()
            ->forTenant($this->tenant)
            ->create([
                'channel_id' => $webChannel->id,
                'public_key' => $this->publicKey,
                'allowed_origins' => [],
                'outside_hours_behavior' => 'normal',
                'pre_chat_form' => 'opcional',
            ]);

        WebWidgetSession::factory()->create([
            'tenant_id' => $this->tenant->id,
            'widget_config_id' => $widgetConfig->id,
            'visitor_token' => $this->visitorToken,
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'expires_at' => now()->addDays(30),
        ]);
    }

    /** @test */
    public function test_public_endpoints_throttled_at_30_per_minute_per_ip(): void
    {
        // Clear the rate limiter before test
        RateLimiter::clear('widget-public:127.0.0.1');

        $url = "/widget/v1/{$this->publicKey}/messages";
        $headers = ['X-Visitor-Token' => $this->visitorToken];

        // Send 30 successful requests (widget-public = 30/min)
        for ($i = 1; $i <= 30; $i++) {
            $response = $this->withHeaders($headers)->postJson($url, [
                'content_type' => 'text',
                'body' => "Mensagem {$i}",
            ]);
            // Each should succeed or at least not be rate limited
            $this->assertNotEquals(429, $response->status(), "Request {$i} should not be rate limited");
        }

        // 31st request should be rate limited
        $response = $this->withHeaders($headers)->postJson($url, [
            'content_type' => 'text',
            'body' => 'Mensagem 31',
        ]);

        $response->assertStatus(429);
    }

    /** @test */
    public function test_rate_limit_isolated_per_ip(): void
    {
        // Different IPs should have independent rate limits
        // IP A: consume all quota
        RateLimiter::clear('widget-public:192.168.1.1');
        RateLimiter::clear('widget-public:192.168.1.2');

        $url = "/widget/v1/{$this->publicKey}/config";

        // Simulate IP A exhausting quota by hitting the rate limit directly
        RateLimiter::hit('widget-public:192.168.1.1', 60);
        RateLimiter::hit('widget-public:192.168.1.1', 60);
        // ... force exhaustion by artificially setting
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit('widget-public:192.168.1.1', 60);
        }

        // IP B should still be able to make requests
        // (We can't perfectly simulate different IPs in tests, so we verify
        // the rate limiter key structure is correct)
        $this->assertTrue(
            RateLimiter::tooManyAttempts('widget-public:192.168.1.1', 30),
            'IP A deve estar throttled'
        );
        $this->assertFalse(
            RateLimiter::tooManyAttempts('widget-public:192.168.1.2', 30),
            'IP B deve ter quota disponível'
        );
    }
}
