<?php

namespace Tests\Feature\Prescription;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Events\Prescription\ReceitaVencida;
use App\Jobs\Prescription\ExpireActivePrescriptionsJob;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T094 — AC-8.2.7: cron expire-active emite ReceitaVencida exatamente 1x.
 *
 * Scan: Prescription active com expires_at < today → status='superseded' + ReceitaVencida.
 * Idempotente: rodar 2x emite evento apenas 1x.
 */
class PrescriptionExpirationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_expire_active_job_marks_expired_prescriptions_as_superseded(): void
    {
        Event::fake([ReceitaVencida::class]);

        $tenant = $this->bootstrapTenantWithRoles('clinica-expire');

        // Receita expirada: issued 31d atrás, expires = issued + 30d = ontem
        $issuedAt = Carbon::today()->subDays(31);
        $expiredPrescription = Prescription::factory()
            ->forTenant($tenant)
            ->state([
                'status' => PrescriptionStatus::Active,
                'issued_at' => $issuedAt,
                'expires_at' => $issuedAt->copy()->addDays(30),
            ])
            ->create();

        // Receita ativa (expires_at no futuro — não deve ser afetada)
        $activePrescription = Prescription::factory()
            ->forTenant($tenant)
            ->state([
                'status' => PrescriptionStatus::Active,
                'expires_at' => Carbon::today()->addDays(30),
            ])
            ->create();

        (new ExpireActivePrescriptionsJob)->handle();

        $expiredPrescription->refresh();
        $this->assertSame(PrescriptionStatus::Superseded, $expiredPrescription->status,
            'Receita com expires_at < today deve ser marcada como superseded.',
        );

        $activePrescription->refresh();
        $this->assertSame(PrescriptionStatus::Active, $activePrescription->status,
            'Receita com expires_at futura não deve ser afetada.',
        );

        Event::assertDispatched(ReceitaVencida::class, fn ($e) => $e->prescriptionId === $expiredPrescription->id);
    }

    public function test_expire_active_job_is_idempotent(): void
    {
        Event::fake([ReceitaVencida::class]);

        $tenant = $this->bootstrapTenantWithRoles('clinica-expire-idempotent');

        $issuedAt2 = Carbon::today()->subDays(31);
        $expiredPrescription = Prescription::factory()
            ->forTenant($tenant)
            ->state([
                'status' => PrescriptionStatus::Active,
                'issued_at' => $issuedAt2,
                'expires_at' => $issuedAt2->copy()->addDays(30),
            ])
            ->create();

        // Roda 2x
        (new ExpireActivePrescriptionsJob)->handle();
        (new ExpireActivePrescriptionsJob)->handle();

        // Evento deve ter sido emitido apenas 1x (segunda run não acha mais receitas active)
        Event::assertDispatchedTimes(ReceitaVencida::class, 1);
    }

    public function test_cancelled_prescriptions_are_not_expired(): void
    {
        Event::fake([ReceitaVencida::class]);

        $tenant = $this->bootstrapTenantWithRoles('clinica-no-expire-cancelled');

        // Receita cancelada com expires_at no passado — NÃO deve ser alterada pelo job
        $issuedCanc = Carbon::today()->subDays(35);
        // Usa factory cancelled() que garante FKs válidas
        $cancelledPrescription = Prescription::factory()
            ->cancelled()
            ->forTenant($tenant)
            ->create();

        (new ExpireActivePrescriptionsJob)->handle();

        $cancelledPrescription->refresh();
        $this->assertSame(PrescriptionStatus::Cancelled, $cancelledPrescription->status,
            'Receita cancelada não deve ser alterada pelo job de expiração.',
        );

        Event::assertNotDispatched(ReceitaVencida::class);
    }

    public function test_expire_active_emits_event_with_correct_data(): void
    {
        Event::fake([ReceitaVencida::class]);

        $tenant = $this->bootstrapTenantWithRoles('clinica-expire-data');

        $issuedAt3 = Carbon::today()->subDays(35);
        $expiredPrescription = Prescription::factory()
            ->forTenant($tenant)
            ->state([
                'status' => PrescriptionStatus::Active,
                'issued_at' => $issuedAt3,
                'expires_at' => $issuedAt3->copy()->addDays(30),
            ])
            ->create();

        (new ExpireActivePrescriptionsJob)->handle();

        Event::assertDispatched(ReceitaVencida::class, function ($event) use ($expiredPrescription) {
            return $event->prescriptionId === $expiredPrescription->id
                && $event->patientId === $expiredPrescription->patient_id;
        });
    }
}
