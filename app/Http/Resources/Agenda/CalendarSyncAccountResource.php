<?php

namespace App\Http\Resources\Agenda;

use App\Models\Agenda\CalendarSyncAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T165 — CalendarSyncAccountResource (US-6.7).
 *
 * **NUNCA** expõe encrypted_access_token/encrypted_refresh_token (Princípio I).
 *
 * @mixin CalendarSyncAccount
 */
class CalendarSyncAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'professional_id' => $this->professional_id,
            'provider' => $this->provider,
            'provider_email' => $this->provider_email,
            'google_calendar_id' => $this->google_calendar_id,
            'google_calendar_name_seen' => $this->google_calendar_name_seen,
            'watch_channel_expires_at' => $this->watch_channel_expires_at?->toIso8601String(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'last_disconnect_at' => $this->last_disconnect_at?->toIso8601String(),
            'last_reconnect_at' => $this->last_reconnect_at?->toIso8601String(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
