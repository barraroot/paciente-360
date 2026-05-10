<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Smoke check estático das páginas Vue de gestão de usuários (T246 / US7).
 *
 * Verifica a integridade dos artefatos JS/Vue sem executar o runtime Vue.
 * Serve como gate de CI antes do `vite build`.
 *
 * Cobre:
 *  - UsersListPage.vue existe e chama o endpoint /users.
 *  - InviteUserPage.vue existe e usa todas as opções de papel.
 *  - AcceptInvitationPage.vue existe, lê route.query.token e redireciona para /panel.
 *  - router/index.js declara as rotas users.list, users.invite e users.accept.
 *  - Tratamento de 409 last_admin e 409 plan_limit nas páginas correspondentes.
 */
class UsersFrontendTest extends TestCase
{
    private string $usersListPath;

    private string $inviteUserPath;

    private string $acceptInvitationPath;

    private string $routerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usersListPath = base_path('resources/js/pages/users/UsersListPage.vue');
        $this->inviteUserPath = base_path('resources/js/pages/users/InviteUserPage.vue');
        $this->acceptInvitationPath = base_path('resources/js/pages/invitations/AcceptInvitationPage.vue');
        $this->routerPath = base_path('resources/js/router/index.js');
    }

    // ── Existência dos arquivos ────────────────────────────────────────────────

    public function test_users_list_page_exists(): void
    {
        $this->assertFileExists(
            $this->usersListPath,
            'resources/js/pages/users/UsersListPage.vue deve existir (T246).'
        );
    }

    public function test_invite_user_page_exists(): void
    {
        $this->assertFileExists(
            $this->inviteUserPath,
            'resources/js/pages/users/InviteUserPage.vue deve existir (T246).'
        );
    }

    public function test_accept_invitation_page_exists(): void
    {
        $this->assertFileExists(
            $this->acceptInvitationPath,
            'resources/js/pages/invitations/AcceptInvitationPage.vue deve existir (T246).'
        );
    }

    // ── UsersListPage ─────────────────────────────────────────────────────────

    public function test_users_list_calls_get_users(): void
    {
        $vue = file_get_contents($this->usersListPath);

        $this->assertTrue(
            str_contains($vue, "'/users'") || str_contains($vue, '"/users"') || str_contains($vue, '`/users`'),
            'UsersListPage.vue deve chamar o endpoint /users (api.get("/users") ou similar).'
        );
    }

    public function test_users_list_handles_409_last_admin(): void
    {
        $vue = file_get_contents($this->usersListPath);

        $this->assertTrue(
            str_contains($vue, 'last_admin') || (str_contains($vue, '409') && str_contains($vue, 'last_admin')),
            'UsersListPage.vue deve tratar erro 409 last_admin.'
        );
    }

    // ── InviteUserPage ────────────────────────────────────────────────────────

    public function test_invite_page_uses_role_options(): void
    {
        $vue = file_get_contents($this->inviteUserPath);

        foreach (['admin-clinica', 'medico', 'atendente', 'recepcionista', 'financeiro'] as $role) {
            $this->assertStringContainsString(
                $role,
                $vue,
                "InviteUserPage.vue deve referenciar o papel '{$role}'."
            );
        }
    }

    public function test_users_list_handles_409_plan_limit(): void
    {
        $vue = file_get_contents($this->inviteUserPath);

        $this->assertTrue(
            str_contains($vue, 'plan_limit') || str_contains($vue, 'plan_limit_reached'),
            'InviteUserPage.vue deve tratar erro 409 plan_limit_reached.'
        );
    }

    // ── AcceptInvitationPage ──────────────────────────────────────────────────

    public function test_accept_page_reads_token_from_query(): void
    {
        $vue = file_get_contents($this->acceptInvitationPath);

        $this->assertTrue(
            str_contains($vue, 'route.query.token') || str_contains($vue, 'useRoute().query.token'),
            'AcceptInvitationPage.vue deve ler o token de route.query.token.'
        );
    }

    public function test_accept_page_redirects_on_success(): void
    {
        $vue = file_get_contents($this->acceptInvitationPath);

        $this->assertStringContainsString(
            'router.push',
            $vue,
            'AcceptInvitationPage.vue deve chamar router.push após sucesso.'
        );

        $this->assertTrue(
            str_contains($vue, "'/panel'") || str_contains($vue, '"/panel"') || str_contains($vue, '`/panel`'),
            'AcceptInvitationPage.vue deve redirecionar para /panel após aceite.'
        );
    }

    // ── Router ────────────────────────────────────────────────────────────────

    public function test_router_has_users_routes(): void
    {
        $js = file_get_contents($this->routerPath);

        $this->assertStringContainsString(
            "name: 'users.list'",
            $js,
            "router/index.js deve declarar rota com name: 'users.list'."
        );

        $this->assertStringContainsString(
            "name: 'users.invite'",
            $js,
            "router/index.js deve declarar rota com name: 'users.invite'."
        );

        $this->assertStringContainsString(
            "name: 'users.accept'",
            $js,
            "router/index.js deve declarar rota com name: 'users.accept'."
        );
    }
}
