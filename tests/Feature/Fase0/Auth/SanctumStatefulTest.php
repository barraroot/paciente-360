<?php

namespace Tests\Feature\Fase0\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Testes do Sanctum SPA stateful (T050 — Princípio VII: Segurança).
 *
 * Verifica que:
 *  1. O endpoint /sanctum/csrf-cookie seta o cookie XSRF-TOKEN.
 *  2. `Sanctum::actingAs` autentica corretamente um usuário de tenant
 *     e a rota `/_me` retorna os campos esperados.
 *
 * @see specs/001-fundacao-multitenant/spec.md — US-2.1 (login SPA)
 */
class SanctumStatefulTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    /**
     * GET /sanctum/csrf-cookie deve responder 204 e setar o cookie XSRF-TOKEN.
     */
    public function test_csrf_cookie_endpoint_sets_xsrf_cookie(): void
    {
        $response = $this->withHeaders([
            'Host' => 'clinica-x.lvh.me',
            'Origin' => 'http://clinica-x.lvh.me',
        ])->get('/sanctum/csrf-cookie');

        $response->assertStatus(204);

        $this->assertNotEmpty(
            $response->headers->getCookies(),
            'Esperado ao menos um cookie na resposta do csrf-cookie endpoint.'
        );

        $cookieNames = array_map(
            fn ($c) => $c->getName(),
            $response->headers->getCookies()
        );

        $this->assertContains(
            'XSRF-TOKEN',
            $cookieNames,
            'O cookie XSRF-TOKEN deve ser enviado pelo endpoint /sanctum/csrf-cookie.'
        );
    }

    /**
     * `Sanctum::actingAs` deve autenticar um usuário de tenant e a rota
     * autenticada `/_me` deve retornar id, email e tenant_id.
     */
    public function test_sanctum_acting_as_works_with_user_resource(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-sanctum']);
        $user = $this->createUserForTenant($tenant);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/_me');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $user->id]);
        $response->assertJsonFragment(['email' => $user->email]);
        $response->assertJsonFragment(['tenant_id' => $user->tenant_id]);
    }
}
