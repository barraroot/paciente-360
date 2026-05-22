<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * **T189 (Fase 8 — Lote D US-11.1)** — Endpoint webhook do tenant.
 *
 * `secret` é criptografado at-rest via cast 'encrypted' (Princípio I).
 * `events_subscribed` filtra quais eventos Q17 disparam delivery para este endpoint.
 */
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use BelongsToTenant;

    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'secret',
        'events_subscribed',
        'is_active',
        'failure_count',
        'last_success_at',
        'last_failure_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'secret' => 'encrypted',
        'events_subscribed' => AsArrayObject::class,
        'is_active' => 'boolean',
        'failure_count' => 'integer',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    protected $hidden = ['secret'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function isSubscribedTo(string $eventType): bool
    {
        $events = $this->events_subscribed?->toArray() ?? [];

        return in_array($eventType, $events, true) || in_array('*', $events, true);
    }

    protected static function newFactory(): WebhookEndpointFactory
    {
        return WebhookEndpointFactory::new();
    }
}
