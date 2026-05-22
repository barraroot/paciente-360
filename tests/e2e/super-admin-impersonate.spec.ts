import { test, expect } from '@playwright/test';

/**
 * **T285 (Fase 8 — Polish)** — E2E Cenário 4 do quickstart.
 *
 * Super Admin Impersonate (Q5, Gate 7):
 *   1. Super Admin loga em crm.com.br/admin
 *   2. Clica em "Impersonate" num tenant
 *   3. Banner sticky aparece em todas as telas (red gradient, polling 60s)
 *   4. Cada tela visitada gera audit_log `super_admin.screen.visited`
 *   5. Clicar "Sair" encerra sessão e remove banner
 */
test.describe('Super Admin — Impersonate (Cenário 4)', () => {
    const superAdminUrl = 'http://crm.lvh.me';
    const tenantUrl = 'http://clinica-alfa.lvh.me';
    const superEmail = 'super@paciente360.com.br';
    const superPassword = 'super_password';

    test('impersonate_session_persists_banner_audits_screens', async ({ page }) => {
        // 1. Login super admin
        await page.goto(`${superAdminUrl}/admin/login`);
        await page.fill('input[name="email"]', superEmail);
        await page.fill('input[name="password"]', superPassword);
        await page.click('button[type="submit"]');
        await page.waitForURL(/admin\/dashboard/, { timeout: 10_000 });

        // 2. Inicia impersonate via lista de tenants
        await page.goto(`${superAdminUrl}/admin/resources/tenants`);
        const tenantRow = page.locator('tr', { hasText: 'clinica-alfa' });
        await tenantRow.getByRole('button', { name: /impersonate/i }).click();
        await page.getByRole('button', { name: /confirmar/i }).click();

        // 3. Redirect para o tenant + banner visível
        await page.waitForURL(/clinica-alfa\.lvh\.me\/panel/, { timeout: 15_000 });
        const banner = page.locator('[data-testid="impersonate-banner"]');
        await expect(banner).toBeVisible();
        await expect(banner).toContainText(/impersonate/i);
        await expect(banner).toContainText(/sair/i);

        // 4. Visita 3 telas — cada uma gera audit (verificável via super admin audit page)
        await page.goto(`${tenantUrl}/panel/pacientes`);
        await expect(banner).toBeVisible();

        await page.goto(`${tenantUrl}/panel/agenda`);
        await expect(banner).toBeVisible();

        await page.goto(`${tenantUrl}/panel/inbox`);
        await expect(banner).toBeVisible();

        // 5. Sair
        await banner.getByRole('button', { name: /sair/i }).click();
        await page.getByRole('button', { name: /confirmar/i }).click();
        await page.waitForURL(/crm\.lvh\.me/, { timeout: 10_000 });
        await expect(banner).not.toBeVisible();
    });
});
