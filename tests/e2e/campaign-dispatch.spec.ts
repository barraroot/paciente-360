import { test, expect } from '@playwright/test';

/**
 * **T282 (Fase 8 — Polish)** — E2E Cenário 1 do quickstart.
 *
 * Pré-requisitos:
 *   - Sail up + migrate fresh com DevSeeder
 *   - Tenant `clinica-alfa` + admin-clinica logado
 *   - ≥3 pacientes elegíveis para reativação
 *
 * Fluxo:
 *   1. Admin acessa /panel/campanhas → clica "+ Nova Campanha"
 *   2. Preenche form (canal, template, segmento)
 *   3. Preview mostra contagem de elegíveis + warnings de compliance
 *   4. Dispatch → status muda para `dispatching`
 *   5. Após processamento, relatório mostra taxa de delivery
 */
test.describe('Campanhas — Cenário 1 quickstart', () => {
    const baseUrl = 'http://clinica-alfa.lvh.me';
    const adminEmail = 'admin@clinica-alfa.com.br';
    const adminPassword = 'password';

    async function login(page) {
        await page.goto(`${baseUrl}/login`);
        await page.fill('input[name="email"]', adminEmail);
        await page.fill('input[name="password"]', adminPassword);
        await page.click('button[type="submit"]');
        await page.waitForURL(`${baseUrl}/panel`, { timeout: 10_000 });
    }

    test('create_preview_dispatch_report_pipeline', async ({ page }) => {
        await login(page);

        // 1. Lista de campanhas
        await page.goto(`${baseUrl}/panel/campanhas`);
        await expect(page.getByRole('heading', { name: /campanhas/i })).toBeVisible();

        // 2. Criar
        await page.click('text=+ Nova Campanha');
        await page.waitForURL(/campanhas\/nova/);
        await page.fill('input[name="name"]', 'Reativação Outubro');
        await page.selectOption('select[name="channel"]', 'whatsapp');
        await page.fill('textarea[name="message_template"]', 'Olá {{nome}}, sentimos sua falta!');
        await page.click('button:has-text("Salvar")');

        // 3. Preview
        await page.click('button:has-text("Preview")');
        await expect(page.getByText(/destinatários elegíveis/i)).toBeVisible({ timeout: 5000 });

        // 4. Dispatch
        await page.click('button:has-text("Disparar")');
        await page.getByRole('button', { name: /confirmar/i }).click();
        await expect(page.getByText(/disparada/i)).toBeVisible({ timeout: 10_000 });

        // 5. Relatório
        await page.click('text=Ver relatório');
        await expect(page.getByText(/taxa de entrega/i)).toBeVisible();
    });
});
