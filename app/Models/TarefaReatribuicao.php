<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T028 — Model `TarefaReatribuicao`, backlog de pacientes órfãos por
 * desativação de profissional.
 *
 * `concluida_em IS NULL` = tarefa pendente. UI lista pendentes via
 * `INDEX (tenant_id, concluida_em)`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $profissional_desativado_id
 * @property array<int, int> $pacientes_orfaos_ids
 * @property int $total_pacientes
 * @property Carbon $criada_em
 * @property Carbon|null $concluida_em
 * @property int|null $concluida_por_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TarefaReatribuicao extends Model
{
    use BelongsToTenant;

    protected $table = 'tarefas_reatribuicao';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'profissional_desativado_id',
        'pacientes_orfaos_ids',
        'total_pacientes',
        'criada_em',
        'concluida_em',
        'concluida_por_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pacientes_orfaos_ids' => 'array',
            'criada_em' => 'datetime',
            'concluida_em' => 'datetime',
        ];
    }

    public function profissionalDesativado(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'profissional_desativado_id');
    }

    public function concluidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluida_por_user_id');
    }

    /**
     * Verifica se a tarefa está pendente de conclusão.
     */
    public function isPendente(): bool
    {
        return $this->concluida_em === null;
    }
}
