<?php

namespace App\Events\Agenda;

use App\Models\Agenda\CalendarSyncAccount;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T146 — Evento de domínio (US-6.7): connect/disconnect/error de sync externo.
 *
 * Status:
 *  - connected     → ativação inicial OU reconexão
 *  - disconnected  → revogação OAuth detectada
 *  - error         → falha transitória (5xx, timeout)
 */
class CalendarioExternoSincronizado
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CalendarSyncAccount $account,
        public readonly string $status,    // connected | disconnected | error
    ) {}
}
