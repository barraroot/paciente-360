# Onboarding — Setup ambiente de desenvolvimento

**Para devs novos (incluindo você no servidor remoto após migração)**.

Stack: PHP 8.5 + Laravel 13 + PostgreSQL 18 + Redis 7 + Vue 3 + Tailwind v4 + Reverb + Horizon. Toda a orquestração via **Laravel Sail** (Docker Compose).

Tempo estimado: ~30 min do clone ao test suite verde.

---

## Pré-requisitos

| Software | Versão mínima | Verificação |
|---|---|---|
| Docker | 24.0+ | `docker --version` |
| Docker Compose | v2 (plugin) | `docker compose version` |
| Git | 2.40+ | `git --version` |

(Não precisa PHP/Composer/Node instalados localmente — Sail roda tudo dentro de containers.)

**WSL2 (Windows)**: garanta que está em filesystem Linux (`/home/...`), NÃO em `/mnt/c/...` — performance de IO degradada.

---

## 1. Clone

```bash
git clone https://github.com/barraroot/paciente-360.git
cd paciente-360
```

Branches relevantes em desenvolvimento:
- `main` — base estável (Fases 0-4 mergeadas)
- `005-agendamento-consultas` — Fase 5 (Agendamento) aguardando smoke E2E + merge
- `006-agenda-ux-polish` — Fase 6 (UX Polish) aguardando smoke visual + PR

Para começar a contribuir:
```bash
git checkout 006-agenda-ux-polish  # ou outra branch ativa
```

---

## 2. Configurar `.env`

```bash
cp .env.example .env
```

**Editar manualmente** (ou via secret manager) os valores sensíveis:

### Mínimos para subir (sem integração externa)
```dotenv
APP_NAME="Paciente360"
APP_ENV=local
APP_KEY=               # gerado abaixo via artisan key:generate
APP_DEBUG=true
APP_URL=http://api.lvh.me

DB_CONNECTION=pgsql
DB_HOST=pgsql          # nome do container Sail
DB_PORT=5432
DB_DATABASE=paciente_360
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis
SESSION_DRIVER=database

REVERB_APP_ID=paciente360
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=reverb
REVERB_PORT=8080
```

### Opcionais (integração externa — preencher se for testar)
```dotenv
# Stripe (Fase 0 — Billing)
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

# Twilio (Fase 3 — WhatsApp)
TWILIO_SID=
TWILIO_AUTH_TOKEN=
TWILIO_FROM=

# Meta Instagram (Fase 3)
META_APP_ID=
META_APP_SECRET=
META_VERIFY_TOKEN=

# Google Calendar (Fase 5 — US-6.7)
GOOGLE_CALENDAR_CLIENT_ID=
GOOGLE_CALENDAR_CLIENT_SECRET=
GOOGLE_CALENDAR_REDIRECT_URI="${APP_URL}/api/v1/agenda/calendar-sync/google/callback"
GOOGLE_CALENDAR_WEBHOOK_BASE_URL="${APP_URL}/webhooks/google-calendar"
AGENDA_CALENDAR_SYNC_WINDOW_DAYS=60
AGENDA_WATCH_CHANNEL_RENEW_HOURS=48
CSP_GOOGLE_HOSTS="https://accounts.google.com https://oauth2.googleapis.com https://www.googleapis.com"
```

Para configurar Google OAuth Client em GCP (necessário para US-6.7 funcionar): ver `specs/005-agendamento-consultas/quickstart.md` §2.

---

## 3. Subir containers Sail

Primeira vez (build da imagem custom Paciente360):

```bash
# Necessário Composer instalado UMA vez para o vendor/bin/sail existir
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php85-composer:latest \
    composer install --ignore-platform-reqs

vendor/bin/sail up -d --build
```

Próximas vezes:
```bash
vendor/bin/sail up -d
```

Confirmar 6 containers healthy:
```bash
vendor/bin/sail ps
# Esperado: paciente-360-{horizon,laravel.test,mailpit,pgsql,redis,reverb}-1
```

---

## 4. Gerar APP_KEY + migrations + seeders

```bash
# Chave APP_KEY (uma vez)
vendor/bin/sail artisan key:generate

# Migrations
vendor/bin/sail artisan migrate

# Seeders default (idempotente — Roles + Plans + SuperAdmin + AgendaPermissions)
vendor/bin/sail artisan db:seed
```

**Para popular dados de teste** (2 tenants exemplo, usuários, pacientes):
```bash
vendor/bin/sail artisan db:seed --class=DevSeeder
```

---

## 5. Instalar deps frontend

```bash
vendor/bin/sail npm install --legacy-peer-deps
```

(`--legacy-peer-deps` necessário por conflict pré-existente entre `vite ^8.0.0` e `@vitejs/plugin-vue ^5.2.1` — documentado em `specs/005-agendamento-consultas/research.md` R2.)

Vite dev server (HMR para Vue):
```bash
vendor/bin/sail npm run dev
```

OU build estático:
```bash
vendor/bin/sail npm run build
```

---

## 6. Validar suite de tests

```bash
vendor/bin/sail artisan test --compact
```

Esperado (na branch `006-agenda-ux-polish`):
```
Tests: 1167 passed (1164 OK, 3 skipped, 1 incomplete, 5 risky)
Duration: ~240s
```

Se algum test falha → reporte antes de prosseguir.

---

## 7. Acessar app no browser

Subdomínio default usado em dev: `lvh.me` (resolve para 127.0.0.1).

Adicione ao `/etc/hosts` do servidor:
```
127.0.0.1   crm.lvh.me api.lvh.me app.lvh.me
```

URLs:
- API: `http://api.lvh.me/api/v1` (Bearer auth)
- SPA tenant: `http://app.lvh.me`
- Filament super admin: `http://crm.lvh.me/admin`
- Mailpit (emails capturados em dev): `http://localhost:8025`
- Horizon dashboard: `http://api.lvh.me/horizon`

---

## 8. Login inicial

Super Admin (criado pelo SuperAdminSeeder):
```
Email: superadmin@paciente360.com.br
Senha: password   # default — TROCAR em produção
```

Para criar tenant de teste manualmente:
```bash
vendor/bin/sail artisan tinker --execute '
  $tenant = \App\Models\Tenant::factory()->create(["slug" => "clinica-teste"]);
  $admin = \App\Models\User::factory()->create([
    "tenant_id" => $tenant->id,
    "email" => "admin@clinica-teste.test",
    "password" => bcrypt("password"),
  ]);
  $admin->assignRole("admin-clinica");
  echo "Tenant: {$tenant->slug} | Admin: {$admin->email} / password\n";
'
```

---

## 9. Comandos úteis dia a dia

```bash
# Tail logs estruturados
vendor/bin/sail artisan pail

# Tinker (REPL Laravel)
vendor/bin/sail artisan tinker

# Listar rotas API filtradas
vendor/bin/sail artisan route:list --path=agenda

# Listar schedule (cron jobs)
vendor/bin/sail artisan schedule:list

# Rodar 1 cron job manualmente
vendor/bin/sail artisan agenda:dispatch-confirmations

# Pint (formatador PHP)
vendor/bin/sail bin pint --dirty --format agent

# Test específico
vendor/bin/sail artisan test --compact tests/Feature/Agenda/SlotConflictRaceTest.php

# Refazer DB do zero (CUIDADO — apaga tudo)
vendor/bin/sail artisan migrate:fresh --seed
```

---

## 10. Troubleshooting

### Sail não sobe — porta ocupada
```bash
# Verifica o que tá usando porta 80
sudo lsof -i :80
# Para containers de outros projetos
docker stop $(docker ps -q)
```

### `npm install` falha com conflict de peer dep
Use `--legacy-peer-deps` sempre. Está documentado.

### Migrations falham com "extension btree_gist does not exist"
PostgreSQL 18 deve ter extension disponível. Se faltar:
```bash
vendor/bin/sail psql -c 'CREATE EXTENSION IF NOT EXISTS btree_gist;'
```

### Tests passam local mas falham em CI
Garanta que `.env.testing` está correto e que o DB testing existe:
```bash
vendor/bin/sail artisan migrate --env=testing
```

---

## Continuidade pós-migração (Claude)

Se você é Claude lendo este arquivo após uma migração de servidor: também leia **`docs/claude-context/REHYDRATE-INSTRUCTIONS.md`** para recriar a memória local antes de continuar trabalho.
