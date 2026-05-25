<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Notification\DataTransfer;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Notification\Services\OutboundChannelResolver;

/**
 * Feature 013 — Resultado de {@see OutboundChannelResolver}.
 *
 * `withinWindow` indica se há janela de 24h aberta (última inbound < 24h),
 * caso em que texto livre é permitido; fora da janela exige template aprovado.
 */
final readonly class ResolvedChannel
{
    public function __construct(
        public Channel $channel,
        public Conversation $conversation,
        public bool $withinWindow,
    ) {}
}
