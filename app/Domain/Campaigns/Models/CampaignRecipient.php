<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use App\Models\Tenant;
use Database\Factories\CampaignRecipientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **T156 (Fase 8 — Lote C US-9.1)** — Destinatário de campanha (snapshot).
 *
 * UNIQUE(campaign_id, patient_id) — idempotência absoluta (Q AC-9.1.6).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $campaign_id
 * @property int $patient_id
 * @property Carbon|null $dispatched_at
 * @property CampaignRecipientStatus $status
 * @property string|null $blocked_reason
 * @property string|null $external_message_id
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $responded_at
 * @property int|null $attributed_appointment_id
 */
class CampaignRecipient extends Model
{
    /** @use HasFactory<CampaignRecipientFactory> */
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'campaign_recipients';

    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'patient_id',
        'dispatched_at',
        'status',
        'blocked_reason',
        'external_message_id',
        'delivered_at',
        'read_at',
        'responded_at',
        'attributed_appointment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignRecipientStatus::class,
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CampaignRecipientFactory
    {
        return CampaignRecipientFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'patient_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CampaignRecipientStatus::Pending);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', CampaignRecipientStatus::Sent);
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('status', CampaignRecipientStatus::Blocked);
    }
}
