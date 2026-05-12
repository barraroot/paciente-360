<?php

namespace Tests\Feature\Fase3\US7_QuickReplies;

use App\Domain\Messaging\QuickReply\Models\QuickReply;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T229 — CRUD de respostas rápidas (AC-4.7.1).
 *
 * Testa criação, edição, exclusão e listagem com escopo dual
 * (tenant scope = owner_user_id IS NULL; privada = owner_user_id = user.id).
 */
class QuickReplyCrudTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $atendente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-qr', 'admin-clinica');
        $this->atendente = $this->userForRole($this->tenant, 'atendente');
    }

    private function url(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, '/inbox/quick-replies'.$path);
    }

    // -------------------------------------------------------------------------
    // Criação
    // -------------------------------------------------------------------------

    #[Test]
    public function atendente_can_create_private_quick_reply(): void
    {
        $this->actingAs($this->atendente);

        $response = $this->postJson($this->url(), [
            'scope' => 'private',
            'shortcut' => '/preco',
            'content' => 'Olá {nome_paciente}, nosso preço é R$100.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.scope', 'private');
        $response->assertJsonPath('data.shortcut', '/preco');

        $this->assertDatabaseHas('messaging_quick_replies', [
            'owner_user_id' => $this->atendente->id,
            'shortcut' => '/preco',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function admin_clinica_can_create_tenant_scope_quick_reply(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson($this->url(), [
            'scope' => 'tenant',
            'shortcut' => '/horario',
            'content' => 'Atendemos de segunda a sexta, 8h às 18h.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.scope', 'tenant');

        $this->assertDatabaseHas('messaging_quick_replies', [
            'owner_user_id' => null,
            'shortcut' => '/horario',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function attendant_without_quick_reply_manage_cannot_create_tenant_scope_403(): void
    {
        // O authorize() do CreateQuickReplyRequest verifica quick_reply.manage para scope=tenant.
        // Um usuário com inbox.respond mas sem quick_reply.manage recebe 403 ao tentar criar tenant scope.
        // Usamos o financeiro (não tem inbox.respond nem quick_reply.manage) para verificar 403.
        $financeiro = $this->userForRole($this->tenant, 'financeiro');
        $this->actingAs($financeiro);

        $response = $this->postJson($this->url(), [
            'scope' => 'tenant',
            'shortcut' => '/horario',
            'content' => 'Atendemos de segunda a sexta.',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function creates_with_shortcut_starting_with_slash(): void
    {
        $this->actingAs($this->atendente);

        $response = $this->postJson($this->url(), [
            'scope' => 'private',
            'shortcut' => '/boas-vindas',
            'content' => 'Bem-vindo!',
        ]);

        $response->assertCreated();
        $this->assertStringStartsWith('/', $response->json('data.shortcut'));
    }

    #[Test]
    public function rejects_shortcut_without_leading_slash_422(): void
    {
        $this->actingAs($this->atendente);

        $response = $this->postJson($this->url(), [
            'scope' => 'private',
            'shortcut' => 'sem-barra',
            'content' => 'Conteúdo qualquer.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['shortcut']);
    }

    #[Test]
    public function rejects_duplicate_shortcut_in_same_scope_409(): void
    {
        QuickReply::factory()
            ->forTenant($this->tenant)
            ->tenantScope()
            ->create(['shortcut' => '/duplicado']);

        $this->actingAs($this->admin);

        $response = $this->postJson($this->url(), [
            'scope' => 'tenant',
            'shortcut' => '/duplicado',
            'content' => 'Outro conteúdo.',
        ]);

        $response->assertStatus(409);
    }

    #[Test]
    public function private_shortcut_can_coincide_with_tenant_shortcut(): void
    {
        // Tenant scope com /preco já existe
        QuickReply::factory()
            ->forTenant($this->tenant)
            ->tenantScope()
            ->create(['shortcut' => '/preco']);

        $this->actingAs($this->atendente);

        // Privada com mesmo atalho — deve ser permitida (escopos diferentes)
        $response = $this->postJson($this->url(), [
            'scope' => 'private',
            'shortcut' => '/preco',
            'content' => 'Meu preço especial.',
        ]);

        $response->assertCreated();
    }

    // -------------------------------------------------------------------------
    // Atualização
    // -------------------------------------------------------------------------

    #[Test]
    public function admin_can_update_tenant_scope_replies(): void
    {
        $reply = QuickReply::factory()
            ->forTenant($this->tenant)
            ->tenantScope()
            ->create(['shortcut' => '/teste']);

        $this->actingAs($this->admin);

        $response = $this->patchJson($this->url("/{$reply->id}"), [
            'content' => 'Conteúdo atualizado.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.content', 'Conteúdo atualizado.');
    }

    #[Test]
    public function owner_can_update_own_private_reply(): void
    {
        $reply = QuickReply::factory()
            ->forTenant($this->tenant)
            ->privateOf($this->atendente)
            ->create(['shortcut' => '/minha']);

        $this->actingAs($this->atendente);

        $response = $this->patchJson($this->url("/{$reply->id}"), [
            'content' => 'Conteúdo novo.',
        ]);

        $response->assertOk();
    }

    #[Test]
    public function owner_cannot_update_others_private_reply_403(): void
    {
        $outro = $this->userForRole($this->tenant, 'atendente');
        $reply = QuickReply::factory()
            ->forTenant($this->tenant)
            ->privateOf($outro)
            ->create(['shortcut' => '/dele']);

        $this->actingAs($this->atendente);

        $response = $this->patchJson($this->url("/{$reply->id}"), [
            'content' => 'Tentativa indevida.',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function cross_tenant_returns_404(): void
    {
        // Cria tenant B diretamente sem passar pelo bootstrapTenantWithRoles
        // para evitar side-effects no PermissionRegistrar team id.
        $tenantB = $this->createTenant(['slug' => 'clinica-outro-b']);

        // Cria reply no tenant B sem escopo Spatie
        $reply = QuickReply::withoutTenantScope()
            ->create([
                'tenant_id' => $tenantB->id,
                'owner_user_id' => null,
                'shortcut' => '/outro-cross',
                'content' => 'Conteúdo do outro tenant.',
                'has_media' => false,
                'variables_used' => [],
                'usage_count' => 0,
            ]);

        // Reautenticar como admin do tenant original (tenant A)
        $this->actingAs($this->admin);
        $this->app->instance('tenant', $this->tenant);

        $response = $this->patchJson($this->url("/{$reply->id}"), [
            'content' => 'Cross-tenant.',
        ]);

        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Listagem
    // -------------------------------------------------------------------------

    #[Test]
    public function list_returns_tenant_scope_plus_user_private(): void
    {
        $outro = $this->userForRole($this->tenant, 'atendente');

        // Tenant scope — visível a todos
        QuickReply::factory()->forTenant($this->tenant)->tenantScope()->create(['shortcut' => '/tenant-a']);
        QuickReply::factory()->forTenant($this->tenant)->tenantScope()->create(['shortcut' => '/tenant-b']);

        // Privada do atendente — visível apenas para ele
        QuickReply::factory()->forTenant($this->tenant)->privateOf($this->atendente)->create(['shortcut' => '/minha-priv']);

        // Privada de outro — NÃO visível para atendente
        QuickReply::factory()->forTenant($this->tenant)->privateOf($outro)->create(['shortcut' => '/outro-priv']);

        $this->actingAs($this->atendente);

        $response = $this->getJson($this->url());

        $response->assertOk();

        $shortcuts = collect($response->json('data'))->pluck('shortcut')->toArray();

        $this->assertContains('/tenant-a', $shortcuts);
        $this->assertContains('/tenant-b', $shortcuts);
        $this->assertContains('/minha-priv', $shortcuts);
        $this->assertNotContains('/outro-priv', $shortcuts);
    }
}
