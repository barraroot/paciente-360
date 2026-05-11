<?php

namespace App\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T027 — Model `EventoTimeline`, tabela canônica da timeline do paciente.
 *
 * Append-only: UPDATE e DELETE são proibidos tanto pelo trigger PG quanto
 * pelo boot do Model. Cada fase injeta eventos usando `tipo` em
 * dot-notation (`paciente.criado`, `tag.aplicada`, `funil.movimentacao`…).
 *
 * SEM `updated_at`: imutável.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $paciente_id
 * @property string $tipo
 * @property int|null $autor_id
 * @property string $actor_type
 * @property array<string, mixed> $payload
 * @property string|null $referencia_tipo
 * @property int|null $referencia_id
 * @property Carbon $created_at
 */
class EventoTimeline extends Model
{
    use BelongsToTenant;

    /**
     * Desabilita `updated_at` — eventos são imutáveis.
     */
    public const UPDATED_AT = null;

    protected $table = 'eventos_timeline';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'paciente_id',
        'tipo',
        'autor_id',
        'actor_type',
        'payload',
        'referencia_tipo',
        'referencia_id',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \RuntimeException('eventos_timeline is append-only: UPDATE is not permitted.');
        });

        static::deleting(static function (): never {
            throw new \RuntimeException('eventos_timeline is append-only: DELETE is not permitted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => AsJsonArray::class,
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
}
