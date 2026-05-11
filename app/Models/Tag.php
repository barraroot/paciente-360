<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * T025 — Model `Tag`, catálogo de tags por tenant.
 *
 * Tags sistêmicas (`tipo = 'sistemica'`) têm prefixo `sys:` em
 * `nome_normalizado` e só podem ser criadas por Super Admin / sistema.
 *
 * `nome_normalizado` é calculado na camada Service (lower + unaccent)
 * antes de persistir — diferente de `pacientes` que usa GENERATED no PG.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $nome
 * @property string $nome_normalizado
 * @property string $tipo
 * @property string|null $cor
 * @property string|null $descricao
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Tag extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'tags';

    /** @var array<int, string> */
    protected $fillable = [
        'nome',
        'nome_normalizado',
        'tipo',
        'cor',
        'descricao',
    ];

    /**
     * Filtra apenas tags de tipo livre (criadas pelo usuário).
     */
    public function scopeLivre(Builder $query): Builder
    {
        return $query->where('tipo', 'livre');
    }

    /**
     * Filtra apenas tags sistêmicas (criadas pelo sistema/Super Admin).
     */
    public function scopeSystemica(Builder $query): Builder
    {
        return $query->where('tipo', 'sistemica');
    }

    public function pacientes(): BelongsToMany
    {
        return $this->belongsToMany(Paciente::class, 'paciente_tags')
            ->withPivot('aplicada_por_user_id', 'aplicada_at');
    }
}
