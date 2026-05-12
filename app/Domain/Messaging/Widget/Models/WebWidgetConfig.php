<?php

namespace App\Domain\Messaging\Widget\Models;

use App\Casts\AsJsonArray;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Messaging\WebWidgetConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * T033 — Model `WebWidgetConfig`: configuração do widget web por tenant.
 *
 * Relação 1:1 com `messaging_channels` (quando `type='web'`).
 * `public_key` é embedada no snippet JS; globalmente única.
 * `allowed_origins` é whitelist de domínios autorizados (NC-10.b).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $channel_id
 * @property string $public_key
 * @property array<int, string> $allowed_origins
 * @property array<string, mixed> $appearance
 * @property string|null $initial_message
 * @property array<string, mixed> $business_hours
 * @property string $outside_hours_behavior bloqueia|fila|normal
 * @property string $pre_chat_form opcional|exigido_para_iniciar|exigido_para_enviar
 * @property string|null $outside_hours_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebWidgetConfig extends Model
{
    /** @use HasFactory<WebWidgetConfigFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'messaging_web_widget_configs';

    protected static function newFactory(): WebWidgetConfigFactory
    {
        return WebWidgetConfigFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'channel_id',
        'public_key',
        'allowed_origins',
        'appearance',
        'initial_message',
        'business_hours',
        'outside_hours_behavior',
        'pre_chat_form',
        'outside_hours_message',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'allowed_origins' => AsJsonArray::class,
            'appearance' => AsJsonArray::class,
            'business_hours' => AsJsonArray::class,
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(WebWidgetSession::class, 'widget_config_id');
    }
}
