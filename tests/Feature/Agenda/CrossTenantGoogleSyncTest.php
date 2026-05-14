<?php

namespace Tests\Feature\Agenda;

use App\Models\Agenda\CalendarSyncAccount;
use App\Models\Agenda\ExternalCalendarBusy;
use App\Models\Professional;
use Database\Seeders\AgendaPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **T140** — GATE clarify nº 15 / AC-6.7.11.
 *
 * Mesmo profissional conectado em 2 tenants distintos com a MESMA conta Google
 * (provider_user_id idêntico) deve gerar 2 CalendarSyncAccount distintos com
 * sub-calendários DIFERENTES. Eventos de tenant A são invisíveis ao polling
 * do tenant B porque cada um aponta para um google_calendar_id distinto.
 */
class CrossTenantGoogleSyncTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesSeeder::class, AgendaPermissionsSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_same_google_account_in_two_tenants_uses_distinct_sub_calendars(): void
    {
        $tenantA = $this->bootstrapTenantWithRoles('cs-leak-a');
        $tenantB = $this->bootstrapTenantWithRoles('cs-leak-b');

        $profA = Professional::factory()->state(['tenant_id' => $tenantA->id])->create();
        $profB = Professional::factory()->state(['tenant_id' => $tenantB->id])->create();

        $sharedGoogleSub = 'g-shared-account-id';

        $accountA = CalendarSyncAccount::factory()
            ->state(['tenant_id' => $tenantA->id, 'professional_id' => $profA->id])
            ->connected()
            ->create([
                'provider_user_id' => $sharedGoogleSub,
                'provider_email' => 'dr.silva@gmail.com',
                'google_calendar_id' => 'cal_tenant_a@group.calendar.google.com',
            ]);

        $accountB = CalendarSyncAccount::factory()
            ->state(['tenant_id' => $tenantB->id, 'professional_id' => $profB->id])
            ->connected()
            ->create([
                'provider_user_id' => $sharedGoogleSub,
                'provider_email' => 'dr.silva@gmail.com',
                'google_calendar_id' => 'cal_tenant_b@group.calendar.google.com',
            ]);

        // Sub-calendar IDs DEVEM ser diferentes
        $this->assertNotSame($accountA->google_calendar_id, $accountB->google_calendar_id);

        // Provider account compartilhada (mesma pessoa Google)
        $this->assertSame($sharedGoogleSub, $accountA->provider_user_id);
        $this->assertSame($sharedGoogleSub, $accountB->provider_user_id);

        // Cria ExternalCalendarBusy no calendário de A (simulando push notification A)
        app()->instance('tenant', $tenantA);
        ExternalCalendarBusy::create([
            'tenant_id' => $tenantA->id,
            'professional_id' => $profA->id,
            'calendar_sync_account_id' => $accountA->id,
            'external_event_id' => 'evt_tenant_a_only',
            'starts_at' => Carbon::tomorrow()->setTime(10, 0),
            'ends_at' => Carbon::tomorrow()->setTime(11, 0),
            'provider' => 'google',
            'synced_at' => now(),
        ]);

        // Tenant B NÃO enxerga o ExternalCalendarBusy do tenant A
        app()->instance('tenant', $tenantB);
        $busyB = ExternalCalendarBusy::all();
        $this->assertCount(0, $busyB, 'Tenant B NÃO pode enxergar busy do tenant A (clarify nº 15)');

        // Tenant A enxerga o seu
        app()->instance('tenant', $tenantA);
        $busyA = ExternalCalendarBusy::all();
        $this->assertCount(1, $busyA);
        $this->assertSame('evt_tenant_a_only', $busyA->first()->external_event_id);
    }

    public function test_unique_constraint_blocks_duplicate_sync_account_per_tenant_prof(): void
    {
        $tenant = $this->bootstrapTenantWithRoles('cs-unique');
        $prof = Professional::factory()->state(['tenant_id' => $tenant->id])->create();

        CalendarSyncAccount::factory()
            ->state(['tenant_id' => $tenant->id, 'professional_id' => $prof->id])
            ->create();

        $this->expectException(QueryException::class);

        CalendarSyncAccount::factory()
            ->state(['tenant_id' => $tenant->id, 'professional_id' => $prof->id])
            ->create();
    }
}
