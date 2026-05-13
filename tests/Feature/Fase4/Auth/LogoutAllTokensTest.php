<?php

namespace Tests\Feature\Fase4\Auth;

use App\Domain\Auth\Enums\MotivoRevogacaoToken;
use App\Domain\Auth\Events\TokenRevogado;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * T038 — Logout-all revoga todos os tokens do usuário.
 *
 * Valida que POST /api/v1/auth/logout-all revoga todos os tokens do
 * usuário autenticado sem afetar tokens de outros usuários no mesmo tenant.
 */
class LogoutAllTokensTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'clinica-logout-all']);
        $this->user = User::factory()->for($this->tenant)->create([
            'email' => 'dr@clinica-logout-all.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $this->user2 = User::factory()->for($this->tenant)->create([
            'email' => 'dr2@clinica-logout-all.com',
            'password' => Hash::make('secret456'),
            'status' => 'active',
        ]);
    }

    public function test_logout_all_revokes_every_token_of_user(): void
    {
        $token1 = $this->user->createToken('device-1');
        $token2 = $this->user->createToken('device-2');
        $token3 = $this->user->createToken('device-3');

        // User2 tem seus próprios tokens (não devem ser afetados)
        $this->user2->createToken('user2-device-1');
        $this->user2->createToken('user2-device-2');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token1->plainTextToken,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->postJson('/api/v1/auth/logout-all')
            ->assertStatus(204);

        // Todos os tokens do user devem ter sido deletados
        $this->assertEquals(0, $this->user->tokens()->count());

        // Tokens do user2 devem permanecer intactos
        $this->assertEquals(2, $this->user2->tokens()->count());
    }

    public function test_logout_all_fires_token_revogado_per_token_with_motivo_logout_all(): void
    {
        Event::fake([TokenRevogado::class]);

        $this->user->createToken('device-1');
        $this->user->createToken('device-2');
        $this->user->createToken('device-3');

        $activeToken = $this->user->createToken('active-device');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$activeToken->plainTextToken,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->postJson('/api/v1/auth/logout-all')
            ->assertStatus(204);

        // Deve ter disparado 4 eventos (3 + o ativo)
        Event::assertDispatched(TokenRevogado::class, 4);

        Event::assertDispatched(TokenRevogado::class, function (TokenRevogado $event): bool {
            return $event->motivo === MotivoRevogacaoToken::LogoutAll
                && $event->userId === $this->user->id;
        });
    }
}
