<?php

namespace App\Providers;

use App\Events\Contracts\Auditable;
use App\Listeners\PersistAuditLogListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Provider de eventos da aplicação.
 *
 * Registra o `PersistAuditLogListener` para interceptar QUALQUER evento
 * que implemente a interface `Auditable` — padrão "interface wildcard"
 * do Laravel 13 via `Event::listen(InterfaceClass::class, ...)`.
 *
 * @see App\Events\Contracts\Auditable
 * @see App\Listeners\PersistAuditLogListener
 * @see specs/001-fundacao-multitenant/spec.md — FR-035
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap dos listeners de eventos.
     */
    public function boot(): void
    {
        Event::listen(Auditable::class, PersistAuditLogListener::class);
    }
}
