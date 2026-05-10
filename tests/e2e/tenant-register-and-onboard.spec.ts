import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * T310 — E2E Cadastro → onboarding → login
 *
 * Pré-requisitos:
 *  - Servidor Laravel rodando: `vendor/bin/sail up -d`
 *  - DB limpo e seeded: execute antes de rodar este teste:
 *    `vendor/bin/sail artisan migrate:fresh --seed`
 *  - URLs resolvem via `lvh.me` (DNS público, 127.0.0.1)
 *
 * Caminhos testados:
 *  1. Navega para http://crm.lvh.me/register-clinic
 *  2. Preenche form: clinic_name, cnpj, responsible_*, password, slug
 *  3. Submete; espera redirect para novo tenant slug
 *  4. Loga com credenciais recém-criadas
 *  5. Completa onboarding
 *  6. Logout + login de novo; espera ir direto para /panel
 */

test.describe('Tenant Registration and Onboarding', () => {
  const timestamp = Date.now();
  const slug = `clinica-teste-${timestamp}`;
  const email = `admin-${timestamp}@example.com`;
  const password = 'P@ssw0rd!Str0ng';
  const cnpj = '11222333000181'; // CNPJ válido (fábrica)

  let baseUrl = 'http://crm.lvh.me';
  let newTenantUrl = '';

  test.beforeAll(() => {
    // Reset DB antes de rodar os testes
    console.log('🗑️  Resetting database...');
    try {
      execSync('vendor/bin/sail artisan migrate:fresh --seed', {
        stdio: 'inherit',
        cwd: process.cwd(),
      });
    } catch (error) {
      console.error('⚠️ Database reset failed. Ensure DB is running.');
      process.exit(1);
    }
  });

  test('should register a new clinic and redirect to login page', async ({ page }) => {
    await page.goto(`${baseUrl}/register-clinic`);

    // Preenche form de cadastro
    await page.fill('input[name="clinic_name"]', 'Clínica Teste');
    await page.fill('input[name="cnpj"]', cnpj);
    await page.fill('input[name="responsible_name"]', 'Dr. João Silva');
    await page.fill('input[name="responsible_email"]', email);
    await page.fill('input[name="responsible_phone"]', '1133334444');
    await page.fill('input[name="password"]', password);
    await page.fill('input[name="password_confirmation"]', password);
    await page.fill('input[name="slug"]', slug);

    // Aceita termos
    await page.check('input[name="terms_accepted"]');

    // Submete
    const submitButton = page.locator('button[type="submit"]');
    await submitButton.click();

    // Espera redirect para login do novo tenant
    newTenantUrl = `http://${slug}.lvh.me`;
    await page.waitForURL(`${newTenantUrl}/login`, { timeout: 10000 });

    expect(page.url()).toContain(slug);
  });

  test('should login with newly created credentials', async ({ page }) => {
    await page.goto(`${newTenantUrl}/login`);

    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);

    const submitButton = page.locator('button[type="submit"]');
    await submitButton.click();

    // Espera redirect para onboarding (por estar incomplete)
    await page.waitForURL(`${newTenantUrl}/panel/onboarding`, { timeout: 10000 });

    expect(page.url()).toContain('onboarding');
  });

  test('should complete onboarding wizard', async ({ page }) => {
    await page.goto(`${newTenantUrl}/panel/onboarding`);

    // Preenche step "clinic_data"
    await page.fill('input[name="address"]', 'Rua Exemplo, 123');
    await page.fill('input[name="city"]', 'São Paulo');
    await page.fill('input[name="state"]', 'SP');
    await page.fill('input[name="phone"]', '1133334444');

    // Submete o step
    const submitButton = page.locator('button:has-text("Próximo")');
    await submitButton.click();

    // Espera mostrar sucesso ou próxima step
    // Se onboarding_completed=true, redireciona para /panel
    await page.waitForURL(
      (url) => url.toString().includes('/panel') && !url.toString().includes('onboarding'),
      { timeout: 10000 }
    );

    expect(page.url()).not.toContain('onboarding');
  });

  test('should redirect to panel on re-login (onboarding complete)', async ({ page }) => {
    // Logout (se necessário)
    const logoutButton = page.locator('button:has-text("Sair")');
    if (await logoutButton.isVisible()) {
      await logoutButton.click();
      await page.waitForURL(`${newTenantUrl}/login`);
    }

    // Login de novo
    await page.goto(`${newTenantUrl}/login`);

    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);

    const submitButton = page.locator('button[type="submit"]');
    await submitButton.click();

    // Espera ir direto para /panel (onboarding agora completo)
    await page.waitForURL(`${newTenantUrl}/panel`, { timeout: 10000 });

    expect(page.url()).not.toContain('onboarding');
    expect(page.url()).toContain('/panel');
  });
});
