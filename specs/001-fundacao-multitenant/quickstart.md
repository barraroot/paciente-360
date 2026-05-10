# Quickstart — Fase 0 Fundação Multi-tenant

**Feature**: `001-fundacao-multitenant`
**Para**: novo desenvolvedor que precisa colocar a Fase 0 de pé na própria
máquina e validar que tudo funciona ponta a ponta.

> Pré-requisitos: Docker + Docker Compose, Git, conta de teste no
> Stripe (modo test). PHP/Composer/Node **não** precisam estar
> instalados localmente — tudo roda via Sail.

## 1. Subir o ambiente

```bash
git clone <repo>
cd paciente-360
cp .env.example .env

# Build + up
vendor/bin/sail up -d

# Dentro do container — instalar dependências do PHP e do front
vendor/bin/sail composer install
vendor/bin/sail npm install
```

Serviços que sobem (Compose):

| Serviço | Porta host | Notas |
|---|---|---|
| `nginx` | 80, 443 | Termina TLS em prod; em dev HTTPS opcional via mkcert |
| `php-fpm` | — | Aplicação Laravel |
| `postgres` | 5432 | PostgreSQL 18 |
| `redis` | 6379 | Cache + filas |
| `horizon` | — | Worker de filas (em dev sobe automaticamente) |
| `reverb` | 8080 | WebSocket; **inativo nesta fase** mas configurado |
| `mailpit` | 8025 | Captura de e-mails locais |

## 2. Inicializar a base

```bash
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate --seed
vendor/bin/sail artisan db:seed --class=DevSeeder
```

`DevSeeder` cria:

- Super Admin: `super@paciente360.test` / `password123`
- Tenant **Clínica Alfa** (slug `clinica-alfa`) com:
  - Plano **Starter** já contratado em modo test do Stripe
  - Admin Clínica: `admin@clinica-alfa.test` / `password123`
  - Médico, Atendente, Recepcionista, Financeiro de exemplo
- Tenant **Clínica Beta** (slug `clinica-beta`) ainda em **trial**:
  - Admin Clínica: `admin@clinica-beta.test` / `password123`

## 3. Configurar Stripe em test mode

### Obter Test Keys

1. Acesse [Stripe Dashboard](https://dashboard.stripe.com)
2. Ative Test Mode (canto superior direito)
3. Copie as chaves públicas e secretas

### Configurar .env

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### Testar Webhooks Localmente (opcional)

Para que webhooks `invoice.payment_failed` funcionem em dev, use `stripe-cli`:

```bash
# Instale stripe-cli (macOS/Homebrew)
brew install stripe/stripe-cli/stripe

# Faça login
stripe login

# Forward webhooks para localhost
stripe listen --forward-to http://crm.lvh.me/webhooks/stripe

# Em outro terminal, simule evento
stripe trigger invoice.payment_failed
```

**Nota**: `crm.lvh.me` resolve em `127.0.0.1` via DNS público; nosso `nginx`
roteia para a app corretamente.

## 4. Acessar as superfícies

- **Cadastro de novo tenant** (público): <https://crm.lvh.me/cadastro>
- **SPA do tenant Alfa** (Admin Clínica): <https://clinica-alfa.lvh.me/panel>
- **SPA do tenant Beta** (Admin Clínica em trial): <https://clinica-beta.lvh.me/panel>
- **Filament Super Admin**: <https://crm.lvh.me/admin>
- **Mailpit** (preview de e-mails): <http://localhost:8025>
- **Telescope** (apenas dev/staging): <https://crm.lvh.me/telescope>
- **Horizon dashboard** (filas): <https://crm.lvh.me/horizon>

## 5. Testes E2E (Playwright)

Cada user story tem um teste E2E que cobre o caminho feliz.
Os testes estão em `tests/e2e/` e usam Playwright.

### Executar E2E (requer servidor rodando)

```bash
# Terminal 1: inicia servidor
vendor/bin/sail up -d

# Terminal 2: roda E2E
vendor/bin/sail npm run test:e2e

# Ou diretamente com Playwright
npx playwright test
```

### Testes E2E disponíveis

- **tenant-register-and-onboard.spec.ts** — Cadastro de novo tenant, login e onboarding
- **invite-and-accept.spec.ts** — Convitar usuário, aceitar convite via e-mail (requer Mailpit)
- **checkout.spec.ts** — Checkout Stripe (requer Stripe Test Keys; skip automático se não configurado)
- **password-reset.spec.ts** — Recuperação de senha via e-mail (requer Mailpit)

⚠️ **Nota**: E2E são lentos e dependem de infraestrutura externa (Mailpit, subdomínios DNS).
Por isso, ficam fora do CI principal — rodem manualmente ou em job separado.

## 5b. Validar a fase ponta a ponta (PHPUnit)

```bash
vendor/bin/sail artisan test --compact
```

Caminhos manuais para confirmar:

### US-1.1 Cadastro de Nova Clínica
1. Acesse <https://crm.lvh.me/cadastro>.
2. Preencha CNPJ válido (ex.: `12345678000195`), nome, e-mail novo, senha forte.
3. Aceite Termos e Política.
4. Confirme: tenant criado, e-mail de boas-vindas no Mailpit, redirect
   para `<slug>.lvh.me/panel`.

### US-1.2 Onboarding
5. No painel da Clínica Beta (recém-criado), confirme que o wizard aparece.
6. Complete a etapa "dados da clínica"; demais etapas estão `locked`.
7. Recarregue o navegador — wizard deve continuar do mesmo ponto.

### US-1.3 Assinatura
8. Em Beta (trial), vá para `/panel/billing` → "Assinar plano".
9. Use cartão de teste `4242 4242 4242 4242` qualquer CVC/data.
10. Após confirmação Stripe, status do tenant vira `active` (acompanhe via
    `/admin` Super Admin).

### US-1.4 Upgrade/Downgrade
11. Em Alfa, vá para `/panel/billing` → adicione 1 profissional.
12. Verifique que `professionals_quantity` aumentou e proration apareceu
    no histórico.

### US-1.5 Monitoramento Cota IA
13. Em Alfa, `/panel/billing/ai-usage` → painel exibe cota inclusa,
    consumo zero e projeção.
14. Configure hard cap de `1000`; verifique persistência no GET.

### US-2.1 Login
15. Faça logout. Acesse `/panel/login` em `clinica-alfa.lvh.me`.
16. Login com `medico@clinica-alfa.test` / `password123`.
17. Habilite 2FA: scan QR no Google Authenticator → confirme com primeiro código.
18. Logout → login → exige TOTP.

### US-2.2 Cadastro de Usuários Internos
19. Como Admin Clínica, em `/panel/users` → convide novo Atendente.
20. Verifique e-mail no Mailpit, abra link → defina senha.
21. Login com a nova credencial: vê apenas o que perfil Atendente permite.

### US-2.3 Recuperação de Senha
22. Em `/panel/login`, "Esqueci minha senha" → digite e-mail.
23. Mailpit recebe e-mail; abra link → defina nova senha.
24. Login com nova senha funciona; senha antiga falha.

### US-2.4 Log de Auditoria
25. Como Admin Clínica, vá para `/panel/audit-logs`.
26. Veja eventos disparados pelos passos acima (`tenant.registered`,
    `user.invited`, `user.accepted_invitation`, `subscription.created`,
    `user.login.succeeded`, `user.password.reset` etc.).
27. Aplique filtro por usuário → resultado filtrado.
28. Clique "Exportar CSV" → arquivo baixa, valores escapados (sem
    vulnerabilidade de injeção em planilha).

## 6. Testes automatizados

```bash
# Todos os testes (mínimo 70% cobertura — Princípio IV)
vendor/bin/sail artisan test --coverage --min=70

# Apenas testes de isolamento (gate obrigatório)
vendor/bin/sail artisan test --filter=Tenant\\IsolationTest

# Apenas testes da fase
vendor/bin/sail artisan test tests/Feature/Fase0
```

## 7. Comandos úteis

```bash
# Lint + format
vendor/bin/sail bin pint --dirty --format agent

# Reset total da DB de dev
vendor/bin/sail artisan migrate:fresh --seed && \
vendor/bin/sail artisan db:seed --class=DevSeeder

# Logs em tempo real (Pail) — modo ad-hoc no container principal
vendor/bin/sail artisan pail

# Logs em tempo real (Pail) — service Compose dedicado (opcional)
# Ativa o profile e mantém um container streaming logs em background.
COMPOSE_PROFILES=pail vendor/bin/sail up -d pail
vendor/bin/sail logs -f pail

# Inspecionar filas
vendor/bin/sail artisan horizon:status

# Rodar job de aplicação de restrições D+7 manualmente
vendor/bin/sail artisan tenants:apply-overdue-restrictions --tenant=clinica-beta
```

## 8. Critério de "feito"

A Fase 0 é considerada entregue quando:

- [ ] Todos os 25 passos manuais acima passam sem warnings.
- [ ] `sail artisan test --coverage --min=70` está verde.
- [ ] `sail npm run test:e2e` está verde.
- [ ] Testes de isolamento multi-tenant rodam em CI e cobrem 100% dos
      endpoints autenticados.
- [ ] `sail bin pint` não tem diffs.
- [ ] Webhook `invoice.payment_failed` testado em loop com `stripe trigger`
      → tenant transita corretamente para `overdue` após 3 falhas.
- [ ] Spec checklist em
      `specs/001-fundacao-multitenant/checklists/requirements.md` 100%
      verde.
