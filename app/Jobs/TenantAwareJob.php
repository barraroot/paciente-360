<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Classe abstrata base para jobs que rodam dentro do contexto de um
 * tenant (Princípio II — tenant isolation by default; Princípio V —
 * observabilidade).
 *
 * Estratégia:
 *  - No constructor (executado no processo do **publisher**), captura
 *    `app('tenant')->id` e armazena em `$tenantId`. Throws se o
 *    container não tiver tenant resolvido — evita publicar job sem
 *    contexto, o que seria vazamento silencioso.
 *  - O método `handle()` é `final` — antes de invocar `run()` (a
 *    lógica concreta da subclasse), rehidrata `app('tenant')` com o
 *    Tenant carregado a partir do `$tenantId` armazenado. Isso roda no
 *    **worker** (Horizon), garantindo que o `TenantScope` global e o
 *    cache prefix funcionem corretamente sem importar em que processo
 *    o job execute.
 *
 * Subclasses implementam `protected function run(): void` — elas
 * **não** sobrescrevem `handle()`.
 *
 * Exemplo:
 *
 *     class SendOnboardingEmailJob extends TenantAwareJob
 *     {
 *         public function __construct(public int $userId)
 *         {
 *             parent::__construct();
 *         }
 *
 *         protected function run(): void
 *         {
 *             // app('tenant') já está bound aqui.
 *             $user = User::findOrFail($this->userId);
 *             // ...
 *         }
 *     }
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Identifica o tenant ao qual este job pertence. Capturado no
     * publisher e re-hidratado no worker. Não pode ser nulo — jobs
     * precisam de tenant; tarefas cross-tenant devem usar Command CLI
     * ou um job com `withoutGlobalScope` explícito.
     */
    public int $tenantId;

    public function __construct()
    {
        if (! app()->bound('tenant')) {
            throw new RuntimeException(
                'TenantAwareJob: cannot dispatch without `app(\'tenant\')` bound. '
                .'Resolve o tenant antes (middleware ResolveTenant ou '
                .'app()->instance(\'tenant\', $tenant) explícito).'
            );
        }

        $tenant = app('tenant');

        if (! is_object($tenant) || ! isset($tenant->id)) {
            throw new RuntimeException('TenantAwareJob: `app(\'tenant\')` not a valid Tenant instance.');
        }

        $this->tenantId = (int) $tenant->id;
    }

    /**
     * Hook do Laravel queue worker. Rehidrata `app('tenant')` antes da
     * lógica concreta da subclasse rodar.
     */
    final public function handle(): void
    {
        $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($this->tenantId);

        app()->instance('tenant', $tenant);

        $this->run();
    }

    /**
     * Lógica concreta do job. Roda já com `app('tenant')` rehidratado.
     */
    abstract protected function run(): void;
}
