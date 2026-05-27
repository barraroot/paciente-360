import { defineConfig, devices } from '@playwright/test';

/**
 * Config dedicada ao harness de auditoria de UI/UX (feature 016).
 *
 * Diferente do `playwright.config.ts` (E2E em tests/e2e): aqui rodamos os
 * specs de invariantes/sweep em `tests/ux`. workers=1 + fullyParallel=false
 * para o sweep acumular achados de forma determinística e escrever um único
 * relatório JSON. baseURL aponta ao tenant de demonstração.
 */
export default defineConfig({
    testDir: './tests/ux',
    testMatch: /.*\.spec\.(ts|js)/,
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 120_000,
    reporter: 'list',
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'https://clinica-alfa.paciente-360.com',
        trace: 'off',
        screenshot: 'off',
        ignoreHTTPSErrors: true,
        headless: true,
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
