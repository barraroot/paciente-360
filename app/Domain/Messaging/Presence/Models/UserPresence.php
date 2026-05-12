<?php

namespace App\Domain\Messaging\Presence\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\Messaging\UserPresenceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T036 — Model `UserPresence`: presença online de atendentes (NC-6.b).
 *
 * Status inferido por `last_seen_at` (último heartbeat via WebSocket ou HTTP).
 * Sem status manual no MVP — `PresenceTrackerService` atualiza via Reverb heartbeat.
 *
 * `current_assigned_count` denormalizado para auto-atribuição rápida evitar COUNT().
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $status online|away|offline
 * @property Carbon $last_seen_at
 * @property int $max_concurrent_conversations
 * @property int $current_assigned_count
 * @property array<string, mixed> $notification_preferences
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserPresence extends Model
{
    /** @use HasFactory<UserPresenceFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'messaging_user_presence';

    protected static function newFactory(): UserPresenceFactory
    {
        return UserPresenceFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'status',
        'last_seen_at',
        'max_concurrent_conversations',
        'current_assigned_count',
        'notification_preferences',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'notification_preferences' => AsJsonArray::class,
            'last_seen_at' => 'datetime',
            'max_concurrent_conversations' => 'integer',
            'current_assigned_count' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -------------------------------------------------------------------------
    // Domain methods
    // -------------------------------------------------------------------------

    /**
     * Verifica se o atendente está online com base no último heartbeat.
     *
     * @param int $idleMinutes Minutos de inatividade para considerar offline.
     */
    public function isOnline(int $idleMinutes = 5): bool
    {
        return $this->last_seen_at->greaterThan(now()->subMinutes($idleMinutes));
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeOnline(Builder $query, int $idleMinutes = 5): Builder
    {
        return $query
            ->where('status', 'online')
            ->where('last_seen_at', '>', now()->subMinutes($idleMinutes));
    }
}
