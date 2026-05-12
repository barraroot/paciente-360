# Quickstart: Fase 4 — Token Auth Migration

**Branch**: `004-token-auth-migration` | **Data**: 2026-05-12

Guia para implementar e validar a migração cookie → Bearer token. Cobre dev local (Sail Docker) e deploy decoupled (api + app em domínios distintos).

Pré-requisitos: Fase 3 entregue e funcional em `main` (commit `65dec50`). Sail UP.

---

## 0. Pré-requisitos do projeto

```bash
vendor/bin/sail up -d
git checkout 004-token-auth-migration
vendor/bin/sail composer install
vendor/bin/sail npm install
```

Aderência: **`/speckit.clarify` concluído com 5/5 perguntas** (commit `2d927a6`). Sem NCs pendentes.

---

## 1. Constituição amendment v1.4.0 (PRÉ-REQUISITO — bloqueante)

**ANTES** de qualquer código de auth ser escrito, o amendment da constitution deve ser registrado:

```bash
# 1. Atualizar .specify/memory/constitution.md
#    - Princípio VII adiciona aceite de Bearer Sanctum tokens (formato adicional, não substituto de cookie do Filament)
#    - Mantém TODOS gates existentes (argon2id, TLS 1.3, rate limit, brute force lock)
#    - Adiciona 5 novos gates: token SHA-256 hash, CORS env-driven, CSP estrita prod, DOMPurify obrigatório, audit suspeito
#    - Bump version v1.3.0 → v1.4.0

# 2. Sync impact report no header do constitution.md

# 3. Commit
git commit -m "[Spec Kit] constitution: amend v1.4.0 — accept Bearer tokens for API tenant"
```

Sem esse commit, o gate constitucional do Princípio VII permanece **bloqueado** e implementação não pode prosseguir.

---

## 2. Pre-flight migration: deduplicar emails cross-tenant

**Antes** da migration `UNIQUE (email)` ser aplicada, executar comando de check:

```bash
vendor/bin/sail artisan users:dedupe-emails-cross-tenant --check
```

Output esperado em ambiente limpo:
```
✓ Nenhum email duplicado entre tenants. Migration pode prosseguir.
```

Se houver duplicatas:
```
✗ 3 emails duplicados detectados:

| Email                  | Tenants envolvidos          | Users count | Last login                                    |
|------------------------|-----------------------------|-------------|-----------------------------------------------|
| maria@example.com      | clinica-alfa, clinica-beta  | 2           | clinica-alfa: 2026-05-10; clinica-beta: 2026-04-22 |
| ...                    | ...                         | ...         | ...                                           |

→ Para resolver, execute:
   vendor/bin/sail artisan users:dedupe-emails-cross-tenant --interactive
   (ou --auto para aplicar regra "manter no tenant mais ativo")
```

### Resolução interativa

```bash
vendor/bin/sail artisan users:dedupe-emails-cross-tenant --interactive
```

Para cada email duplicado, pergunta:
1. Qual tenant mantém o email original? (mostra `last_login_at` por tenant)
2. Outros tenants recebem sufixo `.tenant-{slug}` (ex.: `maria@example.com` → `maria@example.com.tenant-clinica-beta`)
3. Confirma? (y/N)

Cada mudança gera audit log + notifica admin do tenant afetado.

### Modo automático

```bash
vendor/bin/sail artisan users:dedupe-emails-cross-tenant --auto
```

Aplica regra "manter no tenant com login mais recente". Outros recebem sufixo. Não interativo.

---

## 3. Aplicar migration UNIQUE email

Após dedup concluído:

```bash
vendor/bin/sail artisan migrate
```

Migration `2026_05_12_HHMMSS_add_unique_email_global_constraint.php` aplica:
```sql
ALTER TABLE users DROP CONSTRAINT users_tenant_id_email_unique;
ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email);
```

Se falhar (duplicatas restantes), revisar comando `users:dedupe-emails-cross-tenant`.

---

## 4. Variáveis de ambiente novas

Adicionar ao `.env` (e exemplar em `.env.example`):

```env
# ──────────────────────────────────────────────────────────────
# Sanctum tokens — Fase 4
# ──────────────────────────────────────────────────────────────
SANCTUM_TOKEN_PREFIX=paciente360_

# Expiration em minutos (default 30d = 43200min). Sliding renewal aplica.
# Override aqui se quiser política diferente por ambiente.
# SANCTUM_EXPIRATION=43200

# ──────────────────────────────────────────────────────────────
# CORS — domínios autorizados a chamar a API
# ──────────────────────────────────────────────────────────────
# Dev local (Vite + lvh.me)
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:3000,http://clinica-alfa.lvh.me,http://clinica-beta.lvh.me

# Staging
# CORS_ALLOWED_ORIGINS=https://staging.app.crm.com.br

# Prod
# CORS_ALLOWED_ORIGINS=https://app.crm.com.br

# ──────────────────────────────────────────────────────────────
# Filament admin domain (preservado em cookie)
# ──────────────────────────────────────────────────────────────
# Em prod: domain ISOLADO do app tenant (cookies não cruzam)
FILAMENT_DOMAIN=crm.com.br
APP_TENANT_DOMAIN=app.crm.com.br
API_TENANT_DOMAIN=api.crm.com.br
```

Limpar config cache:
```bash
vendor/bin/sail artisan config:clear
```

---

## 5. Fluxo manual — Login + uso

### 5.1 Login via curl (smoke test)

```bash
# Sem necessidade de /sanctum/csrf-cookie pre-flight (deprecated nesse fluxo)
curl -X POST http://api.lvh.me/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Origin: http://clinica-alfa.lvh.me" \
  -d '{
    "email": "admin@clinica-alfa.test",
    "password": "password123",
    "device_name": "curl-smoke-test"
  }'
```

Response esperada (201):
```json
{
  "token": "paciente360_a8b3c9d1e7f2...",
  "token_expires_at": "2026-06-11T15:30:00Z",
  "user": {
    "id": 9,
    "name": "Alfa Admin",
    "email": "admin@clinica-alfa.test",
    "roles": ["admin-clinica"],
    "permissions": ["manage-users", "inbox.view", ...]
  },
  "tenant": {
    "id": 7,
    "slug": "clinica-alfa",
    "name": "Clínica Alfa",
    "status": "active",
    "plan": {...}
  }
}
```

### 5.2 Authenticated request

```bash
TOKEN="paciente360_a8b3c9d1e7f2..."

curl -X GET http://api.lvh.me/api/v1/inbox/conversations \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-Slug: clinica-alfa" \
  -H "Accept: application/json"
```

### 5.3 Logout (token corrente)

```bash
curl -X POST http://api.lvh.me/api/v1/auth/logout \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-Slug: clinica-alfa"
```

Response: 204 No Content.

### 5.4 Logout all (todos os tokens)

```bash
curl -X POST http://api.lvh.me/api/v1/auth/logout-all \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-Slug: clinica-alfa"
```

### 5.5 Listar e revogar tokens individuais

```bash
# Lista
curl -X GET http://api.lvh.me/api/v1/auth/tokens \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-Slug: clinica-alfa"

# Revoga específico
curl -X DELETE http://api.lvh.me/api/v1/auth/tokens/123 \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-Slug: clinica-alfa"
```

---

## 6. Postman collection

Criar collection `Paciente360 API.postman_collection.json` com:

1. **Variables** (collection-level):
   - `base_url`: `http://api.lvh.me/api/v1`
   - `token`: (preenchido após login)
   - `tenant_slug`: `clinica-alfa`

2. **Pre-request script** em todas as requests autenticadas:
```js
pm.request.headers.add({
    key: 'Authorization',
    value: `Bearer ${pm.collectionVariables.get('token')}`
});
pm.request.headers.add({
    key: 'X-Tenant-Slug',
    value: pm.collectionVariables.get('tenant_slug')
});
```

3. **Post-response script** no login para auto-salvar token:
```js
const json = pm.response.json();
pm.collectionVariables.set('token', json.token);
pm.collectionVariables.set('tenant_slug', json.tenant.slug);
```

Distribuir junto com a documentação OpenAPI.

---

## 7. Deploy decoupled (api + app domains)

### 7.1 Arquitetura prod alvo

```
┌────────────────────────┐         ┌──────────────────────────┐
│   app.crm.com.br       │  HTTPS  │  api.crm.com.br          │
│   (Vue 3 SPA — CDN)    │ ──────▶ │  (Laravel API)           │
│   Cloudflare Pages     │         │  Laravel Cloud / EC2     │
│   ou Vercel            │         │                          │
└────────────────────────┘         └──────────────────────────┘
                                            │
                                            ├── PostgreSQL 18
                                            ├── Redis 7
                                            ├── Reverb (wss://reverb.crm.com.br)
                                            └── S3 (media bucket)


┌────────────────────────┐
│   crm.com.br/admin     │  cookie session
│   (Filament admin)     │
│   no mesmo host API    │
└────────────────────────┘
```

### 7.2 Build SPA estática

```bash
vendor/bin/sail npm run build
# Output: public/build/

# Para deploy CDN-friendly, build precisa ser independente do backend:
# 1. Build com env de prod
VITE_API_BASE_URL=https://api.crm.com.br/api/v1 \
VITE_REVERB_HOST=reverb.crm.com.br \
VITE_REVERB_PORT=443 \
VITE_REVERB_SCHEME=wss \
VITE_REVERB_APP_KEY=$REVERB_APP_KEY \
vendor/bin/sail npm run build

# 2. Upload public/build/ + index.html para CDN
# (Cloudflare Pages, Vercel, S3+CloudFront, etc.)
```

### 7.3 Backend Laravel deploy

API em `api.crm.com.br`:
- `APP_URL=https://api.crm.com.br`
- `CORS_ALLOWED_ORIGINS=https://app.crm.com.br`
- `SESSION_DOMAIN=null` (não usado para API)
- Filament admin acessível via `https://crm.com.br/admin` (mesma instância Laravel, route group separado)

### 7.4 Cookies isolation

- Filament: `SESSION_DOMAIN=crm.com.br` (strict, sem subdomínio leak)
- API tenant: **sem cookies** (Bearer only)
- SPA app: **localStorage** (não cookie)

---

## 8. Migração de testes existentes (~650)

### 8.1 Pré-flight (preview, sem aplicar)

```bash
vendor/bin/sail artisan tests:migrate-actingas-to-sanctum --preview
```

Output:
```
Analisando 654 arquivos de teste...

Mudanças propostas:
  - tests/Feature/Fase0/Auth/LoginTest.php: 12 ocorrências de actingAs
  - tests/Feature/Fase2/Pacientes/*.php: 89 ocorrências
  - tests/Feature/Fase3/**.php: 234 ocorrências
  - ...

Total: 654 arquivos, 1247 transformações

Edge cases para revisão manual:
  - tests/Feature/Fase0/Auth/SessionExpirationTest.php — usa session diretamente
  - tests/Feature/Fase3/US3_Widget/WidgetPublicConfigTest.php — testa público sem auth

Para aplicar, execute:
  vendor/bin/sail artisan tests:migrate-actingas-to-sanctum --apply
```

### 8.2 Aplicar

```bash
vendor/bin/sail artisan tests:migrate-actingas-to-sanctum --apply --verify
```

`--verify` roda `vendor/bin/sail artisan test --compact` automaticamente após apply.

### 8.3 Rollback (caso necessário)

```bash
git checkout tests/  # Reset todos os tests para HEAD
```

(O comando NÃO faz commits — git serve como rollback natural.)

---

## 9. Reverb broadcast auth com Bearer

### 9.1 Backend (Lote D)

`routes/channels.php` permanece igual — apenas o middleware do endpoint `/broadcasting/auth` muda:

```php
// bootstrap/app.php — em vez de cookie session, agora aceita Bearer
Broadcast::routes(['middleware' => ['auth:sanctum', 'tenant.slug']]);
```

### 9.2 Frontend (echo.js)

```js
// resources/js/echo.js — atualizar authorizer
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    // ... outras opções ...
    authorizer: (channel) => ({
        authorize: (socketId, callback) => {
            const auth = useAuthStore();
            axios.post('/broadcasting/auth', {
                socket_id: socketId,
                channel_name: channel.name,
            }, {
                baseURL: import.meta.env.VITE_API_BASE_URL,
                headers: {
                    'Authorization': `Bearer ${auth.token}`,
                    'X-Tenant-Slug': auth.tenant.slug,
                    'Accept': 'application/json',
                },
            })
            .then(r => callback(null, r.data))
            .catch(e => callback(e, null));
        },
    }),
});
```

### 9.3 Smoke test

1. Login na SPA
2. DevTools → Network → filter `broadcasting/auth`
3. Após login, ao abrir inbox, deve ter request POST com Bearer header + X-Tenant-Slug
4. Response 200 com `{auth: "key:signature"}`
5. WS conecta em `wss://reverb.crm.com.br/app/{key}?...`

---

## 10. Smoke test E2E

```bash
# 1. Sail UP + DevSeeder
vendor/bin/sail artisan migrate:fresh && vendor/bin/sail artisan db:seed --class=DevSeeder

# 2. Login via SPA
# Abrir http://clinica-alfa.lvh.me em browser
# Login: admin@clinica-alfa.test / password123

# 3. DevTools → Application → Local Storage
# Verificar chave: paciente360.auth.token = paciente360_xxx...

# 4. DevTools → Network → filter "auth/me"
# Verificar header Authorization: Bearer paciente360_xxx
# Response 200 com user + tenant

# 5. Logout
# Click logout no menu
# DevTools → Application → Local Storage
# Chave paciente360.auth.token DELETADA
# Redirect para /login

# 6. Tentar usar token antigo via curl
TOKEN_OLD="paciente360_xxx..."
curl -X GET http://api.lvh.me/api/v1/auth/me \
  -H "Authorization: Bearer $TOKEN_OLD" \
  -H "X-Tenant-Slug: clinica-alfa"
# Esperado: 401 Unauthenticated (token revogado)
```

---

## 11. Troubleshooting

| Sintoma | Causa provável | Resolução |
|---|---|---|
| Login 422 `email_already_exists_in_other_tenant` | Migration UNIQUE não aplicada OU dedup pendente | `vendor/bin/sail artisan users:dedupe-emails-cross-tenant --check` |
| Authenticated request 400 `tenant_header_required` | Cliente esqueceu de injetar `X-Tenant-Slug` | Axios interceptor da SPA injeta auto; verificar `stores/auth.js` carregou tenant |
| 403 `tenant_mismatch` | Token user.tenant_id != header tenant.slug | Logout + login. Pode ser token órfão pós-migration |
| CORS error no browser console | Origin não está em `CORS_ALLOWED_ORIGINS` | Adicionar em `.env` + `config:clear` |
| Filament admin não loga após migration | Guard config errada | Confirmar `config/auth.php` guards.web continua session driver |
| Reverb 403 no WS | Echo authorizer não envia Bearer | Verificar `echo.js` lê token do auth store |
| CSP block scripts em prod | `SetSecurityHeaders` aplicou estrita sem nonce em script inline | Vite plugin deve injetar nonce; verificar |
| Sliding expiration não renova | Middleware não aplicado | Confirmar `SlideTokenExpiration` no grupo api após `auth:sanctum` |
| Suspicious token usage falso-positivo | NAT/CGNAT muda IP frequentemente | Aumentar janela de detecção em config (5min → 15min?); apenas alerta, não auto-revoga |

---

## 12. Definição de Pronto

Verificar **antes** de mergear para `main`:

- [ ] Constituição amendment v1.4.0 (Princípio VII — Bearer aceito formato adicional)
- [ ] `POST /api/v1/auth/login` emite token Bearer + retorna user + tenant
- [ ] `POST /api/v1/auth/logout` revoga apenas token corrente
- [ ] `POST /api/v1/auth/logout-all` revoga todos
- [ ] `GET /api/v1/auth/me` aceita Bearer + X-Tenant-Slug
- [ ] `GET/DELETE /api/v1/auth/tokens[/{id}]` funcional
- [ ] Sanctum stateful middleware **removido** do grupo API tenant
- [ ] Filament admin **mantém cookie session** (smoke test login `/admin`)
- [ ] CORS configurado (`CORS_ALLOWED_ORIGINS`) + preflight OPTIONS funcional
- [ ] CSP estrita em prod via `SetSecurityHeaders` middleware
- [ ] DOMPurify usado em todo `v-html` (ESLint `no-unsanitized` enforced em CI)
- [ ] Axios SPA injeta Bearer + X-Tenant-Slug automaticamente
- [ ] Echo authorizer envia Bearer (smoke test WS)
- [ ] ~650 testes migrados (`actingAs` → `Sanctum::actingAs`); suite verde
- [ ] Novo suite token lifecycle (~10 tests) verde
- [ ] Audit events `TokenEmitido`/`TokenRevogado`/`LoginFalhouViaToken`/`TokenUsoSuspeito` emitidos
- [ ] OpenAPI `bearerAuth` security scheme aplicado; drift 0
- [ ] Pint clean
- [ ] Coverage ≥ 70% global mantida
- [ ] Postman collection publicada com pre-request scripts

---

## 13. Pós-implementação — backlog futuro

Itens **não** entregues nesta fase (registro para roadmap):

- **OAuth2 / OIDC** (Passport) para integrações third-party externas
- **SSO com Google/Microsoft** workspace
- **Mobile app native** (token Bearer já está pronto; falta app)
- **Token refresh background** transparent renewal antes de expirar (UX polish)
- **Admin clínica revoga tokens de outros users** do tenant (gestão de sessões corporativa)
- **Rate limit por token** (não só por IP/user) — defesa contra token comprometido sendo abusado
- **2FA TOTP** voluntário (removido na v1.3.0 da constitution; pode voltar como opt-in)
