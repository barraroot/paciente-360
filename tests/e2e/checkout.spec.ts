import { test, expect } from '@playwright/test';

/**
 * T312 — E2E Checkout Stripe (test mode)
 *
 * ⚠️ IMPORTANTE:
 *  - Requer Stripe Test Keys em .env:
 *    STRIPE_KEY=pk_test_...
 *    STRIPE_SECRET=sk_test_...
 *  - Para webhooks locais, é necessário `stripe listen --forward-to`.
 *    Como isso é executado manualmente, este teste usa test.skip()
 *    se as chaves não estiverem configuradas.
 *
 * Pré-requisitos:
 *  - Servidor Laravel rodando: `vendor/bin/sail up -d`
 *  - DB com DevSeeder: `vendor/bin/sail artisan migrate:fresh --seed --seeder=DevSeeder`
 *  - Tenant clinica-beta está em trial (sem assinatura)
 *
 * Caminhos testados:
 *  1. Login como admin-clinica@clinica-beta.lvh.me
 *  2. Navega para /panel/billing/plans
 *  3. Clica "Assinar" no plano pro; preenche professionals_quantity=2
 *  4. Clica "Continuar para pagamento"
 *  5. Preenche dados do cartão (Stripe test)
 *  6. Espera redirect para /billing/success?session_id=...
 *  7. Verifica status do tenant (se webhook disponível)
 */

test.describe('Stripe Checkout', () => {
  const baseUrl = 'http://clinica-beta.lvh.me';
  const adminEmail = 'admin-clinica@clinica-beta.com.br';
  const adminPassword = 'password123';

  test.skip(
    !process.env.STRIPE_KEY || !process.env.STRIPE_SECRET,
    'Stripe Test Keys não configuradas em .env'
  );

  test('should complete stripe checkout for plan upgrade', async ({ page }) => {
    // Step 1: Login
    await page.goto(`${baseUrl}/login`);

    await page.fill('input[name="email"]', adminEmail);
    await page.fill('input[name="password"]', adminPassword);

    const submitButton = page.locator('button[type="submit"]');
    await submitButton.click();

    await page.waitForURL(`${baseUrl}/panel`, { timeout: 10000 });

    // Step 2: Navega para /panel/billing
    await page.goto(`${baseUrl}/panel/billing/plans`);

    // Step 3: Clica "Assinar" no plano pro
    const proCard = page.locator('text=/pro/i').first();
    const subscribeButton = proCard.locator('button:has-text("Assinar"), button:has-text("Subscribe")');
    await subscribeButton.click();

    // Preenche professionals_quantity (se modal/form aparecer)
    const quantityInput = page.locator('input[name="professionals_quantity"]');
    if (await quantityInput.isVisible()) {
      await quantityInput.fill('2');
    }

    // Step 4: Clica para pagamento
    const checkoutButton = page.locator('button:has-text("Pagamento"), button:has-text("Checkout"), button:has-text("Continuar")');
    await checkoutButton.click();

    // Step 5: Preenche dados do cartão na página Stripe
    // Espera que a página redirecione para checkout.stripe.com
    const stripeFrame = page.frameLocator('iframe[title*="Stripe"]');

    // Cartão de teste Stripe
    const cardInput = stripeFrame.locator('input[name="cardnumber"]');
    if (await cardInput.isVisible({ timeout: 5000 }).catch(() => false)) {
      await cardInput.fill('4242424242424242');
      await stripeFrame.locator('input[name="exp-date"]').fill('12/30');
      await stripeFrame.locator('input[name="cvc"]').fill('123');
      await stripeFrame.locator('input[name="postal"]').fill('12345');
    }

    // Clica "Pay" ou "Confirmar"
    const payButton = page.locator('button:has-text("Pay"), button:has-text("Confirmar"), button[role="button"]');
    await payButton.click();

    // Step 6: Espera redirect para /billing/success
    await page.waitForURL(`${baseUrl}/billing/success`, { timeout: 15000 });

    expect(page.url()).toContain('success');
    expect(page.url()).toContain('session_id');
  });
});
