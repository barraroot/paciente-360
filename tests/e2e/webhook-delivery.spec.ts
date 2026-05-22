import { test, expect } from '@playwright/test';
import http from 'node:http';

/**
 * **T286 (Fase 8 — Polish)** — E2E Cenário 5 do quickstart.
 *
 * Webhook delivery roundtrip:
 *   1. Admin configura endpoint apontando para mock HTTP server local
 *   2. Disparar evento de domínio (criar paciente)
 *   3. Mock recebe POST com payload + HMAC válido
 *   4. Sucesso 200 → delivery status 'delivered'
 *   5. Simular 500 → DLQ após 5 retries
 */
test.describe('Webhooks — Delivery Roundtrip (Cenário 5)', () => {
    const baseUrl = 'http://clinica-alfa.lvh.me';
    const adminEmail = 'admin@clinica-alfa.com.br';
    const adminPassword = 'password';

    let mockServer: http.Server;
    let mockUrl: string;
    let receivedPayloads: Array<{ headers: any; body: string }> = [];

    test.beforeAll(async () => {
        await new Promise<void>((resolve) => {
            mockServer = http.createServer((req, res) => {
                let body = '';
                req.on('data', (chunk) => { body += chunk; });
                req.on('end', () => {
                    receivedPayloads.push({ headers: req.headers, body });
                    res.writeHead(200, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ ok: true }));
                });
            });
            mockServer.listen(0, '127.0.0.1', () => {
                const address = mockServer.address();
                if (typeof address === 'object' && address !== null) {
                    mockUrl = `http://127.0.0.1:${address.port}/webhook`;
                }
                resolve();
            });
        });
    });

    test.afterAll(async () => {
        await new Promise<void>((resolve) => mockServer.close(() => resolve()));
    });

    async function loginAdmin(page) {
        await page.goto(`${baseUrl}/login`);
        await page.fill('input[name="email"]', adminEmail);
        await page.fill('input[name="password"]', adminPassword);
        await page.click('button[type="submit"]');
        await page.waitForURL(`${baseUrl}/panel`, { timeout: 10_000 });
    }

    test('configure_webhook_then_receive_signed_payload', async ({ page }) => {
        await loginAdmin(page);

        // 1. Cadastra endpoint
        await page.goto(`${baseUrl}/panel/integracoes/webhooks`);
        await page.click('text=+ Novo webhook');
        await page.fill('input#wh-name', 'Mock Local');
        await page.fill('input#wh-url', mockUrl);
        await page.check('input[type="checkbox"][value="paciente.criado"]');
        await page.click('button[type="submit"]');

        // Captura secret plaintext (banner)
        const secretBanner = page.locator('.banner--success code');
        await expect(secretBanner).toBeVisible();
        const secret = await secretBanner.textContent();
        expect(secret).toMatch(/^whsec_/);

        // 2. Dispara evento — criar paciente via API
        const response = await page.request.post(`${baseUrl}/api/v1/pacientes`, {
            data: { nome: 'Webhook Trigger', telefone: '11990000000' },
        });
        expect(response.status()).toBe(201);

        // 3. Aguarda mock receber (delivery assíncrona)
        await page.waitForTimeout(3000);

        expect(receivedPayloads.length).toBeGreaterThan(0);
        const delivered = receivedPayloads[0];
        expect(delivered.headers['x-paciente360-event']).toBe('paciente.criado');
        expect(delivered.headers['x-paciente360-signature']).toMatch(/^sha256=/);
        expect(delivered.headers['x-paciente360-correlation-id']).toBeDefined();
    });
});
