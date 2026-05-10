<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Users\PurgeExpiredInvitationsJob;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests para `PurgeExpiredInvitationsJob` (T245).
 *
 * Verifica que apenas convites expirados há > 30 dias (sem accepted_at)
 * são removidos. Aceites e expirados recentes são preservados.
 */
class PurgeExpiredInvitationsJobTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_purges_only_invitations_expired_over_30_days(): void
    {
        $tenant = Tenant::factory()->create();
        $inviter = User::factory()->forTenant($tenant)->create();

        // Expirado há 31 dias → deve ser deletado.
        $oldExpired = Invitation::factory()
            ->forTenant($tenant, $inviter)
            ->create([
                'expires_at' => now()->subDays(31),
                'accepted_at' => null,
                'status' => 'pending',
            ]);

        // Expirado há 10 dias → deve ser preservado.
        $recentExpired = Invitation::factory()
            ->forTenant($tenant, $inviter)
            ->create([
                'expires_at' => now()->subDays(10),
                'accepted_at' => null,
                'status' => 'pending',
            ]);

        // Aceito → deve ser preservado (accepted_at não é null).
        $accepted = Invitation::factory()
            ->forTenant($tenant, $inviter)
            ->accepted()
            ->create([
                'expires_at' => now()->subDays(40),
            ]);

        (new PurgeExpiredInvitationsJob)->handle();

        $this->assertDatabaseMissing('invitations', ['id' => $oldExpired->id]);
        $this->assertDatabaseHas('invitations', ['id' => $recentExpired->id]);
        $this->assertDatabaseHas('invitations', ['id' => $accepted->id]);
    }
}
