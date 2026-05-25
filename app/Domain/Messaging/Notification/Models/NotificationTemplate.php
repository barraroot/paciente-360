<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Notification\Models;

use App\Casts\AsJsonArray;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Notifications\NotificationTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Feature 013 — Catálogo de template de notificação por tenant.
 *
 * Mapeia (tenant, tipo, canal) → template aprovado do provedor. A aprovação
 * real é consultada em runtime no `ChannelTemplate` (Princípio VI / D1).
 *
 * @property int $id
 * @property int $tenant_id
 * @property NotificationType $notification_type
 * @property string $channel_type
 * @property string $provider_template_id
 * @property string $language
 * @property array<string, string> $variables_map
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class NotificationTemplate extends Model
{
    /** @use HasFactory<NotificationTemplateFactory> */
    use BelongsToTenant;

    use HasFactory;
    use SoftDeletes;

    protected $table = 'notification_templates';

    protected static function newFactory(): NotificationTemplateFactory
    {
        return NotificationTemplateFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'notification_type',
        'channel_type',
        'provider_template_id',
        'language',
        'variables_map',
        'is_active',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'notification_type' => NotificationType::class,
            'variables_map' => AsJsonArray::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
