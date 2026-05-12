<?php

namespace App\Domain\Messaging\Conversation\Models;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\Messaging\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * T028 — Model `Conversation`: stream contínuo por par (paciente, canal).
 *
 * Decisão NC-2: 1 conversa por par `(tenant_id, channel_id, external_thread_id_normalized)`.
 * Máquina de estados: `aberta → pendente → resolvida → reaberta`.
 *
 * Campo `ai_paused_until` é o contrato com a Fase 4 (IA); não há IA ativa nesta fase.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $channel_id
 * @property int|null $patient_id
 * @property string $external_thread_id
 * @property string $external_thread_id_normalized
 * @property string $status aberta|pendente|resolvida|reaberta
 * @property int|null $assigned_user_id
 * @property Carbon|null $assigned_at
 * @property string|null $assignment_strategy
 * @property Carbon|null $ai_paused_until
 * @property int|null $ai_pause_set_by
 * @property Carbon|null $last_message_at
 * @property Carbon|null $last_inbound_message_at
 * @property Carbon $opened_at
 * @property Carbon|null $resolved_at
 * @property string|null $resolution_mode
 * @property string $priority alta|normal|baixa
 * @property bool $received_outside_hours
 * @property int $unread_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'messaging_conversations';

    protected static function newFactory(): ConversationFactory
    {
        return ConversationFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'channel_id',
        'patient_id',
        'external_thread_id',
        'status',
        'assigned_user_id',
        'assigned_at',
        'assignment_strategy',
        'ai_paused_until',
        'ai_pause_set_by',
        'last_message_at',
        'last_inbound_message_at',
        'opened_at',
        'resolved_at',
        'resolution_mode',
        'priority',
        'received_outside_hours',
        'unread_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_message_at' => 'datetime',
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
            'assigned_at' => 'datetime',
            'ai_paused_until' => 'datetime',
            'received_outside_hours' => 'boolean',
            'unread_count' => 'integer',
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

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'patient_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function aiPauseSetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_pause_set_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ConversationAssignment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // -------------------------------------------------------------------------
    // Domain methods
    // -------------------------------------------------------------------------

    /**
     * Verifica se a janela de 24h do WhatsApp/Instagram está aberta.
     * A janela abre a cada mensagem inbound do paciente.
     * Princípio VI — bloqueio em runtime de envio fora da janela sem template.
     */
    public function isWindow24hOpen(): bool
    {
        if ($this->last_inbound_message_at === null) {
            return false;
        }

        return $this->last_inbound_message_at->greaterThan(now()->subHours(24));
    }

    /**
     * Segundos restantes na janela 24h. Null se janela fechada ou nunca aberta.
     */
    public function secondsRemainingInWindow(): ?int
    {
        if ($this->last_inbound_message_at === null) {
            return null;
        }

        $expiresAt = $this->last_inbound_message_at->addHours(24);

        if ($expiresAt->isPast()) {
            return null;
        }

        return (int) now()->diffInSeconds($expiresAt, false);
    }

    /**
     * Retorna o ID do paciente relacionado para uso pelo listener wildcard
     * `RegistraEventoTimelineListener` (Fase 2) ao projetar eventos na timeline.
     */
    public function relatedPacienteId(): ?int
    {
        return $this->patient_id;
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeAberta(Builder $query): Builder
    {
        return $query->where('status', 'aberta');
    }

    public function scopePendente(Builder $query): Builder
    {
        return $query->where('status', 'pendente');
    }

    public function scopeResolvida(Builder $query): Builder
    {
        return $query->where('status', 'resolvida');
    }
}
