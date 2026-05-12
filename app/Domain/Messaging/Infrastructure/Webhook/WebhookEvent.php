<?php

namespace App\Domain\Messaging\Infrastructure\Webhook;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\Tenant;
use Database\Factories\Messaging\WebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T037 — Model `WebhookEvent`: evento de webhook recebido (sem BelongsToTenant).
 *
 * Tenant é resolvido DENTRO do job via lookup de `channel_id`. Idempotência via
 * UNIQUE `(provider, external_id)` — INSERT ON CONFLICT DO NOTHING (R9).
 *
 * `raw_payload_encrypted` cifrado em repouso — pode conter PII do paciente (LGPD).
 *
 * SEM BelongsToTenant: `tenant_id` é nullable e preenchido pós-processamento;
 * o global scope quebraria inserções antes da resolução do tenant.
 *
 * @property int $id
 * @property string $provider twilio|meta|widget
 * @property string $external_id
 * @property int|null $channel_id
 * @property int|null $tenant_id
 * @property string $event_type
 * @property string $raw_payload_encrypted (criptografado)
 * @property bool $signature_verified
 * @property Carbon $received_at
 * @property string $status received|processing|processed|failed|duplicate
 * @property Carbon|null $processing_started_at
 * @property Carbon|null $processed_at
 * @property int $attempts
 * @property string|null $failure_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    protected $table = 'messaging_webhook_events';

    protected static function newFactory(): WebhookEventFactory
    {
        return WebhookEventFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'provider',
        'external_id',
        'channel_id',
        'tenant_id',
        'event_type',
        'raw_payload_encrypted',
        'signature_verified',
        'received_at',
        'status',
        'processing_started_at',
        'processed_at',
        'attempts',
        'failure_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_payload_encrypted' => 'encrypted',
            'signature_verified' => 'boolean',
            'received_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
