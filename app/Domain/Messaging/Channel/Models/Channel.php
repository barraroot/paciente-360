<?php

namespace App\Domain\Messaging\Channel\Models;

use App\Casts\AsJsonArray;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Messaging\ChannelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * T026 — Model `Channel`: canal externo conectado ao tenant.
 *
 * Suporta 3 tipos: `whatsapp` (via Twilio), `instagram` (via Meta Graph API
 * direto), `web` (widget embutível). Credenciais são armazenadas criptografadas
 * em `credentials_encrypted` (AES-256-CBC via cast `encrypted`) — nunca em log.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $type whatsapp|instagram|web
 * @property string $name
 * @property string $status ativo|desconectado|invalido|expirado|degradado|suspenso
 * @property string|null $credentials_encrypted
 * @property array<string, mixed> $provider_metadata
 * @property string|null $quality_rating high|medium|low|flagged
 * @property Carbon|null $quality_rating_updated_at
 * @property Carbon|null $last_health_check_at
 * @property bool $auto_send_disabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use BelongsToTenant;

    use HasFactory;
    use SoftDeletes;

    protected $table = 'messaging_channels';

    protected static function newFactory(): ChannelFactory
    {
        return ChannelFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'type',
        'name',
        'status',
        'credentials_encrypted',
        'provider_metadata',
        'quality_rating',
        'quality_rating_updated_at',
        'last_health_check_at',
        'auto_send_disabled',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'credentials_encrypted' => 'encrypted',
            'provider_metadata' => AsJsonArray::class,
            'quality_rating_updated_at' => 'datetime',
            'last_health_check_at' => 'datetime',
            'auto_send_disabled' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function templates(): HasMany
    {
        return $this->hasMany(ChannelTemplate::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(
            Conversation::class
        );
    }

    public function widgetConfig(): HasOne
    {
        return $this->hasOne(
            WebWidgetConfig::class
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('status', 'ativo');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
