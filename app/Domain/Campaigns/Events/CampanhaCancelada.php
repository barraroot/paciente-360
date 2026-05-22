<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

final class CampanhaCancelada implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $campaignId,
        public readonly int $canceledByUserId,
        public readonly Carbon $canceledAt,
        public readonly ?string $reason,
    ) {}

    public function auditAction(): string
    {
        return 'campaign.canceled';
    }

    public function auditPayload(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'canceled_at' => $this->canceledAt->toIso8601String(),
            'reason' => $this->reason,
        ];
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    public function auditTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function auditUserId(): ?int
    {
        return $this->canceledByUserId;
    }
}
