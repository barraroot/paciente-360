<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T024 — Model `PacienteConvenio`, pivot entre paciente e convênio.
 *
 * Suporta `papel` = 'principal' | 'secundario'. O UNIQUE (paciente_id, papel)
 * garante no máximo 2 convênios por paciente.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $paciente_id
 * @property int $convenio_id
 * @property string|null $numero_carteirinha
 * @property string $papel
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PacienteConvenio extends Model
{
    use BelongsToTenant;

    protected $table = 'paciente_convenios';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'paciente_id',
        'convenio_id',
        'numero_carteirinha',
        'papel',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }
}
