<?php

namespace App\Models\Agenda;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Professional;
use App\Models\Tenant;
use Database\Factories\Agenda\CalendarSyncAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo OAuth Google Calendar — US-6.7 / clarify nº 10/11/15.
 *
 * UNIQUE(tenant_id, professional_id) — gate clarify nº 15.
 * Tokens encrypted at rest (Princípio I — credenciais de terceiros).
 */
class CalendarSyncAccount extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'calendar_sync_accounts';

    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'encrypted_access_token' => 'encrypted',
        'encrypted_refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
        'watch_channel_expires_at' => 'datetime',
        'last_polled_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_disconnect_at' => 'datetime',
        'last_disconnect_notified_at' => 'datetime',
        'last_reconnect_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_access_token',
        'encrypted_refresh_token',
    ];

    protected static function newFactory(): CalendarSyncAccountFactory
    {
        return CalendarSyncAccountFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('status', 'connected');
    }

    public function scopeNeedsWatchRenewal(Builder $query, int $hoursAhead = 48): Builder
    {
        return $query->where('status', 'connected')
            ->whereNotNull('watch_channel_expires_at')
            ->where('watch_channel_expires_at', '<=', now()->addHours($hoursAhead));
    }
}
