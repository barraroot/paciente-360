<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Notification\DataTransfer;

use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Services\OutboundNotificationDispatcher;

/**
 * Feature 013 — DTO imutável montado pelos listeners e consumido pelo
 * {@see OutboundNotificationDispatcher}.
 *
 * `context` carrega APENAS dados não-clínicos para preencher variáveis de
 * template (nome, data/hora, profissional, clínica, dias até vencimento) —
 * Princípio I.
 *
 * @see specs/013-outbound-notifications/contracts/outbound-notifications.md §1
 */
final readonly class NotificationRequest
{
    /**
     * @param array<string, scalar|null> $context
     * @param int|null $professionalId profissional relacionado (para opt-out por par)
     */
    public function __construct(
        public int $tenantId,
        public int $patientId,
        public NotificationType $type,
        public string $milestone,
        public string $sourceType,
        public int $sourceId,
        public array $context = [],
        public ?string $freeFormBody = null,
        public ?int $professionalId = null,
    ) {}
}
