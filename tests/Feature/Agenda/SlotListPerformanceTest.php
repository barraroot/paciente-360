<?php

namespace Tests\Feature\Agenda;

use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\ProfessionalSchedule;
use App\Models\Professional;
use Database\Seeders\AgendaPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T178** — SC-009 / RNF-001: GET /agenda/slots-disponiveis p95 ≤ 300ms
 * para janela de 7 dias com 1 profissional + agenda densa.
 *
 * **Débito técnico**: target real é p95 com 50 profissionais; este test mede
 * 1 profissional com janela 7d como sanity check. Para validar p95 real é
 * necessário test runner com paralelismo + repetição (vegeta/k6 — fora do MVP).
 *
 * Threshold relaxado para 600ms aqui (CI/Sail tem overhead extra vs prod).
 */
class SlotListPerformanceTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    public function test_slots_endpoint_returns_under_threshold_for_7d_window(): void
    {
        $this->seed([RolesSeeder::class, AgendaPermissionsSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        [$tenant, $admin] = $this->tenantAndUserForRole('clinica-perf', 'admin-clinica');

        $professional = Professional::factory()->state(['tenant_id' => $tenant->id])->create();
        $type = AppointmentType::factory()->for($tenant)->create([
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
        ]);

        // Agenda densa: 7 dias × 10h trabalho = 140 slots
        for ($dow = 1; $dow <= 7; $dow++) {
            ProfessionalSchedule::create([
                'tenant_id' => $tenant->id,
                'professional_id' => $professional->id,
                'day_of_week' => $dow,
                'blocks' => [['start' => '08:00', 'end' => '18:00']],
                'effective_from' => Carbon::today()->toDateString(),
                'created_by_user_id' => $admin->id,
            ]);
        }

        $start = microtime(true);
        $response = $this->getJson(
            '/api/v1/agenda/slots-disponiveis?'.http_build_query([
                'professional_id' => $professional->id,
                'appointment_type_id' => $type->id,
                'from' => Carbon::tomorrow()->toIso8601String(),
                'to' => Carbon::tomorrow()->addDays(7)->toIso8601String(),
            ]),
            ['X-Tenant-Slug' => $tenant->slug],
        );
        $duration = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')), 'Deve retornar slots');

        // Threshold relaxado para CI/Sail. Target prod: p95 ≤ 300ms (SC-009).
        $this->assertLessThan(
            600.0,
            $duration,
            "GET /slots-disponiveis demorou {$duration}ms (limite CI: 600ms; target prod: 300ms p95)",
        );
    }
}
