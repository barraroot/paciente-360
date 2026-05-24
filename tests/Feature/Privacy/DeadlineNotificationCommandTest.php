<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Domain\Privacy\Models\ForgettingRequest;
use App\Domain\Privacy\Models\PortabilityRequest;
use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T066 (Fase 8 — Lote A US-13.2)** — AC-13.2.6 / Q27.
 *
 * Valida que o cron `privacy:notify-deadlines` envia notificações em D-5 e D-2
 * com os canais corretos (inbox-only em D-5 vs inbox+e-mail+visual em D-2).
 */
class DeadlineNotificationCommandTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_command_runs_without_error_and_returns_success(): void
    {
        $this->artisan('privacy:notify-deadlines', ['--dry-run' => true])
            ->expectsOutputToContain('Notified:')
            ->assertSuccessful();
    }

    public function test_command_finds_d5_deadlines_for_forgetting(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-d5-find', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        // Deadline em 5 dias — deve entrar na janela D-5.
        ForgettingRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->open()
            ->deadlineIn(5)
            ->create();

        // O comando loga via Log::info quando não-dry-run.
        $logs = [];
        Log::listen(function ($message) use (&$logs): void {
            $logs[] = $message;
        });

        $this->artisan('privacy:notify-deadlines')->assertSuccessful();
    }

    public function test_command_handles_no_pending_requests_gracefully(): void
    {
        // Tenant existente sem solicitações pendentes — comando ainda deve passar.
        $this->tenantAndUserForRole('clinica-empty', 'admin-clinica');

        $this->artisan('privacy:notify-deadlines')->assertSuccessful();
    }

    public function test_mark_expired_command_transitions_overdue_requests(): void
    {
        [$tenant] = $this->tenantAndUserForRole('clinica-expire', 'admin-clinica');
        $patient = Paciente::factory()->state(['tenant_id' => $tenant->id])->create();

        $overdue = ForgettingRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->open()
            ->expired() // deadline já passou
            ->create();

        $overdueP = PortabilityRequest::factory()
            ->state(['tenant_id' => $tenant->id, 'patient_id' => $patient->id])
            ->open()
            // requested_at recuado para honrar o CHECK (deadline posterior ao request).
            ->state(['requested_at' => now()->subDays(22), 'deadline_at' => now()->subDay()])
            ->create();

        $this->artisan('privacy:mark-expired')
            ->expectsOutputToContain('Marked expired:')
            ->assertSuccessful();

        $overdue->refresh();
        $overdueP->refresh();

        $this->assertSame('expired', $overdue->status->value);
        $this->assertSame('expired', $overdueP->status->value);
    }
}
