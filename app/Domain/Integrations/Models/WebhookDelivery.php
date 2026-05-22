<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * **T189 (Fase 8 — Lote D US-11.1)** — Log de tentativa de entrega.
 *
 * Status: pending → retrying → delivered (sucesso) ou dead_letter (5 retries esgotados).
 */
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use BelongsToTenant;

    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_DEAD_LETTER = 'dead_letter';

    protected $fillable = [
        'tenant_id',
        'webhook_endpoint_id',
        'event_type',
        'event_id',
        'correlation_id',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'next_attempt_at',
        'delivered_at',
        'last_response',
        'last_error',
    ];

    protected $casts = [
        'payload' => AsArrayObject::class,
        'last_response' => AsArrayObject::class,
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function deadLetter(): HasOne
    {
        return $this->hasOne(WebhookDeadLetter::class, 'original_delivery_id');
    }

    protected static function newFactory(): WebhookDeliveryFactory
    {
        return WebhookDeliveryFactory::new();
    }
}
