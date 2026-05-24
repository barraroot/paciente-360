<?php

namespace Tests\Feature\Agenda;

use App\Events\Agenda\CalendarioExternoSincronizado;
use App\Jobs\Agenda\DetectGoogleSyncFailureJob;
use App\Models\Agenda\CalendarSyncAccount;
use App\Models\Professional;
use App\Services\Agenda\Calendar\GoogleCalendarApiClient;
use App\Services\Agenda\Calendar\GoogleCalendarOAuthService;
use Database\Seeders\AgendaPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * Cobre o débito do BACKLOG (Fase 5, item 4): `DetectGoogleSyncFailureJob`
 * estava implementado mas sem teste isolado do comportamento de cache
 * (2 falhas em 1min → disconnected).
 *
 * Estratégia (R5): tenta refresh; se falhar 2x na janela de 1min, marca a conta
 * como `disconnected` e dispara `CalendarioExternoSincronizado`.
 *
 * Notas de teste:
 *  - `GoogleCalendarOAuthService` é `final` → não é mockável; resolvido real do
 *    container. O ponto de controle é o `GoogleCalendarApiClient` (não-final).
 *  - Caminho de falha: conta SEM `encrypted_refresh_token` faz `tryRefresh()`
 *    retornar `false` deterministicamente, sem tocar a API.
 */
class DetectGoogleSyncFailureTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesSeeder::class, AgendaPermissionsSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeAccount(array $overrides = []): CalendarSyncAccount
    {
        $tenant = $this->bootstrapTenantWithRoles('gsync-fail');
        $professional = Professional::factory()->state(['tenant_id' => $tenant->id])->create();

        return CalendarSyncAccount::factory()
            ->state(array_merge(['tenant_id' => $tenant->id, 'professional_id' => $professional->id], $overrides))
            ->create();
    }

    private function dispatchJob(int $accountId): void
    {
        (new DetectGoogleSyncFailureJob($accountId))->handle(app(GoogleCalendarOAuthService::class));
    }

    public function test_successful_refresh_keeps_account_connected_without_event(): void
    {
        Event::fake([CalendarioExternoSincronizado::class]);
        $account = $this->makeAccount(['status' => 'connected']);

        // Client mockado → refresh bem-sucedido → tryRefresh() === true.
        $this->mock(GoogleCalendarApiClient::class, function ($mock): void {
            $mock->shouldReceive('refreshToken')->andReturn([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
            ]);
        });

        $this->dispatchJob($account->id);

        $this->assertSame('connected', $account->fresh()->status);
        $this->assertSame(0, (int) Cache::get("agenda:google:auth_failures:{$account->id}", 0));
        Event::assertNotDispatched(CalendarioExternoSincronizado::class);
    }

    public function test_single_failure_increments_counter_without_disconnecting(): void
    {
        Event::fake([CalendarioExternoSincronizado::class]);
        // Sem refresh token → tryRefresh() === false.
        $account = $this->makeAccount(['status' => 'connected', 'encrypted_refresh_token' => null]);

        $this->dispatchJob($account->id);

        $this->assertSame('connected', $account->fresh()->status);
        $this->assertSame(1, (int) Cache::get("agenda:google:auth_failures:{$account->id}"));
        Event::assertNotDispatched(CalendarioExternoSincronizado::class);
    }

    public function test_second_failure_within_window_disconnects_and_dispatches_event(): void
    {
        Event::fake([CalendarioExternoSincronizado::class]);
        $account = $this->makeAccount(['status' => 'connected', 'encrypted_refresh_token' => null]);
        Cache::put("agenda:google:auth_failures:{$account->id}", 1, 60);

        $this->dispatchJob($account->id);

        $fresh = $account->fresh();
        $this->assertSame('disconnected', $fresh->status);
        $this->assertNotNull($fresh->last_disconnect_at);
        // Contador é limpo após declarar disconnected.
        $this->assertNull(Cache::get("agenda:google:auth_failures:{$account->id}"));
        Event::assertDispatched(
            CalendarioExternoSincronizado::class,
            fn (CalendarioExternoSincronizado $e): bool => $e->account->id === $account->id,
        );
    }

    public function test_already_disconnected_account_is_skipped(): void
    {
        Event::fake([CalendarioExternoSincronizado::class]);
        $account = $this->makeAccount(['status' => 'disconnected', 'last_disconnect_at' => now()]);

        $this->dispatchJob($account->id);

        $this->assertSame('disconnected', $account->fresh()->status);
        $this->assertSame(0, (int) Cache::get("agenda:google:auth_failures:{$account->id}", 0));
        Event::assertNotDispatched(CalendarioExternoSincronizado::class);
    }
}
