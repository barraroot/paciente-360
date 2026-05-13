<?php

namespace Tests\Feature\Agenda;

use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\CalendarSyncAccount;
use App\Models\Agenda\SlotReservation;
use App\Models\Agenda\WaitlistEntry;
use App\Models\Paciente;
use App\Models\Professional;
use Database\Seeders\AgendaPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T031** — Gate Princípio II (Multi-tenant Isolation) para entidades da Fase 5.
 *
 * 5 cenários cobrindo cada uma das principais entidades — qualquer regressão no global
 * scope `BelongsToTenant` derruba este test imediatamente:
 *
 *  1. AppointmentType
 *  2. Appointment
 *  3. SlotReservation
 *  4. WaitlistEntry
 *  5. CalendarSyncAccount
 *
 * O test cria 2 tenants distintos com dados de cada e verifica que queries default
 * (sem `withoutTenantScope`) retornam APENAS dados do tenant ativo. Para queries que
 * deliberadamente cruzam tenants (Filament Super Admin, exports cross-tenant), o
 * helper `withoutTenantScope()` deve ser chamado explicitamente — e essa chamada
 * deve estar auditada em PR.
 *
 * Cobertura também valida que UNIQUE(tenant_id, professional_id) em
 * `calendar_sync_accounts` permite mesma conta Google em 2 tenants distintos
 * (clarify nº 15) — sub-calendário é o mecanismo de isolamento real, não a conta.
 */
class CrossTenantAgendaTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesSeeder::class, AgendaPermissionsSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_appointment_type_is_isolated_per_tenant(): void
    {
        $tenantA = $this->bootstrapTenantWithRoles('tenant-a');
        $tenantB = $this->bootstrapTenantWithRoles('tenant-b');

        AppointmentType::factory()->for($tenantA)->create(['nome' => 'Consulta A', 'slug' => 'consulta-a']);
        AppointmentType::factory()->for($tenantB)->create(['nome' => 'Consulta B', 'slug' => 'consulta-b']);

        // Tenant A só enxerga o seu próprio tipo
        $this->app->instance('tenant', $tenantA);
        $typesA = AppointmentType::all();
        $this->assertCount(1, $typesA);
        $this->assertSame('Consulta A', $typesA->first()->nome);

        // Tenant B só enxerga o seu
        $this->app->instance('tenant', $tenantB);
        $typesB = AppointmentType::all();
        $this->assertCount(1, $typesB);
        $this->assertSame('Consulta B', $typesB->first()->nome);

        // Bypass explícito vê os 2 (uso EXPLICITO em painéis cross-tenant)
        $this->app->instance('tenant', $tenantA);
        $this->assertCount(2, AppointmentType::query()->withoutTenantScope()->get());
    }

    public function test_appointment_is_isolated_per_tenant(): void
    {
        $tenantA = $this->bootstrapTenantWithRoles('apt-a');
        $tenantB = $this->bootstrapTenantWithRoles('apt-b');

        $this->createAppointmentForTenant($tenantA);
        $this->createAppointmentForTenant($tenantB);

        $this->app->instance('tenant', $tenantA);
        $this->assertCount(1, Appointment::all());

        $this->app->instance('tenant', $tenantB);
        $this->assertCount(1, Appointment::all());

        $this->assertCount(2, Appointment::query()->withoutTenantScope()->get());
    }

    public function test_slot_reservation_is_isolated_per_tenant(): void
    {
        $tenantA = $this->bootstrapTenantWithRoles('sr-a');
        $tenantB = $this->bootstrapTenantWithRoles('sr-b');

        $this->app->instance('tenant', $tenantA);
        $profA = Professional::factory()->state(['tenant_id' => $tenantA->id])->create();
        $typeA = AppointmentType::factory()->for($tenantA)->create();
        SlotReservation::factory()->for($tenantA)
            ->for($profA)
            ->for($typeA, 'appointmentType')
            ->create();

        $this->app->instance('tenant', $tenantB);
        $profB = Professional::factory()->state(['tenant_id' => $tenantB->id])->create();
        $typeB = AppointmentType::factory()->for($tenantB)->create();
        SlotReservation::factory()->for($tenantB)
            ->for($profB)
            ->for($typeB, 'appointmentType')
            ->create();

        $this->app->instance('tenant', $tenantA);
        $this->assertCount(1, SlotReservation::all());

        $this->app->instance('tenant', $tenantB);
        $this->assertCount(1, SlotReservation::all());

        $this->assertCount(2, SlotReservation::query()->withoutTenantScope()->get());
    }

    public function test_waitlist_entry_is_isolated_per_tenant(): void
    {
        $tenantA = $this->bootstrapTenantWithRoles('we-a');
        $tenantB = $this->bootstrapTenantWithRoles('we-b');

        $this->app->instance('tenant', $tenantA);
        WaitlistEntry::factory()
            ->for($tenantA)
            ->for(Paciente::factory()->state(['tenant_id' => $tenantA->id]))
            ->for(Professional::factory()->state(['tenant_id' => $tenantA->id]))
            ->for(AppointmentType::factory()->for($tenantA), 'appointmentType')
            ->create();

        $this->app->instance('tenant', $tenantB);
        WaitlistEntry::factory()
            ->for($tenantB)
            ->for(Paciente::factory()->state(['tenant_id' => $tenantB->id]))
            ->for(Professional::factory()->state(['tenant_id' => $tenantB->id]))
            ->for(AppointmentType::factory()->for($tenantB), 'appointmentType')
            ->create();

        $this->app->instance('tenant', $tenantA);
        $this->assertCount(1, WaitlistEntry::all());

        $this->app->instance('tenant', $tenantB);
        $this->assertCount(1, WaitlistEntry::all());

        $this->assertCount(2, WaitlistEntry::query()->withoutTenantScope()->get());
    }

    public function test_calendar_sync_account_is_isolated_per_tenant_with_unique_per_pair(): void
    {
        $tenantA = $this->bootstrapTenantWithRoles('cs-a');
        $tenantB = $this->bootstrapTenantWithRoles('cs-b');

        $this->app->instance('tenant', $tenantA);
        $profA = Professional::factory()->state(['tenant_id' => $tenantA->id])->create();
        CalendarSyncAccount::factory()->for($tenantA)->for($profA)->create([
            'provider_user_id' => 'g-shared-account',
            'provider_email' => 'dr.silva@gmail.com',
            'google_calendar_id' => 'cal_tenant_a@group.calendar.google.com',
        ]);

        $this->app->instance('tenant', $tenantB);
        $profB = Professional::factory()->state(['tenant_id' => $tenantB->id])->create();
        // Mesma conta Google (provider_user_id) em outro tenant — clarify nº 15:
        // sub-calendário diferente garante isolamento, UNIQUE(tenant_id, professional_id)
        // permite a mesma conta Google em tenants distintos.
        CalendarSyncAccount::factory()->for($tenantB)->for($profB)->create([
            'provider_user_id' => 'g-shared-account',
            'provider_email' => 'dr.silva@gmail.com',
            'google_calendar_id' => 'cal_tenant_b@group.calendar.google.com',
        ]);

        $this->app->instance('tenant', $tenantA);
        $accountsA = CalendarSyncAccount::all();
        $this->assertCount(1, $accountsA);
        $this->assertSame('cal_tenant_a@group.calendar.google.com', $accountsA->first()->google_calendar_id);

        $this->app->instance('tenant', $tenantB);
        $accountsB = CalendarSyncAccount::all();
        $this->assertCount(1, $accountsB);
        $this->assertSame('cal_tenant_b@group.calendar.google.com', $accountsB->first()->google_calendar_id);

        $this->assertCount(2, CalendarSyncAccount::query()->withoutTenantScope()->get());
    }

    /**
     * Helper: cria appointment + dependências para um tenant.
     */
    private function createAppointmentForTenant($tenant): Appointment
    {
        $this->app->instance('tenant', $tenant);

        return Appointment::factory()
            ->for($tenant)
            ->for(Paciente::factory()->state(['tenant_id' => $tenant->id]))
            ->for(Professional::factory()->state(['tenant_id' => $tenant->id]))
            ->for(AppointmentType::factory()->for($tenant), 'appointmentType')
            ->create();
    }
}
