<?php

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado pelo middleware `ResolveTenant` (T042) quando um tenant é
 * resolvido com sucesso a partir do subdomínio do request.
 *
 * Listeners (boot do `AppServiceProvider`):
 *  - Sentry/Pail tag de correlação (`tenant.id`, `tenant.slug`).
 *  - Rebind do cache prefix em runtime (T045).
 *
 * Não usa broadcast nem queue — é um sinal puramente local ao request.
 */
class TenantResolved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Tenant $tenant) {}
}
