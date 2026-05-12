<?php

namespace App\Domain\Messaging\Assignment\Models;

use App\Casts\AsJsonArray;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Messaging\AssignmentRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T035 — Model `AssignmentRule`: regra de auto-atribuição configurável por tenant.
 *
 * `channel_id IS NULL` → aplica a todos os canais do tenant.
 * `priority` menor = maior prioridade (avaliadas em ordem crescente).
 * MVP suporta `round_robin` e `patient_owner` com fallback (NC-6.a).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $channel_id
 * @property string $strategy round_robin|patient_owner|manual
 * @property int $priority
 * @property bool $is_active
 * @property array<string, mixed> $config
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AssignmentRule extends Model
{
    /** @use HasFactory<AssignmentRuleFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'messaging_assignment_rules';

    protected static function newFactory(): AssignmentRuleFactory
    {
        return AssignmentRuleFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'channel_id',
        'strategy',
        'priority',
        'is_active',
        'config',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'config' => AsJsonArray::class,
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrderedByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority');
    }
}
