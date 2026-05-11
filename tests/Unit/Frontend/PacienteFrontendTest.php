<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * Smoke check estático das páginas Vue do módulo Pacientes (T123–T129 / US1).
 *
 * Verifica a integridade dos artefatos JS/Vue sem executar o runtime Vue.
 * Serve como gate de CI antes do `vite build`.
 */
class PacienteFrontendTest extends TestCase
{
    private string $listPagePath;

    private string $formPagePath;

    private string $showPagePath;

    private string $mesclagemPagePath;

    private string $dedupModalPath;

    private string $composablePath;

    private string $routerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listPagePath = base_path('resources/js/pages/pacientes/PacientesListPage.vue');
        $this->formPagePath = base_path('resources/js/pages/pacientes/PacienteFormPage.vue');
        $this->showPagePath = base_path('resources/js/pages/pacientes/PacienteShowPage.vue');
        $this->mesclagemPagePath = base_path('resources/js/pages/pacientes/MesclagemPage.vue');
        $this->dedupModalPath = base_path('resources/js/components/pacientes/DedupSuggestionModal.vue');
        $this->composablePath = base_path('resources/js/composables/usePacienteSearch.js');
        $this->routerPath = base_path('resources/js/router/index.js');
    }

    // ── Existência dos arquivos ────────────────────────────────────────────────

    public function test_pacientes_list_page_exists(): void
    {
        $this->assertFileExists(
            $this->listPagePath,
            'resources/js/pages/pacientes/PacientesListPage.vue deve existir (T123).'
        );
    }

    public function test_paciente_form_page_exists(): void
    {
        $this->assertFileExists(
            $this->formPagePath,
            'resources/js/pages/pacientes/PacienteFormPage.vue deve existir (T124).'
        );
    }

    public function test_paciente_show_page_exists(): void
    {
        $this->assertFileExists(
            $this->showPagePath,
            'resources/js/pages/pacientes/PacienteShowPage.vue deve existir (T125).'
        );
    }

    public function test_mesclagem_page_exists(): void
    {
        $this->assertFileExists(
            $this->mesclagemPagePath,
            'resources/js/pages/pacientes/MesclagemPage.vue deve existir (T127).'
        );
    }

    public function test_dedup_modal_exists(): void
    {
        $this->assertFileExists(
            $this->dedupModalPath,
            'resources/js/components/pacientes/DedupSuggestionModal.vue deve existir (T126).'
        );
    }

    public function test_use_paciente_search_composable_exists(): void
    {
        $this->assertFileExists(
            $this->composablePath,
            'resources/js/composables/usePacienteSearch.js deve existir (T128).'
        );
    }

    // ── PacientesListPage ──────────────────────────────────────────────────────

    public function test_pacientes_list_calls_api_get_pacientes(): void
    {
        // A página pode chamar /pacientes diretamente ou delegar ao composable usePacienteSearch.
        // O composable (usePacienteSearch.js) é quem invoca api.get('/pacientes').
        $vue = file_get_contents($this->listPagePath);
        $composable = file_get_contents($this->composablePath);

        $pageHasEndpoint = str_contains($vue, "'/pacientes'")
            || str_contains($vue, '"/pacientes"')
            || str_contains($vue, '`/pacientes`')
            || str_contains($vue, 'usePacienteSearch');

        $composableHasEndpoint = str_contains($composable, "'/pacientes'")
            || str_contains($composable, '"/pacientes"')
            || str_contains($composable, '`/pacientes`');

        $this->assertTrue(
            $pageHasEndpoint && $composableHasEndpoint,
            'PacientesListPage.vue (via usePacienteSearch) deve chamar o endpoint /pacientes.'
        );
    }

    // ── PacienteFormPage ──────────────────────────────────────────────────────

    public function test_form_handles_409_dedup(): void
    {
        $vue = file_get_contents($this->formPagePath);

        $this->assertTrue(
            str_contains($vue, '409') || str_contains($vue, 'candidatos'),
            'PacienteFormPage.vue deve tratar resposta 409 (dedup) ou usar "candidatos".'
        );
    }

    public function test_form_uses_format_cpf(): void
    {
        $vue = file_get_contents($this->formPagePath);

        $hasFunctionName = str_contains($vue, 'formatCpf');
        $hasPattern = (bool) preg_match('/\d{3}\\\\\.\d{3}\\\\\.\d{3}-\d{2}/', $vue)
            || str_contains($vue, '000.000.000-00')
            || str_contains($vue, "'.'-");

        $this->assertTrue(
            $hasFunctionName || $hasPattern,
            'PacienteFormPage.vue deve conter formatCpf ou lógica de máscara CPF (000.000.000-00).'
        );
    }

    // ── ShowPage tabs ─────────────────────────────────────────────────────────

    public function test_show_page_has_tabs(): void
    {
        $vue = file_get_contents($this->showPagePath);

        $this->assertTrue(
            str_contains($vue, 'tabs.detalhes'),
            'PacienteShowPage.vue deve conter referência à tab "tabs.detalhes".'
        );
        $this->assertTrue(
            str_contains($vue, 'tabs.timeline'),
            'PacienteShowPage.vue deve conter referência à tab "tabs.timeline".'
        );
        $this->assertTrue(
            str_contains($vue, 'tabs.anotacoes'),
            'PacienteShowPage.vue deve conter referência à tab "tabs.anotacoes".'
        );
    }

    // ── Router ────────────────────────────────────────────────────────────────

    public function test_router_has_5_paciente_routes(): void
    {
        $js = file_get_contents($this->routerPath);

        $this->assertStringContainsString(
            "name: 'pacientes.list'",
            $js,
            "router/index.js deve declarar rota 'pacientes.list'."
        );
        $this->assertStringContainsString(
            "name: 'pacientes.create'",
            $js,
            "router/index.js deve declarar rota 'pacientes.create'."
        );
        $this->assertStringContainsString(
            "name: 'pacientes.mesclagem'",
            $js,
            "router/index.js deve declarar rota 'pacientes.mesclagem'."
        );
        $this->assertStringContainsString(
            "name: 'pacientes.show'",
            $js,
            "router/index.js deve declarar rota 'pacientes.show'."
        );
        $this->assertStringContainsString(
            "name: 'pacientes.edit'",
            $js,
            "router/index.js deve declarar rota 'pacientes.edit'."
        );
    }
}
