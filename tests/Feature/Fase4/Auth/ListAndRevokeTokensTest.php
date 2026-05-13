<?php

namespace Tests\Feature\Fase4\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * T039 — Listagem e revogação individual de tokens (AC-A.1.7).
 *
 * Valida GET /api/v1/auth/tokens (lista com metadados) e
 * DELETE /api/v1/auth/tokens/{id} (revoga por ID com enforcement de ownership).
 */
class ListAndRevokeTokensTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'clinica-tokens']);
        $this->user = User::factory()->for($this->tenant)->create([
            'email' => 'dr@clinica-tokens.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $this->user2 = User::factory()->for($this->tenant)->create([
            'email' => 'dr2@clinica-tokens.com',
            'password' => Hash::make('secret456'),
            'status' => 'active',
        ]);
    }

    public function test_list_returns_active_tokens_with_metadata(): void
    {
        $this->user->createToken('device-mobile');
        $activeToken = $this->user->createToken('device-web');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$activeToken->plainTextToken,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->getJson('/api/v1/auth/tokens');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'token_id_prefix',
                        'abilities',
                        'last_used_at',
                        'expires_at',
                        'created_at',
                        'is_current',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_delete_token_by_id_revokes(): void
    {
        $tokenToDelete = $this->user->createToken('device-to-delete');
        $activeToken = $this->user->createToken('active-device');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$activeToken->plainTextToken,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->deleteJson('/api/v1/auth/tokens/'.$tokenToDelete->accessToken->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenToDelete->accessToken->id,
        ]);
    }

    public function test_delete_token_of_other_user_returns_403(): void
    {
        $user2Token = $this->user2->createToken('user2-device');
        $user1Token = $this->user->createToken('user1-device');

        // User1 tenta deletar token do User2 → deve retornar 404 ou 403
        // (ownership check via user->tokens()->find() — não encontra = 404)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user1Token->plainTextToken,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->deleteJson('/api/v1/auth/tokens/'.$user2Token->accessToken->id);

        // Retorna 404 (não encontrado para este user — ownership enforced)
        // pois user->tokens()->find(id) retorna null para tokens de outros users
        $response->assertStatus(404);

        // Token do user2 deve permanecer intacto
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $user2Token->accessToken->id,
        ]);
    }

    public function test_is_current_flag_marks_request_token(): void
    {
        $otherToken = $this->user->createToken('other-device');
        $currentToken = $this->user->createToken('current-device');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$currentToken->plainTextToken,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->getJson('/api/v1/auth/tokens');

        $response->assertStatus(200);
        $data = $response->json('data');

        $currentEntry = collect($data)->firstWhere('id', $currentToken->accessToken->id);
        $otherEntry = collect($data)->firstWhere('id', $otherToken->accessToken->id);

        $this->assertTrue($currentEntry['is_current'], 'Token atual deve ter is_current=true');
        $this->assertFalse($otherEntry['is_current'], 'Outro token deve ter is_current=false');
    }
}
