<?php

namespace App\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T028 — Model `Importacao`, estado de importações assíncronas de pacientes.
 *
 * `checkpoint` permite retry idempotente: armazena a última linha processada
 * e contadores parciais para retomar onde parou em caso de falha.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $executor_id
 * @property string $arquivo_path
 * @property string $arquivo_nome_original
 * @property string $arquivo_hash
 * @property int $arquivo_tamanho_bytes
 * @property int|null $total_linhas
 * @property string $status
 * @property string $status_inicial_pacientes
 * @property array<string, mixed> $checkpoint
 * @property array<string, mixed>|null $relatorio
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Importacao extends Model
{
    use BelongsToTenant;

    protected $table = 'importacoes';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'executor_id',
        'arquivo_path',
        'arquivo_nome_original',
        'arquivo_hash',
        'arquivo_tamanho_bytes',
        'total_linhas',
        'status',
        'status_inicial_pacientes',
        'checkpoint',
        'relatorio',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checkpoint' => AsJsonArray::class,
            'relatorio' => AsJsonArray::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }
}
