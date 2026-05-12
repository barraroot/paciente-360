# Feature Specification: Migração Auth Cookie → Bearer Token

**Feature Branch**: `004-token-auth-migration`
**Created**: 2026-05-12
**Status**: Draft — aguarda `/speckit.clarify` (alguns NCs intencionais)
**Input**: Migrar autenticação Sanctum SPA stateful (cookie-based) para Sanctum Personal Access Tokens (Bearer) para desacoplar camadas API e SPA, permitir deploy independente em domínios distintos (`api.crm.com.br` + `app.crm.com.br`), e habilitar clientes adicionais (mobile, Postman, integrações) sem custo arquitetural.

---

## 1. Visão Geral

A Fase 3 (Omnichannel Inbox) revelou tensões operacionais com o modelo Sanctum SPA stateful:

- Cookie de sessão exige domínio compartilhado entre API e SPA → bloqueia deploy em CDN/edge separado.
- Echo / broadcasting auth com cookie em ambiente proxy (ngrok, load balancer) requer `trustProxies` + same-site cuidadoso → fragiliza setup.
- Cross-domain (widget embarcado em sites de terceiros) já usa Origin whitelist; misturar cookie e public_key gera 2 paradigmas mentais.
- Mobile e clientes Postman precisam de fluxo separado, criando duplicação.

Esta feature **substitui** o fluxo cookie por **Bearer tokens emitidos no login** (Sanctum Personal Access Tokens). API torna-se totalmente stateless. SPA arquitetura como cliente "externo" da API.

**Objetivo de produto**: viabilizar deploy `api.crm.com.br` (Laravel) + `app.crm.com.br` (Vue 3 estático em CDN/Cloudflare Pages/Vercel) + futuros clientes (mobile, parceiros) sem retrabalho de auth.

**Não é refactor cosmético**: toca constituição (Princípio VII), ~30 arquivos backend, ~650 testes, todas as policies, listeners de tenant, Reverb broadcast auth e Filament super admin (que **permanece** cookie por design — guard separado).

---

## 2. Contratos Herdados das Fases 0–3

### 2.1 Multi-tenancy (Fase 0 — preservado)

`ResolveTenant` middleware continua resolvendo tenant via subdomínio em rotas que servem a SPA do tenant; em deploy decoupled (`app.crm.com.br` chamando `api.crm.com.br`), o tenant pode ser resolvido via **header `X-Tenant-Slug`** ou **claim no token** (decisão em NC-1).

### 2.2 Auditoria (Fase 0 — preservado)

`audit_logs` continua imutável. Novo evento `TokenEmitido` / `TokenRevogado` para rastrear emissão de tokens.

### 2.3 Permissões via Spatie (Fase 0 — preservado mas team_id por header/claim)

Spatie team mode continua escopo por tenant. Em request, `setPermissionsTeamId($tenant->id)` é chamado por `ResolveTenant` exatamente como hoje — só muda **como** o tenant é descoberto.

### 2.4 Reverb broadcast auth (Fase 3 — alterado)

`/broadcasting/auth` deixa de aceitar cookie de sessão. Cliente Echo envia `Authorization: Bearer <token>` em `authEndpoint`. Backend valida via guard `sanctum` (token-based).

### 2.5 Filament super admin (Fase 0 — **preservado cookie**)

Filament continua com session cookies (guard `web` próprio). Justificativa:
- Filament é UI server-rendered Blade — cookie é o padrão idiomático.
- Super admin é audiência interna (~5 pessoas), não escala em volume.
- Manter cookie isola o blast radius do refactor para o cliente Vue.

### 2.6 Widget público (Fase 3 — preservado)

Widget JS continua autenticando por `public_key + Origin whitelist`. Não vira Bearer porque é cliente anônimo no browser do paciente — sem identidade humana.

---

## 3. User Scenarios & Testing

### User Story 1 — Login emite Bearer token (Priority: P1)

> Como Atendente, ao fazer login na SPA, quero receber um token persistente que posso usar para todas as chamadas subsequentes — sem depender de cookies de sessão.

**Independent Test**: POST `/api/v1/auth/login` retorna `{token: "1|abc...", token_expires_at: "...", user: {...}}`. Cliente armazena em memória + (opcional) refresh storage. Toda call subsequente envia `Authorization: Bearer 1|abc...`. Logout revoga token.

#### Acceptance Scenarios

- 🔴 **AC-A.1.1 — Login retorna token + dados do usuário**
- 🔴 **AC-A.1.2 — Token Bearer aceito em endpoints autenticados**
- 🔴 **AC-A.1.3 — Endpoint `/api/v1/auth/logout` revoga token corrente**
- 🔴 **AC-A.1.4 — Endpoint `/api/v1/auth/me` retorna user via Bearer**
- 🟡 **AC-A.1.5 — Expiração configurável (default 30 dias)**
- 🟡 **AC-A.1.6 — Refresh token (decisão em NC-2)**
- 🟡 **AC-A.1.7 — Listar e revogar tokens ativos** (`/api/v1/auth/tokens`)

### User Story 2 — SPA Vue armazena token e injeta em requests (P1)

> Como Atendente acessando a SPA em `app.crm.com.br`, quero que a SPA injete meu token em todas as requests para a API em `api.crm.com.br` automaticamente.

#### Acceptance Scenarios

- 🔴 **AC-A.2.1 — Axios interceptor injeta `Authorization: Bearer` automaticamente**
- 🔴 **AC-A.2.2 — 401 invalida storage e redireciona `/login`**
- 🔴 **AC-A.2.3 — Token persiste entre reloads** (decisão storage em NC-3)
- 🟡 **AC-A.2.4 — Logout limpa storage + revoga remoto**
- 🟢 **AC-A.2.5 — Auto-refresh transparente antes da expiração**

### User Story 3 — Reverb broadcast auth com Bearer (P1)

> Cliente Echo na SPA quero autenticar em canais privados Reverb usando Bearer token (não cookie).

#### Acceptance Scenarios

- 🔴 **AC-A.3.1 — Echo `authEndpoint` envia header `Authorization: Bearer`**
- 🔴 **AC-A.3.2 — Backend `/broadcasting/auth` valida via guard `sanctum`**
- 🔴 **AC-A.3.3 — Princípio II preservado**: cross-tenant em canal Reverb continua 403

### User Story 4 — CORS habilitado para cross-domain (P1)

> Como SPA em `app.crm.com.br` consumir `api.crm.com.br`, browser exige CORS preflight + Access-Control-Allow-Origin.

#### Acceptance Scenarios

- 🔴 **AC-A.4.1 — OPTIONS preflight responde com headers CORS apropriados**
- 🔴 **AC-A.4.2 — Origin whitelist configurável por env** (`CORS_ALLOWED_ORIGINS`)
- 🔴 **AC-A.4.3 — Reverb WSS aceita conexões de origin diferente**

### User Story 5 — Filament super admin permanece cookie (P2)

> Como Super Admin, quero continuar acessando `crm.lvh.me/admin` via session cookie sem mudanças.

#### Acceptance Scenarios

- 🔴 **AC-A.5.1 — Filament login emite session cookie (guard `web`)**
- 🔴 **AC-A.5.2 — Filament não interfere com Bearer flow da API tenant**

### User Story 6 — Webhook providers continuam sem auth (P3 — preservado)

> Webhooks Twilio / Meta / Widget continuam validando por HMAC signature, não por Bearer.

### User Story 7 — Documentação OpenAPI Bearer security scheme (P2)

> Como integrador externo (Postman, mobile), quero documentação clara de auth Bearer no OpenAPI.

---

## 4. Functional Requirements

### Auth flow

- **FR-001**: Sistema MUST emitir Sanctum Personal Access Token no `POST /api/v1/auth/login` retornando `{token, token_expires_at, user}`.
- **FR-002**: Sistema MUST aceitar `Authorization: Bearer <token>` em endpoints autenticados via guard `sanctum`.
- **FR-003**: Sistema MUST descontinuar `EnsureFrontendRequestsAreStateful` na pipeline da API tenant (mantém para Filament).
- **FR-004**: Sistema MUST revogar token corrente em `POST /api/v1/auth/logout`.
- **FR-005**: Sistema MUST permitir listar tokens ativos de um user em `GET /api/v1/auth/tokens` e revogar específico em `DELETE .../tokens/{id}`.
- **FR-006**: Tokens MUST ter expiração configurável (default 30 dias via `config('sanctum.expiration')`).

### SPA changes

- **FR-007**: Axios instance MUST injetar header `Authorization` quando token presente no Pinia auth store.
- **FR-008**: Interceptor de response 401 MUST limpar storage + redirect `/login`.
- **FR-009**: Token persistido em storage (decisão NC-3 — localStorage vs sessionStorage vs in-memory only).
- **FR-010**: Reverb client `authorizer` MUST enviar Bearer header em `/broadcasting/auth`.

### Multi-tenancy

- **FR-011**: `ResolveTenant` middleware MUST resolver tenant via uma das estratégias (NC-1):
  - (a) Header `X-Tenant-Slug` enviado pelo cliente
  - (b) Claim `tenant_slug` embutida no token Sanctum (via `tokens.abilities` ou meta)
  - (c) Subdomínio (legado — quando API e SPA no mesmo domínio)

### CORS

- **FR-012**: Sistema MUST aplicar middleware CORS configurável via `config/cors.php`.
- **FR-013**: `CORS_ALLOWED_ORIGINS` env supporta múltiplos domínios separados por vírgula.
- **FR-014**: Preflight OPTIONS MUST responder em <100ms (cached middleware).

### Reverb

- **FR-015**: `/broadcasting/auth` MUST aceitar guard `sanctum` (validação Bearer).
- **FR-016**: Reverb WSS server MUST aceitar `Sec-WebSocket-Origin` configurável.

### Filament

- **FR-017**: Filament super admin MUST permanecer guard `web` (cookie session) sem alteração.
- **FR-018**: Filament login route MUST permanecer no domínio `crm.{tld}/admin` (não conflita com `app.crm.{tld}`).

### Testes

- **FR-019**: TODOS os ~650 testes existentes que usam `actingAs($user)` MUST ser migrados para `Sanctum::actingAs($user, ['*'])` (Sanctum já tem helper compatível).
- **FR-020**: Novo suite de testes para token lifecycle (emit, validate, expire, revoke, list).

### Auditoria

- **FR-021**: Novo evento `TokenEmitido` (Auditable) com payload `{user_id, token_id, ip, user_agent, expires_at}`.
- **FR-022**: Novo evento `TokenRevogado` com `{user_id, token_id, motivo: manual|logout|expired|admin_force}`.

### Segurança operacional

- **FR-023**: Tokens MUST ser hash SHA-256 no DB (Sanctum default).
- **FR-024**: Rate limit no login MANTIDO (5 tentativas → bloqueio temporário — Fase 0).
- **FR-025**: Token revocation MUST ser idempotente (revogar token já revogado retorna 200/204).

---

## 5. Fora de Escopo

- **OAuth2 / OIDC flows** — fora do MVP (Sanctum tokens internos suficientes).
- **SSO com Google/Microsoft** — fase futura.
- **Refresh token automático no servidor** — depende NC-2; default é "long-lived single token" (mais simples).
- **Mobile clients** — esta feature **habilita** mobile mas não entrega app mobile.
- **Migração Filament para Bearer** — explicitamente preservado em cookie (US-5).

---

## 6. Eventos de Domínio

| Evento | Disparado em | Payload | Audit action |
|---|---|---|---|
| `TokenEmitido` | Login bem-sucedido | `{user_id, token_id, ip, user_agent, expires_at, abilities}` | `auth.token_emitido` |
| `TokenRevogado` | Logout, expiração detectada, revogação admin | `{user_id, token_id, motivo}` | `auth.token_revogado` |
| `LoginFalhouViaToken` | Token inválido ou expirado | `{ip, token_prefix_masked}` | `auth.login_falhou_token` |

---

## 7. NEEDS_CLARIFICATION (3 NCs intencionais)

### NC-1 — Estratégia de resolução de tenant em deploy decoupled

**Contexto**: Hoje `ResolveTenant` usa `$request->getHost()` → subdomínio → tenant slug. Em `app.crm.com.br` (SPA) chamando `api.crm.com.br` (API), o subdomínio da request é `api`, não o slug do tenant.

**Opções**:
- (A) **Header `X-Tenant-Slug` obrigatório** em toda request autenticada — cliente SPA injeta automaticamente.
- (B) **Claim embutida no token** via Sanctum ability `tenant:clinica-alfa` ou tabela auxiliar.
- (C) **Híbrido**: header default; claim no token como assinatura cruzada de segurança.

**Recomendação**: (A) — header simples + Spatie team mode existente. Custo de implementação baixo.

### NC-2 — Refresh token strategy

**Opções**:
- (A) **Long-lived single token** (default 30d). Quando expira, user re-loga. Simples mas força login frequente.
- (B) **Refresh token separado** (HttpOnly cookie ou outro Sanctum token) — quando access token expira, cliente troca via refresh. Mais complexo.
- (C) **Sliding expiration** — toda request renova janela do token automaticamente.

**Recomendação**: (A) para MVP da migração. (B) ou (C) em fase futura se UX exigir.

### NC-3 — Token storage no cliente (XSS vs UX)

**Opções**:
- (A) **localStorage** — persiste entre tabs/reloads. **Vulnerável a XSS** (qualquer script lê o token).
- (B) **sessionStorage** — persiste só na tab. XSS-vulnerable também.
- (C) **In-memory only** (Pinia store) — XSS-safe mas perde no reload.
- (D) **HttpOnly cookie do Bearer** — XSS-safe, mas ironicamente volta a depender de cookie (mistura paradigmas).

**Recomendação**: (C) com fallback de re-login transparente. **Aceita o trade-off de re-login mais frequente em troca de XSS hardening.** Mitigação extra: CSP estrita + DOMPurify em qualquer content de usuário.

---

## 8. Success Criteria

- **SC-001**: 0 chamadas autenticadas dependem de cookie de sessão (exceto Filament admin).
- **SC-002**: API e SPA podem rodar em domínios distintos sem ajuste de código (apenas env vars).
- **SC-003**: Tempo médio de login mantém p95 < 500ms.
- **SC-004**: Suite total de testes ≥ 1044 verdes (não regredir).
- **SC-005**: Coverage backend ≥ 70% mantida.
- **SC-006**: Postman pode autenticar via `POST /login` e usar token nas requests subsequentes (smoke test manual).
- **SC-007**: Echo client em domínio externo conecta a Reverb com Bearer (smoke test manual).
- **SC-008**: 0 vazamentos de PII em token (token é opaco, não JWT).
- **SC-009**: Princípios constitucionais I, II, V, VI, VII preservados; **Princípio VII amendment** registrado.

---

## 9. Definição de Pronto

Verificar **antes** de merge para `main`:

- [ ] Constituição amendment v1.4.0 (Princípio VII — aceita auth via Bearer token; mantém argon2id, TLS 1.3, rate limit, brute force lock).
- [ ] `POST /api/v1/auth/login` retorna token Bearer + user.
- [ ] `POST /api/v1/auth/logout` revoga token.
- [ ] `GET /api/v1/auth/me` aceita Bearer.
- [ ] `GET/DELETE /api/v1/auth/tokens[/{id}]` para listar/revogar.
- [ ] Sanctum stateful middleware removido da pipeline da API tenant.
- [ ] CORS configurado e testado.
- [ ] Axios SPA injeta Bearer automaticamente.
- [ ] Echo authorizer envia Bearer.
- [ ] Filament permanece cookie (NÃO regredir).
- [ ] ~650 testes migrados de `actingAs` para `Sanctum::actingAs`.
- [ ] Novo suite de tests para token lifecycle.
- [ ] Audit events `TokenEmitido`/`TokenRevogado` emitidos.
- [ ] OpenAPI atualizado com `bearerAuth` security scheme.
- [ ] Quickstart `004-token-auth-migration/quickstart.md` documenta deploy split (api + app domains).
- [ ] Pint clean + OpenAPI drift 0.

---

## 10. Riscos e Mitigações

| Risco | Severidade | Mitigação |
|---|---|---|
| **R1 — XSS rouba token de localStorage** | 🔴 Alta | NC-3 recomenda in-memory + CSP estrita + DOMPurify. Token expira 30d. |
| **R2 — Quebra de 650 testes ao mudar guard** | 🔴 Alta | Migração mecânica via grep+sed; rodar suite full antes/depois de cada lote. |
| **R3 — Reverb auth Bearer não funciona em prod** | 🟡 Média | Test E2E manual antes go-live; documentar configuração WSS Origin. |
| **R4 — Mobile/Postman requer documentação clara** | 🟡 Média | OpenAPI bearerAuth scheme + quickstart com curl examples. |
| **R5 — Filament e API tenant conflito de session** | 🟡 Média | Filament guard `web` separado; cookies de domínios distintos (`crm.{tld}` vs `app.crm.{tld}`). |
| **R6 — Constituição amendment rejeitada** | 🟢 Baixa | Discutir antecipadamente; amendment é MINOR (não MAJOR) — apenas aceita formato adicional. |

---

## 11. Princípios da Constituição Tocados

| US | Princípios | Como toca |
|---|---|---|
| US-1 | **VII** | Substitui session cookie por Bearer token; mantém argon2id/TLS/rate-limit. **Exige amendment v1.4.0.** |
| US-2 | **I** (XSS = LGPD risk se token leak), **VII** | Storage decision via NC-3. |
| US-3 | **II** (broadcast isolation) | Continua validando ability + tenant — só muda formato de credential. |
| US-4 | **VII** | CORS expand surface — Origin whitelist mitiga. |
| US-5 | **VII** | Preserva guard web para Filament — isolamento. |

---

## 12. Assumptions

- **Sanctum Personal Access Tokens** é mecanismo correto (vs Passport JWT). Justifica: opaque tokens são mais seguros (no PII embarcada), Sanctum já está instalado, integração nativa Laravel.
- **Token format**: `{id}|{plain_text_token}` (Sanctum default) — DB armazena hash.
- **Storage decision NC-3**: in-memory + auto-reload via mínima request to `/me` na abertura da SPA.
- **CORS**: middleware Laravel padrão é suficiente (não precisa CDN-level).
- **Filament cookie domain**: `crm.{tld}` strict (não compartilha com `app.crm.{tld}`).

---

## 13. Estimativa de esforço

| Lote | Escopo | Tasks estimadas | Risco |
|---|---|---|---|
| A | Amendment constituição v1.4.0 + Sanctum config tokens + login endpoint | ~10 | Baixo |
| B | Migrar middleware pipeline (remove stateful, adiciona sanctum guard) | ~5 | Médio |
| C | Migrar SPA axios + Pinia auth store + interceptors | ~8 | Médio |
| D | Migrar Echo client + Reverb broadcast auth | ~5 | Médio (E2E sensível) |
| E | CORS config + Filament isolation | ~5 | Baixo |
| F | Migrar ~650 testes (script + verificação) | ~10 (massivos) | **Alto** (regressão potencial) |
| G | Novo suite token lifecycle | ~8 | Baixo |
| H | OpenAPI bearerAuth + quickstart deploy split | ~5 | Baixo |
| I | Verificação final + housekeeping | ~5 | Baixo |

**Total estimado**: ~60 tasks. **Esforço wall-clock**: 1-2 dias com paralelização agressiva.

---

## 14. Pré-requisitos

- Constituição amendment v1.4.0 aprovada (block 0 — antes de qualquer código).
- Decisão NC-1, NC-2, NC-3 via `/speckit.clarify`.
- Suite Fase 3 verde em `main` (já feito — commit `65dec50`).
- Branch `004-token-auth-migration` criada from `main` (já feito).

---

## 15. Próximos passos

1. ✅ Spec rascunhada (este arquivo)
2. ⏳ Resolver 3 NCs via `/speckit.clarify`
3. ⏳ Constituição amendment v1.4.0
4. ⏳ `/speckit.plan` para gerar plan.md + research.md + data-model.md + contracts/openapi.yaml
5. ⏳ `/speckit.tasks` para tasks.md
6. ⏳ `/speckit.analyze` para gates
7. ⏳ `/speckit.implement` por lote
