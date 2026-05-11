<?php

namespace App\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T028 — Model `MesclagemPaciente`, rastro reversível de operações de merge.
 *
 * `snapshot_pre_merge` persiste o estado completo antes da mescla para
 * permitir reversão dentro de `reversivel_ate`. Job mensal nula o snapshot
 * após `reversivel_ate + 30 dias` para conter o crescimento do JSONB.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $paciente_alvo_id
 * @property array<int, int> $pacientes_origem_ids
 * @property int $executor_id
 * @property array<string, mixed> $snapshot_pre_merge
 * @property array<string, mixed> $resolucoes
 * @property Carbon $executada_em
 * @property Carbon $reversivel_ate
 * @property Carbon|null $revertida_em
 * @property int|null $revertida_por_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MesclagemPaciente extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'mesclagens_pacientes';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'paciente_alvo_id',
        'pacientes_origem_ids',
        'executor_id',
        'snapshot_pre_merge',
        'resolucoes',
        'executada_em',
        'reversivel_ate',
        'revertida_em',
        'revertida_por_user_id',
        'paciente_principal_id',
        'paciente_secundario_id',
        'motivo',
        'relatorio',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pacientes_origem_ids' => 'array',
            'snapshot_pre_merge' => AsJsonArray::class,
            'resolucoes' => AsJsonArray::class,
            'relatorio' => AsJsonArray::class,
            'executada_em' => 'datetime',
            'reversivel_ate' => 'datetime',
            'revertida_em' => 'datetime',
        ];
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    public function revertidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revertida_por_user_id');
    }

    /**
     * Verifica se a mesclagem é reversível (window de reversibilidade ainda ativo).
     */
    public function isReversivel(): bool
    {
        return $this->revertida_em === null && $this->reversivel_ate->isFuture();
    }
}
