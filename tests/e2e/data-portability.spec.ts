import { test, expect } from '@playwright/test';

/**
 * **T284 (Fase 8 — Polish)** — E2E Cenário 3 do quickstart.
 *
 * Portabilidade de Dados (LGPD Q28):
 *   1. Admin cria PortabilityRequest para paciente
 *   2. Executa → gera arquivo JSON em S3
 *   3. URL assinada gerada (TTL 7d)
 *   4. Download funciona
 *   5. Após 7d, URL retorna 403 (link expirado)
 */
test.describe('Privacidade — Portabilidade (Cenário 3)', () => {
    const baseUrl = 'http://clinica-alfa.lvh.me';
    const adminEmail = 'admin@clinica-alfa.com.br';
    const adminPassword = 'password';

    async function loginAdmin(page) {
        await page.goto(`${baseUrl}/login`);
        await page.fill('input[name="email"]', adminEmail);
        await page.fill('input[name="password"]', adminPassword);
        await page.click('button[type="submit"]');
        await page.waitForURL(`${baseUrl}/panel`, { timeout: 10_000 });
    }

    test('admin_generates_portability_file_and_downloads', async ({ page }) => {
        await loginAdmin(page);
        await page.goto(`${baseUrl}/panel/privacidade/portabilidade`);
        await expect(page.getByRole('heading', { name: /portabilidade/i })).toBeVisible();

        // Nova solicitação
        await page.click('button:has-text("+ Nova solicitação")');
        await page.fill('input[name="patient_email"]', 'paciente-teste@example.com');
        await page.click('button:has-text("Criar")');
        await expect(page.getByText(/criada com sucesso/i)).toBeVisible();

        // Executa
        const row = page.locator('tr', { hasText: 'paciente-teste@example.com' });
        await row.getByRole('button', { name: /executar/i }).click();
        await expect(page.getByText(/arquivo gerado/i)).toBeVisible({ timeout: 30_000 });

        // Download via URL assinada (TTL 7d documentado no card)
        const [download] = await Promise.all([
            page.waitForEvent('download'),
            row.getByRole('link', { name: /baixar/i }).click(),
        ]);

        const filename = download.suggestedFilename();
        expect(filename).toMatch(/portability.*\.json$/);
    });
});
