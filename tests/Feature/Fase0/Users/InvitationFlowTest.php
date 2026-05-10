<?php

namespace Tests\Feature\Fase0\Users;

use App\Jobs\Email\SendInvitationEmailJob;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Services\Users\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Feature tests de fluxo completo de convites (T240 — US-2.2, FR-025).
 *
 * Cobre: criação, e-mail, aceite, invalidade, expiração, revogação,
 * isolamento cross-tenant e controle de acesso por role.
 */
class InvitationFlowTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Cria tenant com roles de sistema + usuário admin-clinica pronto.
     *
     * @return array{tenant: Tenant, admin: User}
     */
    private function createTenantWithAdmin(string $slug = 'clinica-inv'): array
    {
        $tenant = $this->createTenant(['slug' => $slug]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        Role::firstOrCreate(
            ['name' => 'admin-clinica', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );
        Role::firstOrCreate(
            ['name' => 'medico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );

        $admin = $this->createUserForTenant($tenant, ['status' => 'active']);
        $admin->assignRole('admin-clinica');

        return ['tenant' => $tenant, 'admin' => $admin];
    }

    private function invitationsUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/users/invitations";
    }

    private function acceptUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/users/invitations/accept";
    }

    /** @test */
    public function test_admin_clinica_can_create_invitation(): void
    {
        Notification::fake();

        ['tenant' => $tenant, 'admin' => $admin] = $this->createTenantWithAdmin('clinica-inv-create');

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($admin)
            ->postJson(
                $this->invitationsUrl('clinica-inv-create'),
                ['email' => 'novo@clinica.com', 'intended_role' => 'medico']
            );

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'email', 'intended_role', 'expires_at', 'accepted_at'],
        ]);
        $response->assertJsonPath('data.email', 'novo@clinica.com');
        $response->assertJsonPath('data.intended_role', 'medico');
        $response->assertJsonPath('data.accepted_at', null);

        $this->assertDatabaseHas('invitations', [
            'tenant_id' => $tenant->id,
            'email' => 'novo@clinica.com',
            'intended_role' => 'medico',
            'inviter_user_id' => $admin->id,
            'status' => 'pending',
        ]);

        $invitation = Invitation::withoutGlobalScopes()
            ->where('email', 'novo@clinica.com')
            ->first();

        $this->assertNotNull($invitation->token_hash);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.invitation.created',
            'tenant_id' => $tenant->id,
        ]);

        Notification::assertSentOnDemand(InvitationNotification::class);
    }

    /** @test */
    public function test_invitation_email_contains_correct_subdomain_link(): void
    {
        Notification::fake();

        ['tenant' => $tenant, 'admin' => $admin] = $this->createTenantWithAdmin('clinica-x');

        $this->app->instance('tenant', $tenant);

        $this->actingAs($admin)
            ->postJson(
                $this->invitationsUrl('clinica-x'),
                ['email' => 'convidado@example.com', 'intended_role' => 'medico']
            )
            ->assertStatus(201);

        Notification::assertSentOnDemand(
            InvitationNotification::class,
            function (InvitationNotification $notification, array $channels, object $notifiable) use ($tenant): bool {
                $mail = $notification->toMail($notifiable);
                $actionUrl = $mail->actionUrl ?? '';

                return str_contains($actionUrl, "{$tenant->slug}.lvh.me")
                    && str_contains($actionUrl, 'accept-invitation?token=');
            }
        );
    }

    /** @test */
    public function test_accept_with_valid_token_creates_user_and_logs_in(): void
    {
        Notification::fake();

        ['tenant' => $tenant, 'admin' => $admin] = $this->createTenantWithAdmin('clinica-accept');

        $this->app->instance('tenant', $tenant);

        // Captura token claro via InvitationService diretamente.
        $tokenClaro = null;
        $service = new InvitationService;

        $invitation = $this->callInvitationServiceCapturingToken(
            $tenant,
            $admin,
            'aceite@example.com',
            'medico',
            $tokenClaro
        );

        $this->assertNotNull($tokenClaro);

        $response = $this->postJson(
            $this->acceptUrl('clinica-accept'),
            [
                'token' => $tokenClaro,
                'name' => 'Dr. Aceito',
                'password' => 'StrongPwd123',
            ]
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'status', 'roles'],
        ]);
        $response->assertJsonPath('data.email', 'aceite@example.com');
        $response->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('users', [
            'email' => 'aceite@example.com',
            'status' => 'active',
            'tenant_id' => $tenant->id,
        ]);

        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.invitation.accepted',
            'tenant_id' => $tenant->id,
        ]);
    }

    /** @test */
    public function test_accept_with_invalid_token_returns_410(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithAdmin('clinica-invalid-token');
        $this->app->instance('tenant', $tenant);

        $response = $this->postJson(
            $this->acceptUrl('clinica-invalid-token'),
            [
                'token' => bin2hex(random_bytes(32)),
                'name' => 'Ninguém',
                'password' => 'StrongPwd123',
            ]
        );

        $response->assertStatus(410);
        $response->assertJsonPath('error', 'invalid_invitation');
    }

    /** @test */
    public function test_accept_expired_returns_410(): void
    {
        Notification::fake();

        ['tenant' => $tenant, 'admin' => $admin] = $this->createTenantWithAdmin('clinica-expired');
        $this->app->instance('tenant', $tenant);

        $tokenClaro = null;
        $this->callInvitationServiceCapturingToken(
            $tenant,
            $admin,
            'expirado@example.com',
            'medico',
            $tokenClaro
        );

        // Força expiração.
        Invitation::withoutGlobalScopes()
            ->where('email', 'expirado@example.com')
            ->update(['expires_at' => now()->subHour()]);

        $response = $this->postJson(
            $this->acceptUrl('clinica-expired'),
            [
                'token' => $tokenClaro,
                'name' => 'Expirado',
                'password' => 'StrongPwd123',
            ]
        );

        $response->assertStatus(410);

        $this->assertDatabaseHas('invitations', [
            'email' => 'expirado@example.com',
            'status' => 'expired',
        ]);
    }

    /** @test */
    public function test_token_can_be_used_only_once(): void
    {
        Notification::fake();

        ['tenant' => $tenant, 'admin' => $admin] = $this->createTenantWithAdmin('clinica-once');
        $this->app->instance('tenant', $tenant);

        $tokenClaro = null;
        $this->callInvitationServiceCapturingToken(
            $tenant,
            $admin,
            'umavez@example.com',
            'medico',
            $tokenClaro
        );

        // Primeiro aceite — OK.
        $this->postJson(
            $this->acceptUrl('clinica-once'),
            ['token' => $tokenClaro, 'name' => 'First', 'password' => 'StrongPwd123']
        )->assertOk();

        // Segundo aceite — 410.
        $this->postJson(
            $this->acceptUrl('clinica-once'),
            ['token' => $tokenClaro, 'name' => 'Second', 'password' => 'StrongPwd123']
        )->assertStatus(410);
    }

    /** @test */
    public function test_accept_in_other_tenant_subdomain_returns_410(): void
    {
        Notification::fake();

        ['tenant' => $tenantA, 'admin' => $adminA] = $this->createTenantWithAdmin('clinica-a-cross');
        ['tenant' => $tenantB] = $this->createTenantWithAdmin('clinica-b-cross');

        $this->app->instance('tenant', $tenantA);

        $tokenClaro = null;
        $this->callInvitationServiceCapturingToken(
            $tenantA,
            $adminA,
            'cross@example.com',
            'medico',
            $tokenClaro
        );

        // Tenta aceitar no subdomínio do tenant B.
        $this->app->instance('tenant', $tenantB);

        $response = $this->postJson(
            $this->acceptUrl('clinica-b-cross'),
            ['token' => $tokenClaro, 'name' => 'Cross', 'password' => 'StrongPwd123']
        );

        $response->assertStatus(410);
    }

    /** @test */
    public function test_revoke_invitation_succeeds_when_pending(): void
    {
        Notification::fake();

        ['tenant' => $tenant, 'admin' => $admin] = $this->createTenantWithAdmin('clinica-revoke');
        $this->app->instance('tenant', $tenant);

        $tokenClaro = null;
        $invitation = $this->callInvitationServiceCapturingToken(
            $tenant,
            $admin,
            'revogar@example.com',
            'medico',
            $tokenClaro
        );

        $response = $this->actingAs($admin)
            ->deleteJson("{$this->invitationsUrl('clinica-revoke')}/{$invitation->id}");

        $response->assertStatus(204);

        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'status' => 'revoked',
        ]);

        $this->assertNotNull(
            Invitation::withoutGlobalScopes()->find($invitation->id)->revoked_at
        );
    }

    /** @test */
    public function test_revoke_already_accepted_returns_410(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->createTenantWithAdmin('clinica-revoke-acc');
        $this->app->instance('tenant', $tenant);

        $invitation = Invitation::factory()
            ->forTenant($tenant, $admin)
            ->accepted()
            ->create(['email' => 'ja-aceito@example.com']);

        $response = $this->actingAs($admin)
            ->deleteJson("{$this->invitationsUrl('clinica-revoke-acc')}/{$invitation->id}");

        $response->assertStatus(410);
    }

    /** @test */
    public function test_only_admin_clinica_can_invite(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithAdmin('clinica-perm-invite');

        $medico = $this->createUserForTenant($tenant, ['status' => 'active']);
        $medico->assignRole('medico');

        $this->app->instance('tenant', $tenant);

        $response = $this->actingAs($medico)
            ->postJson(
                $this->invitationsUrl('clinica-perm-invite'),
                ['email' => 'novo@example.com', 'intended_role' => 'medico']
            );

        $response->assertStatus(403);
    }

    /** @test */
    public function test_only_admin_clinica_can_revoke(): void
    {
        Notification::fake();

        ['tenant' => $tenant, 'admin' => $admin] = $this->createTenantWithAdmin('clinica-perm-revoke');

        $medico = $this->createUserForTenant($tenant, ['status' => 'active']);
        $medico->assignRole('medico');

        $this->app->instance('tenant', $tenant);

        $tokenClaro = null;
        $invitation = $this->callInvitationServiceCapturingToken(
            $tenant,
            $admin,
            'alvo@example.com',
            'medico',
            $tokenClaro
        );

        $response = $this->actingAs($medico)
            ->deleteJson("{$this->invitationsUrl('clinica-perm-revoke')}/{$invitation->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function test_list_invitations_shows_pending_only(): void
    {
        Notification::fake();

        ['tenant' => $tenant, 'admin' => $admin] = $this->createTenantWithAdmin('clinica-list-inv');
        $this->app->instance('tenant', $tenant);

        // Cria pending.
        $tokenClaro = null;
        $this->callInvitationServiceCapturingToken(
            $tenant,
            $admin,
            'pending@example.com',
            'medico',
            $tokenClaro
        );

        // Cria accepted manualmente.
        Invitation::factory()
            ->forTenant($tenant, $admin)
            ->accepted()
            ->create(['email' => 'aceito@example.com']);

        $response = $this->actingAs($admin)
            ->getJson($this->invitationsUrl('clinica-list-inv'));

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('pending@example.com', $data[0]['email']);
    }

    /**
     * Chama InvitationService capturando o token claro via spy no job.
     *
     * Estratégia: intercepta `SendInvitationEmailJob::dispatch` via fake de queue
     * e extrai `tokenClaro` do payload do job.
     */
    private function callInvitationServiceCapturingToken(
        Tenant $tenant,
        User $inviter,
        string $email,
        string $role,
        ?string &$tokenClaro,
    ): Invitation {
        // Intercepta o dispatch do job via Bus::fake parcial.
        Bus::fake([SendInvitationEmailJob::class]);

        $this->app->instance('tenant', $tenant);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $service = new InvitationService;
        $invitation = $service->createInvitation($tenant, $inviter, $email, $role);

        Bus::assertDispatched(
            SendInvitationEmailJob::class,
            function (SendInvitationEmailJob $job) use (&$tokenClaro): bool {
                $tokenClaro = $job->tokenClaro;

                return true;
            }
        );

        return $invitation;
    }
}
