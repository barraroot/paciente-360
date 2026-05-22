<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CampanhaAtualizada implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    /**
     * @param list<string> $changedFields
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $campaignId,
        public readonly int $updatedByUserId,
        public readonly array $changedFields,
    ) {}

    public function auditAction(): string
    {
        return 'campaign.updated';
    }

    public function auditPayload(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'changed_fields' => $this->changedFields,
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
        return $this->updatedByUserId;
    }
}
