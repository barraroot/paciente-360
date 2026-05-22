<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domain\SuperAdmin\Events\AnomaliaDetectada;
use App\Domain\SuperAdmin\Models\AnomalyCategory;
use App\Domain\SuperAdmin\Models\AnomalyDetected;
use App\Domain\SuperAdmin\Models\AnomalySeverity;
use App\Domain\SuperAdmin\Services\AnomalyDetectorService;
use App\Models\Tenant;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T139 (Fase 8 — Lote B US-12.3)** — AC-12.3.4 — Q22.
 *
 * Valida:
 *   1. `detectPaymentOverdue()` cria anomalias para tenants overdue > 30d.
 *   2. Cooldown 30min impede duplo-disparo da mesma categoria+tenant.
 *   3. Severidade escala: ≥60d => Critical; senão Warning.
 *   4. notified_via correto por severity (critical → inbox+email).
 *   5. Evento AnomaliaDetectada disparado com payload completo.
 */
class AnomalyDetectionTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_payment_overdue_creates_warning_anomaly(): void
    {
        Event::fake([AnomaliaDetectada::class]);

        $tenant = Tenant::factory()->create([
            'status' => 'overdue',
            'overdue_since' => Carbon::now()->subDays(45),
        ]);

        /** @var AnomalyDetectorService $svc */
        $svc = app(AnomalyDetectorService::class);
        $detected = $svc->detectPaymentOverdue();

        $this->assertCount(1, $detected);
        $this->assertSame(AnomalyCategory::PaymentOverdue, $detected[0]->categoria);
        $this->assertSame(AnomalySeverity::Warning, $detected[0]->severity);
        $this->assertSame($tenant->id, $detected[0]->tenant_id);
        $this->assertContains('inbox', $detected[0]->notified_via);
        $this->assertNotContains('email', $detected[0]->notified_via); // warning não envia e-mail

        Event::assertDispatched(AnomaliaDetectada::class);
    }

    public function test_payment_overdue_60_days_escalates_to_critical(): void
    {
        Tenant::factory()->create([
            'status' => 'overdue',
            'overdue_since' => Carbon::now()->subDays(75),
        ]);

        /** @var AnomalyDetectorService $svc */
        $svc = app(AnomalyDetectorService::class);
        $detected = $svc->detectPaymentOverdue();

        $this->assertCount(1, $detected);
        $this->assertSame(AnomalySeverity::Critical, $detected[0]->severity);
        $this->assertContains('inbox', $detected[0]->notified_via);
        $this->assertContains('email', $detected[0]->notified_via);
    }

    public function test_cooldown_prevents_duplicate_detection(): void
    {
        Tenant::factory()->create([
            'status' => 'overdue',
            'overdue_since' => Carbon::now()->subDays(45),
        ]);

        /** @var AnomalyDetectorService $svc */
        $svc = app(AnomalyDetectorService::class);

        $first = $svc->detectPaymentOverdue();
        $second = $svc->detectPaymentOverdue(); // mesma rodada — cooldown bloqueia

        $this->assertCount(1, $first);
        $this->assertCount(0, $second, 'Cooldown deve bloquear segundo disparo da mesma categoria+tenant.');
        $this->assertSame(1, AnomalyDetected::query()->count());
    }

    public function test_threshold_breached_payload_is_complete(): void
    {
        Tenant::factory()->create([
            'status' => 'overdue',
            'overdue_since' => Carbon::now()->subDays(45),
        ]);

        $detected = app(AnomalyDetectorService::class)->detectPaymentOverdue();
        $anomaly = $detected[0];

        $this->assertArrayHasKey('metric', $anomaly->threshold_breached);
        $this->assertArrayHasKey('threshold', $anomaly->threshold_breached);
        $this->assertArrayHasKey('observed_value', $anomaly->threshold_breached);

        $this->assertSame('payment_overdue_days', $anomaly->threshold_breached['metric']);
        $this->assertSame(30, $anomaly->threshold_breached['threshold']);
        $this->assertGreaterThanOrEqual(45, $anomaly->threshold_breached['observed_value']);
    }

    public function test_no_overdue_tenants_returns_empty(): void
    {
        // Tenant active normal — não dispara nada.
        Tenant::factory()->create(['status' => 'active']);

        $detected = app(AnomalyDetectorService::class)->detectPaymentOverdue();

        $this->assertEmpty($detected);
        $this->assertSame(0, AnomalyDetected::query()->count());
    }

    public function test_detect_all_runs_without_errors_in_empty_database(): void
    {
        // Garante que detectAll() não quebra com tabelas vazias / opcionais ausentes.
        $detected = app(AnomalyDetectorService::class)->detectAll();

        $this->assertIsArray($detected);
        // Pode haver 0 anomalias — comportamento esperado em ambiente limpo.
    }
}
