<?php

namespace Tests\Feature\Fase4\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * T036 — Endpoint /me com Bearer token (AC-A.1.4).
 *
 * Valida que GET /api/v1/auth/me retorna user, tenant e metadados do token
 * quando autenticado via Bearer, e rejeita adequadamente tentativas inválidas.
 */
class MeEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'clinica-me']);
        $this->user = User::factory()->for($this->tenant)->create([
            'email' => 'dr@clinica-me.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
    }

    public function test_me_returns_user_and_tenant_when_bearer_valid(): void
    {
        $token = $this->user->createToken('test-device')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'tenant' => ['id', 'slug'],
                'token' => ['id', 'name', 'abilities', 'expires_at'],
            ])
            ->assertJsonFragment(['id' => $this->user->id])
            ->assertJsonFragment(['slug' => $this->tenant->slug]);
    }

    public function test_me_rejects_missing_bearer_401(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_me_rejects_expired_token_401(): void
    {
        $token = $this->user->createToken('expired-device');

        // Manipula expires_at para o passado diretamente no DB
        $token->accessToken->forceFill([
            'expires_at' => now()->subMinutes(1),
        ])->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_me_rejects_revoked_token_401(): void
    {
        $token = $this->user->createToken('revoked-device');
        $plainText = $token->plainTextToken;

        // Revoga o token deletando do DB
        $token->accessToken->delete();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$plainText,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_me_includes_token_metadata_in_response(): void
    {
        $newToken = $this->user->createToken('mobile-app', ['*'], now()->addDays(30));

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$newToken->plainTextToken,
            'X-Tenant-Slug' => $this->tenant->slug,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $tokenMeta = $response->json('token');

        $this->assertNotNull($tokenMeta['id']);
        $this->assertEquals('mobile-app', $tokenMeta['name']);
        $this->assertEquals(['*'], $tokenMeta['abilities']);
        $this->assertNotNull($tokenMeta['expires_at']);
    }
}
