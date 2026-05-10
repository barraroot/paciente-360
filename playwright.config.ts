import { defineConfig, devices } from '@playwright/test';

/**
 * Configuração base do Playwright para E2E do Paciente360.
 *
 * - baseURL aponta para o tenant de demonstração `clinica-alfa.lvh.me`,
 *   alinhado com `config/tenancy.php` (subdomain_suffix=lvh.me em dev/CI).
 * - Retries=2 apenas em CI (Princípio IV — pipeline confiável sob flake leve).
 * - Workers=1 em CI para garantir isolamento determinístico do schema multi-tenant.
 * - Trace `on-first-retry` reduz custo no caminho feliz e dá diagnose nos retries.
 *
 * Especifica diretório `tests/e2e/`; specs serão adicionados nas tasks T138+.
 */
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'https://clinica-alfa.lvh.me',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        ignoreHTTPSErrors: true,
        headless: !process.env.PLAYWRIGHT_HEADED,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
