<?php

namespace Tests\Feature\Fase3\US7_QuickReplies;

use App\Domain\Messaging\QuickReply\Models\QuickReply;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T230 — Privada override tenant quando shortcut coincide (AC-4.7.2).
 *
 * Quando user tem resposta rápida privada com mesmo atalho que uma tenant,
 * a privada aparece na listagem em vez da tenant (override).
 * Outros usuários continuam vendo a versão tenant.
 */
class QuickReplyPrivateOverridesTenantTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        [$this->tenant, $this->userA] = $this->tenantAndUserForRole('clinica-override', 'atendente');
        $this->userB = $this->userForRole($this->tenant, 'atendente');
    }

    private function url(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, '/inbox/quick-replies'.$path);
    }

    #[Test]
    public function when_user_has_private_shortcut_same_as_tenant_private_overrides_in_list(): void
    {
        // Tenant scope "/preco"
        $tenantReply = QuickReply::factory()
            ->forTenant($this->tenant)
            ->tenantScope()
            ->create([
                'shortcut' => '/preco',
                'content' => 'Preço da equipe: R$200.',
            ]);

        // User A tem privada com mesmo atalho
        $privateReply = QuickReply::factory()
            ->forTenant($this->tenant)
            ->privateOf($this->userA)
            ->create([
                'shortcut' => '/preco',
                'content' => 'Meu preço especial: R$150.',
            ]);

        Sanctum::actingAs($this->userA, ['*']);

        $response = $this->getJson($this->url());
        $response->assertOk();

        $items = collect($response->json('data'));
        $precoItems = $items->where('shortcut', '/preco');

        // Deve haver exatamente 1 item com /preco — a privada vence
        $this->assertCount(1, $precoItems);

        $preco = $precoItems->first();
        $this->assertEquals('private', $preco['scope']);
        $this->assertEquals($privateReply->id, $preco['id']);
    }

    #[Test]
    public function other_users_see_tenant_scope_version(): void
    {
        $tenantReply = QuickReply::factory()
            ->forTenant($this->tenant)
            ->tenantScope()
            ->create([
                'shortcut' => '/preco',
                'content' => 'Preço da equipe: R$200.',
            ]);

        // User A tem privada — User B não
        QuickReply::factory()
            ->forTenant($this->tenant)
            ->privateOf($this->userA)
            ->create([
                'shortcut' => '/preco',
                'content' => 'Meu preço especial: R$150.',
            ]);

        Sanctum::actingAs($this->userB, ['*']);

        $response = $this->getJson($this->url());
        $response->assertOk();

        $items = collect($response->json('data'));
        $precoItems = $items->where('shortcut', '/preco');

        $this->assertCount(1, $precoItems);
        $preco = $precoItems->first();
        $this->assertEquals('tenant', $preco['scope']);
        $this->assertEquals($tenantReply->id, $preco['id']);
    }

    #[Test]
    public function private_override_uses_private_content_when_rendered(): void
    {
        // Tenant scope
        QuickReply::factory()
            ->forTenant($this->tenant)
            ->tenantScope()
            ->create([
                'shortcut' => '/preco',
                'content' => 'Preço da equipe.',
            ]);

        $privateReply = QuickReply::factory()
            ->forTenant($this->tenant)
            ->privateOf($this->userA)
            ->create([
                'shortcut' => '/preco',
                'content' => 'Preço privado.',
            ]);

        Sanctum::actingAs($this->userA, ['*']);

        $items = collect($this->getJson($this->url())->json('data'));
        $preco = $items->where('shortcut', '/preco')->first();

        $this->assertEquals('Preço privado.', $preco['content']);
    }

    #[Test]
    public function scope_query_filter_works(): void
    {
        // Tenant scope
        QuickReply::factory()
            ->forTenant($this->tenant)
            ->tenantScope()
            ->create(['shortcut' => '/tenant-only']);

        // Privada do userA
        QuickReply::factory()
            ->forTenant($this->tenant)
            ->privateOf($this->userA)
            ->create(['shortcut' => '/mine-only']);

        Sanctum::actingAs($this->userA, ['*']);

        // Filter scope=tenant
        $tenantResponse = $this->getJson($this->url().'?scope=tenant');
        $tenantResponse->assertOk();
        $tenantShortcuts = collect($tenantResponse->json('data'))->pluck('shortcut')->toArray();
        $this->assertContains('/tenant-only', $tenantShortcuts);
        $this->assertNotContains('/mine-only', $tenantShortcuts);

        // Filter scope=mine
        $mineResponse = $this->getJson($this->url().'?scope=mine');
        $mineResponse->assertOk();
        $mineShortcuts = collect($mineResponse->json('data'))->pluck('shortcut')->toArray();
        $this->assertContains('/mine-only', $mineShortcuts);
        $this->assertNotContains('/tenant-only', $mineShortcuts);

        // Filter scope=all (default)
        $allResponse = $this->getJson($this->url().'?scope=all');
        $allResponse->assertOk();
        $allShortcuts = collect($allResponse->json('data'))->pluck('shortcut')->toArray();
        $this->assertContains('/tenant-only', $allShortcuts);
        $this->assertContains('/mine-only', $allShortcuts);
    }
}
