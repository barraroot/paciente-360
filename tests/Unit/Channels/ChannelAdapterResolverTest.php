<?php

namespace Tests\Unit\Channels;

use App\Domain\Messaging\Channel\Adapters\ChannelAdapterResolver;
use App\Domain\Messaging\Channel\Adapters\EvolutionApiAdapter;
use App\Domain\Messaging\Channel\Adapters\InstagramGraphAdapter;
use App\Domain\Messaging\Channel\Adapters\WebWidgetAdapter;
use App\Domain\Messaging\Channel\Adapters\WhatsAppCloudAdapter;
use App\Domain\Messaging\Channel\Models\Channel;
use Tests\TestCase;

/**
 * T015 — Unit do ChannelAdapterResolver (resolve por type + provider).
 */
class ChannelAdapterResolverTest extends TestCase
{
    private function resolver(): ChannelAdapterResolver
    {
        return app(ChannelAdapterResolver::class);
    }

    private function channel(string $type, string $provider = 'twilio'): Channel
    {
        return Channel::factory()->make(['type' => $type, 'provider' => $provider]);
    }

    public function test_whatsapp_twilio_resolves_to_whatsapp_cloud_adapter(): void
    {
        $this->assertInstanceOf(
            WhatsAppCloudAdapter::class,
            $this->resolver()->for($this->channel('whatsapp', 'twilio')),
        );
    }

    public function test_whatsapp_evolution_resolves_to_evolution_adapter(): void
    {
        $this->assertInstanceOf(
            EvolutionApiAdapter::class,
            $this->resolver()->for($this->channel('whatsapp', 'evolution')),
        );
    }

    public function test_instagram_and_web_resolve_to_their_adapters(): void
    {
        $this->assertInstanceOf(InstagramGraphAdapter::class, $this->resolver()->for($this->channel('instagram')));
        $this->assertInstanceOf(WebWidgetAdapter::class, $this->resolver()->for($this->channel('web')));
    }
}
