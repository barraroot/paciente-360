<?php

namespace App\Models;

use App\Events\Professional\ProfessionalDeactivated;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\ProfessionalSchedule;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Model `Professional` — profissional de saúde vinculado a um tenant.
 *
 * Um profissional é um usuário com perfil clínico (médico, fisioterapeuta,
 * etc.). O vínculo `user_id` aponta para o usuário do sistema; `name`,
 * `council_type/number/state` são dados do CRM e podem diferir do User.
 *
 * T260: Dispara evento `ProfessionalDeactivated` quando `is_active` muda
 * `true → false` em update.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $name
 * @property string|null $council_type
 * @property string|null $council_number
 * @property string|null $council_state
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Professional extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'professionals';

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'pending_invitation_email',
        'name',
        'council_type',
        'council_type_other',
        'council_number',
        'council_state',
        'especialidade',
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

    public static function boot(): void
    {
        parent::boot();

        static::updated(function (Professional $professional) {
            // Verifica se is_active mudou de true para false
            if ($professional->wasChanged('is_active') && $professional->is_active === false) {
                ProfessionalDeactivated::dispatch($professional);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Fase 5 — Agenda recorrente do profissional (US-6.1).
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ProfessionalSchedule::class);
    }

    /**
     * Fase 5 — Tipos de atendimento aceitos pelo profissional (US-6.1 / US-6.2).
     */
    public function appointmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            AppointmentType::class,
            'appointment_type_professional',
            'professional_id',
            'appointment_type_id'
        )->withPivot(['tenant_id', 'created_at']);
    }
}
