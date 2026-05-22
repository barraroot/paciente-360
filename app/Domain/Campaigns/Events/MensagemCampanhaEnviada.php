<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Events;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AC-9.1.3 — emitido por destinatário. `blocked_reason` populado quando
 * status='blocked'; null quando sent/delivered.
 */
final class MensagemCampanhaEnviada implements Auditable, ContainsNoClinicalData
{
    use Dispatchable;
    use IsAuditable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $campaignId,
        public readonly int $patientId,
        public readonly string $channel,
        public readonly string $status,
        public readonly ?string $blockedReason,
    ) {}

    public function auditAction(): string
    {
        return 'campaign.message.sent';
    }

    public function auditPayload(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'patient_id' => $this->patientId,
            'channel' => $this->channel,
            'status' => $this->status,
            'blocked_reason' => $this->blockedReason,
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
