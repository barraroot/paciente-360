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

final class CampanhaCriada implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $audienceFilters
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $campaignId,
        public readonly int $createdByUserId,
        public readonly string $channel,
        public readonly array $audienceFilters,
        public readonly ?Carbon $scheduledFor,
    ) {}

    public function auditAction(): string
    {
        return 'campaign.created';
    }

    public function auditPayload(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'channel' => $this->channel,
            'audience_filters' => $this->audienceFilters,
            'scheduled_for' => $this->scheduledFor?->toIso8601String(),
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
        return $this->createdByUserId;
    }
}
