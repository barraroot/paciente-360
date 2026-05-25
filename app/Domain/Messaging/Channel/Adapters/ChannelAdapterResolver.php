<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Channel\Adapters;

use App\Domain\Messaging\Channel\Enums\ChannelProvider;
use App\Domain\Messaging\Channel\Models\Channel;
use InvalidArgumentException;

/**
 * Feature 014 — Ponto único de seleção de adapter por `(type, provider)`.
 *
 * Substitui o `match($channel->type)` hardcoded nos jobs de envio/recebimento,
 * tornando a resolução ciente do provedor (whatsapp pode ser Twilio OU Evolution).
 *
 * @see specs/014-channel-provider-integration/research.md §R4
 */
final class ChannelAdapterResolver
{
    public function __construct(
        private readonly WhatsAppCloudAdapter $whatsAppAdapter,
        private readonly EvolutionApiAdapter $evolutionAdapter,
        private readonly InstagramGraphAdapter $instagramAdapter,
        private readonly WebWidgetAdapter $webWidgetAdapter,
    ) {}

    public function for(Channel $channel): ChannelAdapter
    {
        return match ($channel->type) {
            'whatsapp' => $this->forWhatsapp($channel),
            'instagram' => $this->instagramAdapter,
            'web' => $this->webWidgetAdapter,
            default => throw new InvalidArgumentException("Tipo de canal não suportado: {$channel->type}"),
        };
    }

    private function forWhatsapp(Channel $channel): ChannelAdapter
    {
        $provider = $channel->provider instanceof ChannelProvider
            ? $channel->provider
            : ChannelProvider::from((string) ($channel->provider ?? 'twilio'));

        return $provider === ChannelProvider::Evolution
            ? $this->evolutionAdapter
            : $this->whatsAppAdapter;
    }
}
