import { test, expect } from '@playwright/test';

/**
 * **T283 (Fase 8 — Polish)** — E2E Cenário 2 do quickstart.
 *
 * Direito ao Esquecimento (LGPD Q26):
 *   1. Paciente submete solicitação via formulário público (sem login)
 *   2. Admin Clínica vê solicitação pendente no painel
 *   3. Executa anonimização
 *   4. Paciente fica com nome="<paciente anonimizado>"; mensagens preservadas
 *      com banner "Conteúdo anonimizado (LGPD)"
 */
test.describe('Privacidade — Direito ao Esquecimento (Cenário 2)', () => {
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

    test('patient_request_then_admin_executes_anonymization', async ({ page }) => {
        // 1. Solicitação pública (sem login)
        await page.goto(`${baseUrl}/privacidade/esquecimento/publico`);
        await page.fill('input[name="email"]', 'paciente-teste@example.com');
        await page.fill('input[name="cpf"]', '12345678900');
        await page.click('button:has-text("Solicitar")');
        await expect(page.getByText(/solicitação registrada/i)).toBeVisible({ timeout: 5000 });

        // 2. Admin executa
        await loginAdmin(page);
        await page.goto(`${baseUrl}/panel/privacidade/esquecimento`);
        await expect(page.getByRole('heading', { name: /direito ao esquecimento/i })).toBeVisible();

        const pendingRow = page.locator('tr', { hasText: 'paciente-teste@example.com' });
        await pendingRow.getByRole('button', { name: /executar/i }).click();
        await page.getByRole('button', { name: /confirmar.*anonimiz/i }).click();
        await expect(page.getByText(/anonimização aplicada/i)).toBeVisible({ timeout: 10_000 });

        // 3. Verificar timeline preservada com banner LGPD
        await page.goto(`${baseUrl}/panel/pacientes`);
        const anonRow = page.locator('tr', { hasText: '<paciente anonimizado>' });
        await expect(anonRow).toBeVisible();
        await anonRow.click();
        await expect(page.getByText(/conteúdo anonimizado.*lgpd/i)).toBeVisible();
    });
});
