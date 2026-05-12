<?php

namespace App\Domain\Messaging\Message\Models;

use App\Casts\AsJsonArray;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use App\Models\User;
use Database\Factories\Messaging\MessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * T030 — Model `Message`: mensagem individual do canal.
 *
 * `body` é cifrado em repouso (AES-256-CBC via cast `encrypted`) — LGPD/RNF-007.
 * `body_searchable` é plain text para indexação trigram (NC-13/R5); NUNCA em log.
 * `body_preview` (primeiros 140 chars) para listagem de inbox sem decriptar.
 *
 * Padrão append-only: apenas campos de status (`delivered_at`, `read_at`, `status`,
 * `failure_reason`) são atualizados após criação.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $conversation_id
 * @property string $direction in|out
 * @property string $sender_type patient|user|system|ai
 * @property int|null $sender_id
 * @property string|null $body (criptografado)
 * @property string|null $body_searchable
 * @property string|null $body_preview
 * @property string $content_type
 * @property string|null $template_provider_id
 * @property array<int, mixed>|null $template_variables
 * @property string|null $external_id
 * @property array<string, mixed> $external_metadata
 * @property string $status queued|sent|delivered|read|failed
 * @property string|null $failure_reason
 * @property string|null $idempotency_key
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'messaging_messages';

    protected static function newFactory(): MessageFactory
    {
        return MessageFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'direction',
        'sender_type',
        'sender_id',
        'body',
        'body_searchable',
        'body_preview',
        'content_type',
        'template_provider_id',
        'template_variables',
        'external_id',
        'external_metadata',
        'status',
        'failure_reason',
        'idempotency_key',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'external_metadata' => AsJsonArray::class,
            'template_variables' => AsJsonArray::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(MessageMedia::class);
    }

    /**
     * Retorna o User remetente quando `sender_type = 'user'`.
     */
    public function senderUser(): ?User
    {
        if ($this->sender_type !== 'user' || $this->sender_id === null) {
            return null;
        }

        return User::find($this->sender_id);
    }

    /**
     * Retorna o Paciente remetente quando `sender_type = 'patient'`.
     */
    public function senderPatient(): ?Paciente
    {
        if ($this->sender_type !== 'patient' || $this->sender_id === null) {
            return null;
        }

        return Paciente::find($this->sender_id);
    }

    // -------------------------------------------------------------------------
    // Domain methods
    // -------------------------------------------------------------------------

    /**
     * Retorna o ID do paciente para projeção na timeline (listener Fase 2).
     * Usa `loadMissing` para evitar N+1 quando conversation não está carregada.
     */
    public function relatedPacienteId(): ?int
    {
        $this->loadMissing('conversation');

        return $this->conversation?->patient_id;
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', 'queued');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', 'in');
    }

    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', 'out');
    }
}
