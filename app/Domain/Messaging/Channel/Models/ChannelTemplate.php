<?php

namespace App\Domain\Messaging\Channel\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Messaging\ChannelTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T027 — Model `ChannelTemplate`: templates aprovados pela Meta (sync read-only).
 *
 * Tenant não cadastra templates aqui no MVP — apenas consome templates
 * sincronizados via `ChannelTemplateSyncJob` (Twilio Content API ou Meta Graph).
 * Status `approved` é pré-requisito para envio fora da janela 24h (Princípio VI).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $channel_id
 * @property string $provider_template_id
 * @property string $meta_template_name
 * @property string $meta_template_status approved|pending|rejected|paused
 * @property string $language
 * @property string|null $category
 * @property string|null $body_preview
 * @property array<int, mixed> $variables_schema
 * @property Carbon $last_synced_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ChannelTemplate extends Model
{
    /** @use HasFactory<ChannelTemplateFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'messaging_channel_templates';

    protected static function newFactory(): ChannelTemplateFactory
    {
        return ChannelTemplateFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'channel_id',
        'provider_template_id',
        'meta_template_name',
        'meta_template_status',
        'language',
        'category',
        'body_preview',
        'variables_schema',
        'last_synced_at',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'variables_schema' => AsJsonArray::class,
            'last_synced_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('meta_template_status', 'approved');
    }
}
