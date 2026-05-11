<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * T024 — Model `Convenio`, catálogo de convênios por tenant.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $nome
 * @property string|null $codigo_ans
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Convenio extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'convenios';

    /** @var array<int, string> */
    protected $fillable = [
        'nome',
        'codigo_ans',
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

    public function pacienteConvenios(): HasMany
    {
        return $this->hasMany(PacienteConvenio::class);
    }
}
