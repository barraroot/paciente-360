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

final class CampanhaDisparada implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $campaignId,
        public readonly Carbon $dispatchedAt,
        public readonly int $totalEligible,
    ) {}

    public function auditAction(): string
    {
        return 'campaign.dispatched';
    }

    public function auditPayload(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'dispatched_at' => $this->dispatchedAt->toIso8601String(),
            'total_eligible' => $this->totalEligible,
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
}
