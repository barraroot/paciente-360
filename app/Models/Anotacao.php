<?php

namespace App\Models;

use App\Exceptions\Pacientes\AnotacaoImutavelException;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * T026 — Model `Anotacao`, anotações tipadas imutáveis.
 *
 * Imutabilidade em duas camadas:
 *  1. Boot: `updating` e `deleting` lançam `AnotacaoImutavelException`.
 *  2. Trigger PG `anotacoes_immutable_trigger`: defesa em profundidade.
 *
 * Retratação = nova anotação com `retratacao_de_anotacao_id` preenchido.
 * O registro original nunca é alterado.
 *
 * SEM `updated_at`: registros imutáveis não precisam de campo de atualização.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $paciente_id
 * @property string $tipo
 * @property string $texto
 * @property int $autor_id
 * @property int|null $retratacao_de_anotacao_id
 * @property Carbon $created_at
 */
class Anotacao extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * Desabilita `updated_at` — anotações são imutáveis.
     */
    public const UPDATED_AT = null;

    protected $table = 'anotacoes';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'paciente_id',
        'tipo',
        'texto',
        'autor_id',
        'retratacao_de_anotacao_id',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new AnotacaoImutavelException('Anotações são imutáveis. Use retratação.'));
        static::deleting(fn () => throw new AnotacaoImutavelException('Anotações não podem ser excluídas.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    public function retratacaoDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retratacao_de_anotacao_id');
    }

    public function retratacoes(): HasMany
    {
        return $this->hasMany(self::class, 'retratacao_de_anotacao_id');
    }
}
