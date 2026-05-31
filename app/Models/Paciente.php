<?php

namespace App\Models;

use App\Casts\AsJsonArray;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * T023 — Model `Paciente`, entidade central do CRM.
 *
 * Imutabilidade de `anonimizado_em`: uma vez anonimizado, o paciente não
 * aparece em queries normais (global scope `not_anonymized`). Use
 * `scopeWithAnonymized()` para incluí-los explicitamente.
 *
 * Colunas GENERATED (`nome_normalizado`, `telefone_primario_normalizado`)
 * não estão em `$fillable` — são computadas pelo PostgreSQL.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $nome
 * @property string|null $nome_normalizado
 * @property string|null $cpf
 * @property string|null $documento_estrangeiro
 * @property Carbon|null $data_nascimento
 * @property string|null $telefone_primario
 * @property string|null $telefone_primario_normalizado
 * @property array<int, mixed> $telefones_secundarios
 * @property string|null $email
 * @property array<string, mixed>|null $endereco
 * @property string $status
 * @property string $origem
 * @property string|null $origem_detalhe
 * @property string $origem_origem
 * @property int|null $profissional_responsavel_id
 * @property int|null $convenio_principal_id
 * @property int|null $funil_coluna_atual_id
 * @property float|null $funil_posicao
 * @property Carbon|null $anonimizado_em
 * @property int|null $merged_into_paciente_id
 * @property Carbon|null $merged_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Paciente extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pacientes';

    /** @var array<int, string> */
    protected $fillable = [
        'nome',
        'cpf',
        'documento_estrangeiro',
        'data_nascimento',
        'telefone_primario',
        'telefones_secundarios',
        'email',
        'endereco',
        'status',
        'origem',
        'origem_detalhe',
        'origem_origem',
        'profissional_responsavel_id',
        'convenio_principal_id',
        'funil_coluna_atual_id',
        'funil_posicao',
        'anonimizado_em',
        'merged_into_paciente_id',
        'merged_at',
        // Feature 018 (T085-pre, US2, FR-010) — identificadores de canais
        // não-WhatsApp para auto-onboarding como lead.
        'instagram_handle',
        'widget_anonymous_id',
    ];

    /**
     * Global scope adicional: oculta pacientes anonimizados por padrão.
     * Use `scopeWithAnonymized()` para incluí-los explicitamente.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('not_anonymized', fn (Builder $q) => $q->whereNull('anonimizado_em'));
    }

    /**
     * Remove o scope `not_anonymized` para incluir pacientes anonimizados.
     * Uso restrito a relatórios LGPD / Super Admin.
     */
    public function scopeWithAnonymized(Builder $query): Builder
    {
        return $query->withoutGlobalScope('not_anonymized');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'telefones_secundarios' => AsJsonArray::class,
            'endereco' => AsJsonArray::class,
            'data_nascimento' => 'date',
            'anonimizado_em' => 'datetime',
            'merged_at' => 'datetime',
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'paciente_tags')
            ->withPivot('aplicada_por_user_id', 'aplicada_at');
    }

    public function convenios(): HasMany
    {
        return $this->hasMany(PacienteConvenio::class);
    }

    public function anotacoes(): HasMany
    {
        return $this->hasMany(Anotacao::class);
    }

    public function eventosTimeline(): HasMany
    {
        return $this->hasMany(EventoTimeline::class);
    }

    /**
     * Feature 018 (T104) — usado pelo DowngradeToLostOnInactivityListener
     * para consultar `last_inbound_message_at` das conversas do paciente.
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'patient_id');
    }

    public function profissionalResponsavel(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'profissional_responsavel_id');
    }

    public function funilColuna(): BelongsTo
    {
        return $this->belongsTo(FunilColuna::class, 'funil_coluna_atual_id');
    }
}
