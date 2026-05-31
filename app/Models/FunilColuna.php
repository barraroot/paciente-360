<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * T028 — Model `FunilColuna`, configuração de Kanban por tenant.
 *
 * `is_system = true`: colunas criadas pelo template padrão; o usuário pode
 * renomear mas não pode deletar.
 *
 * Reordenamento via swap explícito de `posicao` (UNIQUE por tenant garante
 * sem colisão).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $nome
 * @property string $slug
 * @property int $posicao
 * @property string|null $cor
 * @property bool $is_initial
 * @property bool $is_terminal
 * @property bool $motivo_obrigatorio
 * @property bool $is_system
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class FunilColuna extends Model
{
    use BelongsToTenant;

    protected $table = 'funil_colunas';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'nome',
        'slug',
        'posicao',
        'cor',
        'is_initial',
        'is_terminal',
        'motivo_obrigatorio',
        'is_system',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_initial' => 'boolean',
            'is_terminal' => 'boolean',
            'motivo_obrigatorio' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class, 'funil_coluna_atual_id');
    }
}
