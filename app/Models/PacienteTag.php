<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * T025 — Model `PacienteTag`, pivot estendido entre paciente e tag.
 *
 * Estende `Pivot` (não `Model`) pois é utilizado via `belongsToMany`
 * com `->using(PacienteTag::class)` quando precisar de acesso rico ao
 * pivot. Também pode ser instanciado diretamente para queries no pivot.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $paciente_id
 * @property int $tag_id
 * @property int|null $aplicada_por_user_id
 * @property Carbon $aplicada_at
 */
class PacienteTag extends Pivot
{
    protected $table = 'paciente_tags';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'paciente_id',
        'tag_id',
        'aplicada_por_user_id',
        'aplicada_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aplicada_at' => 'datetime',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function aplicadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aplicada_por_user_id');
    }
}
