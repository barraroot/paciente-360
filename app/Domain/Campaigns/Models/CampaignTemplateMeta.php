<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Database\Factories\CampaignTemplateMetaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **T143 (Fase 8 — Lote C US-9.3)** — Metadata de campanha sobre messaging_channel_templates.
 *
 * **Princípio VI** (Conformidade Meta): `has_unsubscribe=true` é OBRIGATÓRIO
 * para campanhas não-transacionais. Templates sem unsubscribe são rejeitados
 * no cadastro (validação Fase 3 sync) E no momento do dispatch (gate runtime).
 *
 * Cache 30min do status Meta evita over-querying da Graph API durante batch
 * de dispatch — `MetaTemplateStatusChecker` (T145) usa este modelo.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $messaging_channel_template_id
 * @property bool $has_unsubscribe
 * @property Carbon|null $last_compliance_check_at
 * @property string|null $last_known_meta_status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 */
class CampaignTemplateMeta extends Model
{
    /** @use HasFactory<CampaignTemplateMetaFactory> */
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'campaign_templates_meta';

    protected $fillable = [
        'tenant_id',
        'messaging_channel_template_id',
        'has_unsubscribe',
        'last_compliance_check_at',
        'last_known_meta_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_unsubscribe' => 'boolean',
            'last_compliance_check_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CampaignTemplateMetaFactory
    {
        return CampaignTemplateMetaFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeApprovedAndCompliant(Builder $query): Builder
    {
        return $query
            ->where('has_unsubscribe', true)
            ->where('last_known_meta_status', 'approved');
    }

    public function scopeStale(Builder $query, int $minutes): Builder
    {
        $cutoff = Carbon::now()->subMinutes($minutes);

        return $query->where(function ($q) use ($cutoff): void {
            $q->whereNull('last_compliance_check_at')
                ->orWhere('last_compliance_check_at', '<', $cutoff);
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Cache hit: status checado nos últimos N minutos (default 30 — Q AC-9.3.5).
     */
    public function isCheckFresh(int $minutes = 30): bool
    {
        if ($this->last_compliance_check_at === null) {
            return false;
        }

        return $this->last_compliance_check_at->diffInMinutes(Carbon::now()) <= $minutes;
    }

    /**
     * Aprovado, com unsubscribe E check recente — pronto para dispatch.
     */
    public function isDispatchReady(): bool
    {
        return $this->has_unsubscribe
            && $this->last_known_meta_status === 'approved'
            && $this->isCheckFresh();
    }
}
