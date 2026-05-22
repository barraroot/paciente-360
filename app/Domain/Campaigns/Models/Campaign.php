<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * **T155 (Fase 8 — Lote C US-9.1)** — Campanha de disparo em massa.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property CampaignStatus $status
 * @property CampaignChannel $channel
 * @property int|null $template_id
 * @property array<string, mixed> $audience_filters
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $dispatched_at
 * @property int|null $total_eligible
 * @property int $total_dispatched
 * @property int $total_blocked
 * @property int $daily_limit_applied
 * @property Carbon|null $canceled_at
 * @property int|null $canceled_by_user_id
 * @property string|null $canceled_reason
 * @property int $created_by_user_id
 */
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'campaigns';

    protected $fillable = [
        'tenant_id',
        'name',
        'status',
        'channel',
        'template_id',
        'audience_filters',
        'scheduled_for',
        'dispatched_at',
        'total_eligible',
        'total_dispatched',
        'total_blocked',
        'daily_limit_applied',
        'canceled_at',
        'canceled_by_user_id',
        'canceled_reason',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'channel' => CampaignChannel::class,
            'audience_filters' => AsJsonArray::class,
            'scheduled_for' => 'datetime',
            'dispatched_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CampaignFactory
    {
        return CampaignFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by_user_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function dispatchLogs(): HasMany
    {
        return $this->hasMany(CampaignDispatchLog::class);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::Draft);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::Scheduled);
    }

    public function scopeDispatching(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::Dispatching);
    }

    /**
     * Campanhas com `scheduled_for` no passado, prontas para dispatch.
     */
    public function scopeReadyToDispatch(Builder $query): Builder
    {
        return $query
            ->where('status', CampaignStatus::Scheduled)
            ->where('scheduled_for', '<=', Carbon::now());
    }
}
