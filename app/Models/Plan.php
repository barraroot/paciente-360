<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Model `Plan` — catálogo comercial global (sem `tenant_id`).
 *
 * Tabela: `plans` (schema em data-model.md § 2). Tabela GLOBAL: NÃO usa
 * `BelongsToTenant`; é referenciada por `tenants.plan_id` (RESTRICT).
 *
 * Snapshot do plano vigente fica em `subscriptions.plan_snapshot`
 * (JSONB) — alterações de catálogo NÃO retroagem em contratos ativos.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $base_price_cents
 * @property int $included_professionals
 * @property int $included_ai_messages
 * @property int $overage_price_cents
 * @property int $max_users
 * @property int $max_channels
 * @property string $stripe_price_id_base
 * @property string $stripe_price_id_overage
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $table = 'plans';

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'description',
        'base_price_cents',
        'included_professionals',
        'included_ai_messages',
        'overage_price_cents',
        'max_users',
        'max_channels',
        'stripe_price_id_base',
        'stripe_price_id_overage',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price_cents' => 'integer',
            'included_professionals' => 'integer',
            'included_ai_messages' => 'integer',
            'overage_price_cents' => 'integer',
            'max_users' => 'integer',
            'max_channels' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
