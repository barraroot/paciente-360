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
 * T037 — Logout revoga apenas o token corrente (AC-A.1.3).
 *
 * Valida que POST /api/v1/auth/logout deleta apenas o token Bearer
 * usado na request, preservando outros tokens do mesmo usuário.
 */
class LogoutCurrentTokenTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'clinica-logout']);
        $this->user = User::factory()->for($this->tenant)->create([
            'email' => 'dr@clinica-logout.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $token1 = $this->user->createToken('device-1');
        $token2 = $this->user->createToken('device-2');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token1->plainTextToken,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->postJson('/api/v1/auth/logout')
            ->assertStatus(204);

        // Token1 deve ter sido deletado
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token1->accessToken->id,
        ]);

        // Token2 ainda deve existir
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token2->accessToken->id,
        ]);
    }

    public function test_logout_other_tokens_remain_active(): void
    {
        $token1 = $this->user->createToken('device-1');
        $token2 = $this->user->createToken('device-2');
        $token3 = $this->user->createToken('device-3');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token1->plainTextToken,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->postJson('/api/v1/auth/logout')
            ->assertStatus(204);

        // Os outros dois tokens devem permanecer
        $remaining = $this->user->tokens()->count();
        $this->assertEquals(2, $remaining);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token2->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token3->accessToken->id]);
    }

    public function test_logout_fires_token_revogado_with_motivo_manual(): void
    {
        Event::fake([TokenRevogado::class]);

        $token = $this->user->createToken('device-logout');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->postJson('/api/v1/auth/logout')
            ->assertStatus(204);

        Event::assertDispatched(TokenRevogado::class, function (TokenRevogado $event): bool {
            return $event->motivo === MotivoRevogacaoToken::Manual
                && $event->userId === $this->user->id;
        });
    }

    public function test_logout_first_call_returns_204_second_call_returns_401(): void
    {
        $token = $this->user->createToken('device-double-logout');
        $plainText = $token->plainTextToken;

        // Primeiro logout — deve retornar 204
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$plainText,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->postJson('/api/v1/auth/logout');

        $response1->assertStatus(204);

        // Confirmar que o token foi deletado do DB
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);

        // Flush do guard autenticação para forçar re-validação do token na próxima request.
        // Sem isso, o guard mantém o usuário cacheado no mesmo processo de teste.
        app('auth')->forgetGuards();

        // Segundo logout com o mesmo token (já deletado) — Sanctum rejeita com 401.
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$plainText,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->postJson('/api/v1/auth/logout');

        $response2->assertStatus(401);
    }
}
