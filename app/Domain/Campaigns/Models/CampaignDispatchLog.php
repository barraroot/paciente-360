<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use App\Models\Tenant;
use Database\Factories\CampaignDispatchLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **T156 (Fase 8 — Lote C US-9.1)** — Log granular por tentativa.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $campaign_id
 * @property int $patient_id
 * @property Carbon $attempted_at
 * @property string $result
 * @property string|null $block_reason
 * @property array<string, mixed>|null $details
 */
class CampaignDispatchLog extends Model
{
    /** @use HasFactory<CampaignDispatchLogFactory> */
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'campaign_dispatch_log';

    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'patient_id',
        'attempted_at',
        'result',
        'block_reason',
        'details',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'details' => AsJsonArray::class,
        ];
    }

    protected static function newFactory(): CampaignDispatchLogFactory
    {
        return CampaignDispatchLogFactory::new();
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

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('result', 'blocked');
    }

    public function scopeByReason(Builder $query, string $reason): Builder
    {
        return $query->where('block_reason', $reason);
    }
}
