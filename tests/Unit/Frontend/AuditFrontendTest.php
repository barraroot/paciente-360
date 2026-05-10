<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Smoke check estático da página Vue de Audit Logs (T265 / US9).
 *
 * Verifica a integridade dos artefatos JS/Vue sem executar o runtime Vue.
 * Serve como gate de CI antes do `vite build`.
 *
 * Cobre:
 *  - AuditLogsPage.vue existe.
 *  - Chama o endpoint /audit-logs.
 *  - Usa formatDateTime do composable useI18nFormat.
 *  - Suporta os filtros from, to, action e actor_user_id.
 *  - Botão de exportação CSV presente (/audit-logs/export ou export_csv).
 *  - Payload renderizado em modal com JSON.stringify ou pretty.
 *  - router/index.js declara a rota audit.list.
 *  - Exibe 'Sistema' (audit.list.actor_system) para actor.type === 'system'.
 */
class AuditFrontendTest extends TestCase
{
    private string $auditPagePath;

    private string $routerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditPagePath = base_path('resources/js/pages/audit/AuditLogsPage.vue');
        $this->routerPath = base_path('resources/js/router/index.js');
    }

    // ── Existência ────────────────────────────────────────────────────────────

    public function test_audit_page_exists(): void
    {
        $this->assertFileExists(
            $this->auditPagePath,
            'resources/js/pages/audit/AuditLogsPage.vue deve existir (T265).'
        );
    }

    // ── Chamada de API ────────────────────────────────────────────────────────

    public function test_page_calls_get_audit_logs(): void
    {
        $vue = file_get_contents($this->auditPagePath);

        $this->assertTrue(
            str_contains($vue, "'/audit-logs'") ||
            str_contains($vue, '"/audit-logs"') ||
            str_contains($vue, '`/audit-logs`'),
            'AuditLogsPage.vue deve chamar o endpoint /audit-logs.'
        );
    }

    // ── formatDateTime ────────────────────────────────────────────────────────

    public function test_page_uses_format_datetime(): void
    {
        $vue = file_get_contents($this->auditPagePath);

        $this->assertStringContainsString(
            'formatDateTime',
            $vue,
            'AuditLogsPage.vue deve usar formatDateTime do useI18nFormat.'
        );
    }

    // ── Filtros ───────────────────────────────────────────────────────────────

    public function test_page_supports_filters(): void
    {
        $vue = file_get_contents($this->auditPagePath);

        foreach (['from', 'to', 'action', 'actor_user_id'] as $filter) {
            $this->assertStringContainsString(
                $filter,
                $vue,
                "AuditLogsPage.vue deve suportar o filtro '{$filter}'."
            );
        }
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function test_page_export_csv_button_present(): void
    {
        $vue = file_get_contents($this->auditPagePath);

        $this->assertTrue(
            str_contains($vue, '/audit-logs/export') || str_contains($vue, 'export_csv'),
            'AuditLogsPage.vue deve ter botão/link de exportação CSV (/audit-logs/export ou export_csv).'
        );
    }

    // ── Modal de payload ──────────────────────────────────────────────────────

    public function test_page_renders_payload_in_modal(): void
    {
        $vue = file_get_contents($this->auditPagePath);

        $this->assertStringContainsString(
            'payload',
            $vue,
            'AuditLogsPage.vue deve referenciar o campo payload no modal de detalhes.'
        );

        $this->assertTrue(
            str_contains($vue, 'JSON.stringify') || str_contains($vue, 'pretty'),
            'AuditLogsPage.vue deve formatar o payload com JSON.stringify ou função pretty.'
        );
    }

    // ── Router ────────────────────────────────────────────────────────────────

    public function test_router_has_audit_route(): void
    {
        $js = file_get_contents($this->routerPath);

        $this->assertStringContainsString(
            "name: 'audit.list'",
            $js,
            "router/index.js deve declarar rota com name: 'audit.list'."
        );
    }

    // ── Actor system ──────────────────────────────────────────────────────────

    public function test_page_shows_actor_system_for_null_user(): void
    {
        $vue = file_get_contents($this->auditPagePath);

        $this->assertTrue(
            str_contains($vue, 'audit.list.actor_system') || str_contains($vue, "'Sistema'") || str_contains($vue, '"Sistema"'),
            "AuditLogsPage.vue deve exibir 'Sistema' (audit.list.actor_system) para actor.type === 'system'."
        );
    }
}
