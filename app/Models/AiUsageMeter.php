<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model `AiUsageMeter` — contador mensal de uso de IA por tenant (T033).
 *
 * Tabela: `ai_usage_meters` (schema em data-model.md § 14).
 *
 * Cada linha representa um ciclo `year_month` (YYYY-MM, fuso BRT).
 * `firstOrCreate(['tenant_id', 'year_month'])` garante unicidade via
 * constraint UNIQUE `ai_usage_tenant_month_uniq`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $year_month CHAR(7) formato YYYY-MM em BRT
 * @property int $messages_count
 * @property int $included_quota_snapshot
 * @property int $overage_count
 * @property int|null $hard_cap
 * @property Carbon|null $hard_cap_triggered_at
 * @property Carbon $last_reset_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
class AiUsageMeter extends Model
{
    use BelongsToTenant;

    protected $table = 'ai_usage_meters';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'year_month',
        'messages_count',
        'included_quota_snapshot',
        'overage_count',
        'hard_cap',
        'hard_cap_triggered_at',
        'last_reset_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'messages_count' => 'integer',
            'included_quota_snapshot' => 'integer',
            'overage_count' => 'integer',
            'hard_cap' => 'integer',
            'hard_cap_triggered_at' => 'datetime',
            'last_reset_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Verifica se o hard cap está ativo e foi atingido.
     */
    public function isHardCapTriggered(): bool
    {
        return $this->hard_cap_triggered_at !== null;
    }
}
