<?php

namespace App\Domain\Messaging\Widget\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use Database\Factories\Messaging\WebWidgetSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T034 — Model `WebWidgetSession`: sessão de visitante anônimo no widget.
 *
 * Lead provisório que pode virar paciente (NC-1.c, NC-10.c).
 * `visitor_token` → cookie/localStorage no browser do visitante.
 * `ip_hash` = SHA-256 do IP real — nunca armazenamos IP plain (LGPD).
 * `provisional_data` → dados capturados no form pré-chat antes de virar paciente formal.
 *
 * Expiração: `expires_at = started_at + 30 days`. Job mensal purga sessões
 * expiradas com `identified_patient_id IS NULL`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $widget_config_id
 * @property string $visitor_token
 * @property string $ip_hash
 * @property string|null $user_agent
 * @property string|null $referer_domain
 * @property Carbon $started_at
 * @property Carbon $last_activity_at
 * @property int|null $identified_patient_id
 * @property array<string, mixed> $provisional_data
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebWidgetSession extends Model
{
    /** @use HasFactory<WebWidgetSessionFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'messaging_web_widget_sessions';

    protected static function newFactory(): WebWidgetSessionFactory
    {
        return WebWidgetSessionFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'widget_config_id',
        'visitor_token',
        'ip_hash',
        'user_agent',
        'referer_domain',
        'started_at',
        'last_activity_at',
        'identified_patient_id',
        'provisional_data',
        'expires_at',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'provisional_data' => AsJsonArray::class,
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function widgetConfig(): BelongsTo
    {
        return $this->belongsTo(WebWidgetConfig::class, 'widget_config_id');
    }

    public function identifiedPatient(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'identified_patient_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }
}
