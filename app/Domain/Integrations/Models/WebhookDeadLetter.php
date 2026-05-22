<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\WebhookDeadLetterFactory;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * **T189 (Fase 8 — Lote D US-11.1)** — Dead Letter Queue (DLQ).
 *
 * Recebe deliveries após 5 retries esgotados (Q16). Admin pode reenviar
 * (AC-11.1.6) ou aguardar expiração automática em 30d (T202).
 */
class WebhookDeadLetter extends Model
{
    /** @use HasFactory<WebhookDeadLetterFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'webhook_dead_letter';

    protected $fillable = [
        'tenant_id',
        'webhook_endpoint_id',
        'original_delivery_id',
        'event_type',
        'event_id',
        'correlation_id',
        'payload',
        'failure_history',
        'failed_at',
        'expires_at',
        'resent_by_user_id',
        'resent_at',
    ];

    protected $casts = [
        'payload' => AsArrayObject::class,
        'failure_history' => AsArrayObject::class,
        'failed_at' => 'datetime',
        'expires_at' => 'datetime',
        'resent_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function originalDelivery(): BelongsTo
    {
        return $this->belongsTo(WebhookDelivery::class, 'original_delivery_id');
    }

    public function resentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resent_by_user_id');
    }

    protected static function newFactory(): WebhookDeadLetterFactory
    {
        return WebhookDeadLetterFactory::new();
    }
}
