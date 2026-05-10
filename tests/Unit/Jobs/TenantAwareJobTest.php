<?php

namespace Tests\Unit\Jobs;

use App\Jobs\TenantAwareJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Verifica o contrato do `TenantAwareJob`:
 *  - dispatch sem tenant bound -> exception;
 *  - dispatch com tenant A: handle() rehidrata `app('tenant')` para A;
 *  - troca de contexto entre 2 tenants é isolada.
 *
 * Em testes, `QUEUE_CONNECTION=sync` (phpunit.xml) — o `handle` roda
 * inline, mas o dispatch ainda passa pelo construtor (que captura o
 * tenantId).
 */
class TenantAwareJobTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    public function test_dispatch_without_tenant_bound_throws(): void
    {
        $this->app->forgetInstance('tenant');

        $this->expectException(RuntimeException::class);

        new ProbeTenantJob;
    }

    public function test_handle_rehydrates_tenant_in_container(): void
    {
        $tenant = $this->createTenant();
        $this->app->instance('tenant', $tenant);

        $job = new ProbeTenantJob;

        // Limpa o container ANTES de chamar handle() — simulando
        // execução em outro processo (worker).
        $this->app->forgetInstance('tenant');
        ProbeTenantJob::$capturedTenantId = null;

        $job->handle();

        $this->assertSame($tenant->id, ProbeTenantJob::$capturedTenantId);
        $this->assertTrue($this->app->bound('tenant'));
        $this->assertSame($tenant->id, app('tenant')->id);
    }

    public function test_two_tenants_dispatch_independent_jobs(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $this->app->instance('tenant', $tenantA);
        $jobA = new ProbeTenantJob;

        $this->app->instance('tenant', $tenantB);
        $jobB = new ProbeTenantJob;

        $this->assertSame($tenantA->id, $jobA->tenantId);
        $this->assertSame($tenantB->id, $jobB->tenantId);
    }

    public function test_dispatched_job_runs_with_correct_tenant_via_bus(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        Bus::fake();

        $this->app->instance('tenant', $tenantA);
        ProbeTenantJob::dispatch();

        $this->app->instance('tenant', $tenantB);
        ProbeTenantJob::dispatch();

        Bus::assertDispatchedTimes(ProbeTenantJob::class, 2);
        Bus::assertDispatched(
            ProbeTenantJob::class,
            fn (ProbeTenantJob $job): bool => $job->tenantId === $tenantA->id
        );
        Bus::assertDispatched(
            ProbeTenantJob::class,
            fn (ProbeTenantJob $job): bool => $job->tenantId === $tenantB->id
        );
    }

    public function test_handle_loads_tenant_even_if_soft_deleted(): void
    {
        $tenant = $this->createTenant();
        $this->app->instance('tenant', $tenant);

        $job = new ProbeTenantJob;

        // Soft-delete NÃO deve impedir rehidratação (jobs em fila
        // podem rodar depois de soft-delete por race; preferimos rodar
        // a perder a operação).
        $tenant->delete();

        $this->app->forgetInstance('tenant');
        $job->handle();

        $this->assertSame($tenant->id, app('tenant')->id);
    }
}

/**
 * Job de teste: captura o `tenant.id` resolvido durante `run()` em
 * variável estática para inspeção pelo teste.
 */
class ProbeTenantJob extends TenantAwareJob
{
    public static ?int $capturedTenantId = null;

    protected function run(): void
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        self::$capturedTenantId = is_object($tenant) ? (int) $tenant->id : null;
    }
}
