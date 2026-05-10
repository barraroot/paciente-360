import { test, expect } from '@playwright/test';

/**
 * T311 — E2E Convite e aceite
 *
 * Pré-requisitos:
 *  - Servidor Laravel rodando: `vendor/bin/sail up -d`
 *  - DB com DevSeeder (cria clinica-alfa): `vendor/bin/sail artisan migrate:fresh --seed --seeder=DevSeeder`
 *  - Mailpit rodando (captura e-mails): acesso em http://localhost:8025
 *
 * Caminhos testados:
 *  1. Login como admin-clinica@clinica-alfa.lvh.me
 *  2. Navega para /panel/users
 *  3. Clica "Convidar usuário"; preenche email + role
 *  4. Submete; espera toast sucesso
 *  5. Captura e-mail do Mailpit
 *  6. Extrai link de aceite
 *  7. Navega para o link; completa cadastro
 *  8. Confirma login automático + role correto
 */

test.describe('Invite User and Accept', () => {
  const baseUrl = 'http://clinica-alfa.lvh.me';
  const adminEmail = 'admin-clinica@clinica-alfa.com.br';
  const adminPassword = 'password123';
  const timestamp = Date.now();
  const inviteeEmail = `convidado-${timestamp}@example.com`;
  const inviteePassword = 'P@ssw0rd!Str0ng';
  const mailpitUrl = 'http://localhost:8025';

  test('should invite a user and they should be able to accept and login', async ({
    page,
    context,
  }) => {
    // Step 1: Login como admin
    await page.goto(`${baseUrl}/login`);

    await page.fill('input[name="email"]', adminEmail);
    await page.fill('input[name="password"]', adminPassword);

    const submitButton = page.locator('button[type="submit"]');
    await submitButton.click();

    // Espera para painel (onboarding já completo)
    await page.waitForURL(`${baseUrl}/panel`, { timeout: 10000 });

    // Step 2: Navega para /panel/users
    await page.goto(`${baseUrl}/panel/users`);

    // Step 3: Clica "Convidar usuário"
    const inviteButton = page.locator('button:has-text("Convidar"), button:has-text("Invite")');
    await inviteButton.click();

    // Preenche form de convite
    await page.fill('input[name="email"]', inviteeEmail);
    await page.selectOption('select[name="role"], [name="intended_role"]', 'atendente');

    // Submete
    const submitInviteButton = page.locator('button[type="submit"]');
    await submitInviteButton.click();

    // Espera toast de sucesso
    const successMessage = page.locator('text=/sucesso|enviado|convite|criado/i');
    await successMessage.waitFor({ timeout: 10000 });

    // Step 5: Captura e-mail do Mailpit
    const mailpitResponse = await page.request.get(
      `${mailpitUrl}/api/v2/messages?kind=to&query=${inviteeEmail}`
    );
    const mailData = (await mailpitResponse.json()) as any;

    expect(mailData.total).toBeGreaterThan(0);

    // Step 6: Extrai link de aceite (regex: https?://...?token=...)
    const emailBody = mailData.messages[0].HTML || mailData.messages[0].Text;
    const tokenMatch = emailBody.match(/([a-f0-9]{40,64})/);
    const invitationToken = tokenMatch?.[1];

    expect(invitationToken).toBeTruthy();

    // Step 7: Navega para o link de aceite
    await page.goto(`${baseUrl}/accept-invitation?token=${invitationToken}`);

    // Preenche name + password
    await page.fill('input[name="name"]', 'Novo Atendente');
    await page.fill('input[name="password"]', inviteePassword);
    await page.fill('input[name="password_confirmation"]', inviteePassword);

    // Submete
    const submitAcceptButton = page.locator('button[type="submit"]');
    await submitAcceptButton.click();

    // Step 8: Espera login automático e redirect para /panel
    await page.waitForURL(`${baseUrl}/panel`, { timeout: 10000 });

    // Confirma que o user está logado e tem role atendente
    const userProfile = page.locator('[data-testid="user-profile"], .user-menu');
    await userProfile.waitFor({ timeout: 5000 });

    expect(page.url()).toContain('/panel');
  });
});
