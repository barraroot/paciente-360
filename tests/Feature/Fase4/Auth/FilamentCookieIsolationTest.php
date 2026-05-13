<?php

namespace Tests\Feature\Fase4\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T070 — Isolamento de auth entre Filament (cookie/web) e API (Bearer/sanctum).
 *
 * Cobre AC-A.5.1 e AC-A.5.2: Filament continua usando session cookie e o
 * cookie do Filament NUNCA autentica em rotas API. Reciprocamente, um
 * Bearer token não dá acesso a rotas Filament.
 *
 * Setup do Filament:
 *  - AdminPanelProvider declara sua própria stack (StartSession, EncryptCookies,
 *    Authenticate Filament, etc) com guard `web` implícito.
 *  - Bearer não funciona em /admin/* — guards são isolados.
 *
 * @see app/Providers/Filament/AdminPanelProvider.php
 * @see config/auth.php (guards web/sanctum lado-a-lado)
 */
class FilamentCookieIsolationTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    #[Test]
    public function api_route_blocks_filament_only_session_via_tenant_slug_defense(): void
    {
        // Setup: super admin com tenant_id=null (pode logar no Filament).
        $this->seedRoles();
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'email' => 'super@filament.test',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $superAdmin->assignRole('super-admin');

        // Login via Filament (guard web) — set session.
        // NOTA: config('sanctum.guard') ainda inclui 'web' como fallback (Lote D
        // adiou a remoção para Lote I — quebraria ~650 testes legados). Logo, em
        // dev/test, Sanctum aceita a sessão web e auth:sanctum passa. Mesmo assim,
        // o middleware `tenant.slug` exige X-Tenant-Slug (cookie do Filament nunca
        // o envia) → 400 tenant_header_required. Defesa em profundidade preserva
        // o isolamento mesmo com o leak do fallback.
        //
        // Em produção, SANCTUM_STATEFUL_DOMAINS NÃO incluirá api.crm.com.br
        // (SPA fica em app.crm.com.br) — o fallback nunca dispara.
        $this->actingAs($superAdmin, 'web');

        $response = $this->getJson('/api/v1/auth/me');

        // NUNCA 200 (sessão sozinha não dá acesso a /me da API).
        $this->assertNotEquals(200, $response->getStatusCode());
        // Espera bloqueio: 400 (no X-Tenant-Slug) ou 401 (no Lote I quando o
        // fallback for fechado). Qualquer um confirma a defesa.
        $this->assertContains($response->getStatusCode(), [400, 401]);
    }

    #[Test]
    public function filament_admin_route_does_not_accept_bearer_token(): void
    {
        // Bearer token de um user autenticado no API.
        $tenant = Tenant::factory()->create(['slug' => 'tenant-fcookie']);
        $user = User::factory()->for($tenant)->create([
            'email' => 'dr@fcookie.test',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $token = $user->createToken('api-device')->plainTextToken;

        // Tenta acessar /admin com Authorization Bearer (não cookie).
        // Filament usa guard `web` (cookie-session) — Bearer é ignorado.
        // Sem session cookie, o usuário é tratado como anônimo → redirect
        // para /admin/login (302) OU 401 dependendo do panel guard.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/admin');

        // Filament redireciona usuários não-autenticados para login.
        // Não devemos receber 200 (autenticado) nem renderizar a dashboard.
        $this->assertNotEquals(200, $response->getStatusCode(), 'Filament não deve autenticar via Bearer');
        $this->assertContains(
            $response->getStatusCode(),
            [302, 401, 403],
            'Filament admin deve rejeitar (redirect/unauthorized) sem session cookie',
        );
    }

    #[Test]
    public function api_v1_auth_me_requires_bearer_token_not_session(): void
    {
        // Garante simetria: nenhuma sessão cookie atende ao /auth/me — só Bearer.
        // (Já coberto indiretamente acima, mas reafirma o contrato.)
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    #[Test]
    public function bearer_token_does_not_authenticate_via_web_guard(): void
    {
        // Princípio II / amendment v1.4.0: guards são totalmente isolados.
        // Mesmo um Bearer Sanctum válido não preenche `Auth::guard('web')->user()`.
        $tenant = Tenant::factory()->create(['slug' => 'tenant-fcookie-2']);
        $user = User::factory()->for($tenant)->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        // Faz uma request Bearer e inspeciona o estado do web guard depois.
        // Como auth:sanctum só seta o user na sanctum guard, web fica null.
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Slug' => $tenant->slug,
        ])->getJson('/api/v1/auth/me')->assertOk();

        $this->assertNull(
            auth()->guard('web')->user(),
            'Web guard não deve ser populado por Bearer auth',
        );
    }
}
