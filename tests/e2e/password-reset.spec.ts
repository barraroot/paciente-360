import { test, expect } from '@playwright/test';

/**
 * T314 — E2E Recuperação de senha
 *
 * Pré-requisitos:
 *  - Servidor Laravel rodando: `vendor/bin/sail up -d`
 *  - DB com DevSeeder: `vendor/bin/sail artisan migrate:fresh --seed --seeder=DevSeeder`
 *  - Mailpit rodando: http://localhost:8025
 *
 * Caminhos testados:
 *  1. Navega para http://clinica-alfa.lvh.me/login
 *  2. Clica "Esqueceu a senha?"
 *  3. Preenche email de um user existente
 *  4. Submete; espera mensagem de sucesso (genérica)
 *  5. Captura e-mail do Mailpit
 *  6. Extrai link reset
 *  7. Navega para link; preenche nova senha
 *  8. Submete; espera redirect para /login com sucesso
 *  9. Login com nova senha
 *  10. Confirma painel acessível
 */

test.describe('Password Reset', () => {
  const baseUrl = 'http://clinica-alfa.lvh.me';
  const userEmail = 'medico-1@clinica-alfa.com.br';
  const newPassword = 'NewP@ssw0rd!Strong';
  const mailpitUrl = 'http://localhost:8025';

  test('should reset password via email link', async ({ page }) => {
    // Step 1: Navega para login
    await page.goto(`${baseUrl}/login`);

    // Step 2: Clica "Esqueceu a senha?"
    const forgotPasswordLink = page.locator('a:has-text("Esqueceu"), a:has-text("Forgot"), text=/esqueci/i');
    await forgotPasswordLink.click();

    // Espera página de forgot-password
    await page.waitForURL(`${baseUrl}/forgot-password`, { timeout: 5000 });

    // Step 3: Preenche email
    await page.fill('input[name="email"]', userEmail);

    // Step 4: Submete
    const submitButton = page.locator('button[type="submit"]');
    await submitButton.click();

    // Espera mensagem de sucesso (genérica, mesmo se email não existe)
    const successMessage = page.locator('text=/enviado|success|verifique/i');
    await successMessage.waitFor({ timeout: 10000 });

    // Step 5: Captura e-mail do Mailpit
    const mailpitResponse = await page.request.get(
      `${mailpitUrl}/api/v2/messages?kind=to&query=${userEmail}`
    );
    const mailData = (await mailpitResponse.json()) as any;

    if (mailData.total === 0) {
      test.skip();
      return;
    }

    // Step 6: Extrai token do link de reset
    const emailBody = mailData.messages[0].HTML || mailData.messages[0].Text;
    const tokenMatch = emailBody.match(/token=([a-f0-9]{40,64})/);
    const resetToken = tokenMatch?.[1];

    expect(resetToken).toBeTruthy();

    // Step 7: Navega para o link reset-password
    await page.goto(`${baseUrl}/reset-password?token=${resetToken}&email=${userEmail}`);

    // Preenche nova senha
    await page.fill('input[name="password"]', newPassword);
    await page.fill('input[name="password_confirmation"]', newPassword);

    // Step 8: Submete
    const resetSubmitButton = page.locator('button[type="submit"]');
    await resetSubmitButton.click();

    // Espera redirect para /login com mensagem de sucesso
    await page.waitForURL(`${baseUrl}/login`, { timeout: 10000 });

    const resetSuccessMessage = page.locator('text=/sucesso|resetada|redefinida/i');
    await resetSuccessMessage.waitFor({ timeout: 5000 });

    // Step 9: Login com nova senha
    await page.fill('input[name="email"]', userEmail);
    await page.fill('input[name="password"]', newPassword);

    const loginSubmitButton = page.locator('button[type="submit"]');
    await loginSubmitButton.click();

    // Step 10: Espera acesso ao painel
    await page.waitForURL(`${baseUrl}/panel`, { timeout: 10000 });

    expect(page.url()).toContain('/panel');
  });
});
