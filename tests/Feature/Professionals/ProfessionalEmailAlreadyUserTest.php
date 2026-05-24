<?php

declare(strict_types=1);

namespace Tests\Feature\Professionals;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * **G5 (Spec 012)** — Q2: confirmação de email duplicado (FR-005a).
 *
 * O caso "409 sem confirmação" vive em `ProfessionalInvitationFlowTest`. Aqui
 * cobrimos: confirmação explícita (`confirmed_existing_user=true`) → 201 vinculado,
 * e o endpoint `check-email` (que NÃO expõe o email do user — Princípio I).
 */
final class ProfessionalEmailAlreadyUserTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        [$this->tenant] = $this->tenantAndUserForRole('email-q2-admin', 'admin-clinica');
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    public function test_create_with_confirmed_existing_user_links_without_new_invitation(): void
    {
        $existing = User::factory()->forTenant($this->tenant)->create([
            'email' => 'existente@example.com',
            'name' => 'Já Existo',
        ]);

        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/professionals'),
            [
                'name' => 'Dr. Existente',
                'council_type' => 'CRM',
                'council_number' => '11223',
                'council_state' => 'SP',
                'email' => 'existente@example.com',
                'confirmed_existing_user' => true,
            ]
        );

        $response->assertCreated();
        $response->assertJsonPath('data.is_active', true);
        $response->assertJsonPath('data.user.id', $existing->id);

        $this->assertDatabaseHas('professionals', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $existing->id,
            'is_active' => true,
        ]);

        // Não cria convite quando vincula a user existente confirmado.
        $this->assertDatabaseMissing('invitations', [
            'tenant_id' => $this->tenant->id,
            'email' => 'existente@example.com',
        ]);
    }

    public function test_check_email_returns_existing_user_id_and_name_without_email(): void
    {
        $existing = User::factory()->forTenant($this->tenant)->create([
            'email' => 'maria@example.com',
            'name' => 'Maria Souza',
        ]);

        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/professionals/check-email'),
            ['email' => 'maria@example.com']
        );

        $response->assertOk();
        $response->assertJsonPath('exists_in_current_tenant', true);
        $response->assertJsonPath('existing_user.id', $existing->id);
        $response->assertJsonPath('existing_user.name', 'Maria Souza');
        $response->assertJsonPath('exists_in_other_tenant', false);

        // Princípio I — não vaza o email do user existente.
        $this->assertStringNotContainsString('maria@example.com', $response->json('existing_user.name'));
        $response->assertJsonMissingPath('existing_user.email');
    }

    public function test_check_email_flags_other_tenant_without_leaking_user(): void
    {
        $tenantB = $this->bootstrapTenantWithRoles('email-q2-b');
        User::factory()->forTenant($tenantB)->create(['email' => 'outro@example.com']);

        // Reautentica como tenant atual (bootstrap mexe no contexto).
        [$this->tenant] = $this->tenantAndUserForRole('email-q2-admin-2', 'admin-clinica');

        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/professionals/check-email'),
            ['email' => 'outro@example.com']
        );

        $response->assertOk();
        $response->assertJsonPath('exists_in_current_tenant', false);
        $response->assertJsonPath('existing_user', null);
        $response->assertJsonPath('exists_in_other_tenant', true);
    }

    public function test_check_email_unknown_returns_all_false(): void
    {
        $response = $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, '/professionals/check-email'),
            ['email' => 'ninguem@example.com']
        );

        $response->assertOk();
        $response->assertJsonPath('exists_in_current_tenant', false);
        $response->assertJsonPath('existing_user', null);
        $response->assertJsonPath('exists_in_other_tenant', false);
    }
}
