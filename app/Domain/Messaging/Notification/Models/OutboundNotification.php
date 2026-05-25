<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Notification\Models;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Notification\Enums\NotificationSkipReason;
use App\Domain\Messaging\Notification\Enums\NotificationStatus;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Notifications\OutboundNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Feature 013 — Rastreio de uma tentativa de notificar um paciente.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $patient_id
 * @property NotificationType $notification_type
 * @property string $milestone
 * @property NotificationStatus $status
 * @property NotificationSkipReason|null $skip_reason
 * @property int|null $channel_id
 * @property int|null $conversation_id
 * @property int|null $notification_template_id
 * @property int|null $message_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $failed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OutboundNotification extends Model
{
    /** @use HasFactory<OutboundNotificationFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'outbound_notifications';

    protected static function newFactory(): OutboundNotificationFactory
    {
        return OutboundNotificationFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'patient_id',
        'notification_type',
        'milestone',
        'status',
        'skip_reason',
        'channel_id',
        'conversation_id',
        'notification_template_id',
        'message_id',
        'source_type',
        'source_id',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'notification_type' => NotificationType::class,
            'status' => NotificationStatus::class,
            'skip_reason' => NotificationSkipReason::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
