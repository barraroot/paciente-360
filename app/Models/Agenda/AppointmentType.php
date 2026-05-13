<?php

namespace App\Models\Agenda;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Professional;
use App\Models\Tenant;
use Database\Factories\Agenda\AppointmentTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tipo de atendimento (US-6.2 — clarify nº 4).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $nome
 * @property string $slug
 * @property int $duration_minutes
 * @property int $buffer_minutes
 * @property string $valor_particular
 * @property string|null $valor_convenio_default
 * @property int|null $min_cancellation_hours
 * @property string $cor
 * @property string|null $descricao
 * @property string|null $intent_ia
 * @property bool $is_active
 */
class AppointmentType extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'appointment_types';

    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'duration_minutes' => 'integer',
        'buffer_minutes' => 'integer',
        'valor_particular' => 'decimal:2',
        'valor_convenio_default' => 'decimal:2',
        'min_cancellation_hours' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): AppointmentTypeFactory
    {
        return AppointmentTypeFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(
            Professional::class,
            'appointment_type_professional',
            'appointment_type_id',
            'professional_id'
        )->withPivot('tenant_id')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
