<?php

declare(strict_types=1);

namespace App\Domain\Crm\Kanban\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\FunilColuna;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feature 018 (US3) — mapping evento→coluna do funil por tenant.
 *
 * Cada tenant tem seu pipeline; o mapping default vem do
 * DefaultKanbanPipelineMappingSeeder (T035, idempotente). Tenant pode
 * customizar via KanbanPipelineMappingController (T106).
 *
 * event_kind é o gatilho de domínio (lead_created, qualification_started,
 * value_accepted, slot_held, reservation_confirmed, ai_paused_to_human,
 * inactivity). KanbanAutoTransitionService (T100) consulta esta tabela
 * antes de aplicar a transição.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $event_kind
 * @property int $funil_coluna_id
 * @property bool $is_active
 */
class KanbanPipelineMapping extends Model
{
    use BelongsToTenant;

    protected $table = 'kanban_pipeline_mappings';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'event_kind',
        'funil_coluna_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FunilColuna, $this>
     */
    public function funilColuna(): BelongsTo
    {
        return $this->belongsTo(FunilColuna::class, 'funil_coluna_id');
    }
}
