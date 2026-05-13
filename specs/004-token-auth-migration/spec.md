# Feature Specification: Migração Auth Cookie → Bearer Token

**Feature Branch**: `004-token-auth-migration`
**Created**: 2026-05-12
**Status**: **Clarified** — 5/5 perguntas críticas resolvidas em 2026-05-12 (NC-1, NC-2, NC-3 + 2 ambiguidades descobertas em sessão clarify)
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

## Clarifications

### Session 2026-05-12

- Q: Como o backend `api.crm.com.br` identifica qual tenant pertence a request em deploy decoupled? → **A: Header `X-Tenant-Slug` obrigatório em toda request autenticada; SPA injeta via axios interceptor lendo do auth store. Resolve NC-1.**
- Q: Onde a SPA armazena o token Bearer? → **A: `localStorage` — persiste entre tabs/reloads, melhor UX. Trade-off XSS aceito sob mitigação obrigatória (CSP estrita + DOMPurify + token expiração 30d). Resolve NC-3.**
- Q: Como tratamos expiração de token Bearer? → **A: Sliding expiration — toda request autenticada renova janela do token por mais 30 dias. Sem refresh token separado. Resolve NC-2.**
- Q: Como o login em `app.crm.com.br` (sem subdomínio de tenant) resolve qual tenant alvo? → **A: Email globalmente único — constraint UNIQUE cross-tenant em `users.email`. Login lookup por email retorna user + tenant_id; SPA persiste e usa `X-Tenant-Slug` daí em diante. Casos raros de "mesma pessoa em múltiplas clínicas" usam emails distintos.**
- Q: Escopo do `POST /auth/logout`? → **A: Apenas o token corrente (o do `Authorization: Bearer` da request). Outros dispositivos continuam logados. Endpoint separado `POST /auth/logout-all` para revogar todos os tokens do user.**

---

## 2. Contratos Herdados das Fases 0–3

### 2.1 Multi-tenancy (Fase 0 — preservado com nova estratégia de resolução)

`ResolveTenant` middleware passa a resolver tenant via **header `X-Tenant-Slug`** em endpoints autenticados (decisão NC-1 resolvida em 2026-05-12). Subdomínio continua aceito como fallback para compatibilidade com deploys mono-domínio (legado). SPA injeta o header via axios interceptor lendo do auth store; Postman/curl/mobile injetam manualmente.

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

- **FR-001**: Sistema MUST emitir Sanctum Personal Access Token no `POST /api/v1/auth/login` retornando `{token, token_expires_at, user, tenant: {id, slug, name}}`. Tenant é resolvido via lookup `users.email` (email globalmente único — decisão Q4 /clarify 2026-05-12). SPA persiste `tenant.slug` para uso em `X-Tenant-Slug` header subsequente.
- **FR-001a**: Sistema MUST aplicar UNIQUE constraint global em `users.email` (cross-tenant) via migration de pré-implementação. Migration MUST validar existência de duplicatas antes de aplicar; se houver, dispara comando interativo `users:dedupe-emails-cross-tenant` que: lista duplicatas, oferece append de sufixo `.tenant-{slug}` ao email duplicado E notifica admins dos tenants envolvidos. Sem duplicatas → constraint aplicada automaticamente.
- **FR-002**: Sistema MUST aceitar `Authorization: Bearer <token>` em endpoints autenticados via guard `sanctum`.
- **FR-003**: Sistema MUST descontinuar `EnsureFrontendRequestsAreStateful` na pipeline da API tenant (mantém para Filament).
- **FR-004**: Sistema MUST revogar **apenas o token corrente** (extraído de `Authorization: Bearer` header da request) em `POST /api/v1/auth/logout` (decisão Q5 /clarify 2026-05-12). Outros tokens do mesmo user em outros dispositivos permanecem ativos.
- **FR-004a**: Sistema MUST oferecer `POST /api/v1/auth/logout-all` para revogar TODOS os tokens do user autenticado (sair de todos os dispositivos). Dispara `TokenRevogado` event por token afetado com `motivo='logout_all'`.
- **FR-005**: Sistema MUST permitir listar tokens ativos de um user em `GET /api/v1/auth/tokens` e revogar específico em `DELETE .../tokens/{id}`.
- **FR-006**: Tokens MUST ter expiração **sliding** (decisão NC-2 resolvida em 2026-05-12) com janela default 30 dias via `config('sanctum.expiration')`. **Toda request autenticada renova `tokens.expires_at = now() + janela`** de forma transparente (middleware `RefreshSanctumTokenExpiration` aplicado após guard sanctum). Atendente ativo nunca expira; inativo por 30 dias consecutivos re-loga. Token revogado manualmente (logout / `DELETE /tokens/{id}`) NÃO renova.

### SPA changes

- **FR-007**: Axios instance MUST injetar header `Authorization` quando token presente no Pinia auth store.
- **FR-008**: Interceptor de response 401 MUST limpar storage + redirect `/login`.
- **FR-009**: Token persistido em **`localStorage`** (decisão NC-3 resolvida em 2026-05-12) na chave `paciente360.auth.token`. Auth store Pinia carrega no boot da SPA via `localStorage.getItem(...)`. Limpeza no logout via `localStorage.removeItem(...)`. **Mitigações XSS obrigatórias** (gates de release): (a) Content-Security-Policy estrita (sem `unsafe-inline`/`unsafe-eval`); (b) DOMPurify aplicado a qualquer HTML user-provided antes de render; (c) token expira em 30d (NC-2); (d) ESLint plugin `no-unsanitized` enforced em PR.
- **FR-010**: Reverb client `authorizer` MUST enviar Bearer header em `/broadcasting/auth`.

### Multi-tenancy

- **FR-011**: `ResolveTenant` middleware MUST resolver tenant via **header `X-Tenant-Slug`** (estratégia principal, decisão NC-1) em endpoints autenticados. Subdomínio mantido como fallback para deploys mono-domínio (legado). SPA injeta header automaticamente via axios interceptor; clientes externos (Postman, mobile) injetam manualmente. Header ausente em endpoint autenticado → 400 `tenant_header_required`.

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

### ✅ NC-1 — Estratégia de resolução de tenant — RESOLVIDO (2026-05-12)

**Decisão (Q1.a /clarify)**: **Header `X-Tenant-Slug` obrigatório** em toda request autenticada. Cliente SPA injeta automaticamente via axios interceptor lendo do Pinia auth store (carregado após login). Clientes externos (Postman, mobile, integrações) injetam manualmente. Header ausente → 400 `tenant_header_required`.

**Implicação prática**:
- Login flow inclui passo "selecionar/identificar tenant" antes de POST `/login` (decisão de UX na plan — provavelmente combo: digitar email → backend retorna lista de tenants associados → user escolhe).
- Subdomínio continua aceito como fallback para deploys mono-domínio (legado/dev local com `clinica-alfa.lvh.me`).
- Validação de pertencimento: token pertence a `user.id` X + `X-Tenant-Slug` indica tenant Y → backend verifica `user.tenant_id === tenant(Y).id`; mismatch → 403. Sem isso, token roubado poderia ser usado em qualquer tenant.

### ✅ NC-2 — Refresh token strategy — RESOLVIDO (2026-05-12)

**Decisão (Q3.c /clarify)**: **Sliding expiration** com janela 30 dias.

**Implementação**:
- Middleware `RefreshSanctumTokenExpiration` aplicado após guard sanctum em rotas autenticadas
- Update `personal_access_tokens.expires_at = now() + 30d` em toda request bem-sucedida
- Throttle interno: só renova se `expires_at - now() < 25 dias` (evita 1 UPDATE por request — apenas quando faz sentido renovar; ~5 dias de "buffer")
- Excludes: revogação manual (logout, DELETE tokens/{id}) NÃO renova
- Padrão usado por GitHub PAT, Slack tokens

**Justificativa vs (B) refresh token separado**: simplicidade arquitetural — sem tabela auxiliar, sem 2 token types, sem `/refresh` endpoint. Atendente que esquecer 30d consecutivos é caso raro o suficiente para justificar re-login. Caso real de exfiltração de token (R1) é mitigado por audit log de uso suspeito + revogação remota — não por short-lived access tokens (que apenas reduzem janela, não eliminam risco).

### ✅ NC-3 — Token storage no cliente — RESOLVIDO (2026-05-12)

**Decisão (Q2.a /clarify)**: **`localStorage`** na chave `paciente360.auth.token`. Persiste entre tabs e reloads — melhor UX.

**Trade-off aceito**: localStorage é XSS-vulnerable (qualquer script injetado pode ler). Mitigações obrigatórias **como gate de release**:
- (a) **CSP estrita** sem `unsafe-inline`/`unsafe-eval`; nonce/hash para scripts inline necessários
- (b) **DOMPurify** aplicado em qualquer HTML user-provided (mensagens, anotações, quick replies content) antes de render
- (c) **Token expira em 30d** (NC-2 default — limita janela de exposição)
- (d) **ESLint plugin `no-unsanitized`** em CI bloqueia PR com sinks DOM diretos
- (e) **Audit log** de uso de token suspeito (mesmo token, IP/UA diferentes em <5min) — alerta operacional

**Justificativa pela escolha A vs E (híbrido) recomendada**: priorizou UX consistente (zero re-login involuntário) sobre defesa em profundidade adicional. Risco R1 do spec § 10 sobe de 🔴 Alta para **🔴 Alta + obrigatório** (mitigações são gate, não best-effort).

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
- [ ] `POST /api/v1/auth/logout` revoga **apenas token corrente** (escopo decidido Q5 /clarify).
- [ ] `POST /api/v1/auth/logout-all` revoga TODOS os tokens do user.
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
| **R1 — XSS rouba token de localStorage** (NC-3 decidiu por localStorage) | 🔴 Alta | **Mitigações obrigatórias (gates de release)**: CSP estrita sem unsafe-inline/eval, DOMPurify em todo HTML user-provided, token expira 30d, ESLint `no-unsanitized` enforced em CI, audit log de uso de token suspeito (mesmo token / IPs distintos em <5min). |
| **R2 — Quebra de 650 testes ao mudar guard** | 🔴 Alta | Migração mecânica via grep+sed; rodar suite full antes/depois de cada lote. |
| **R3 — Reverb auth Bearer não funciona em prod** | 🟡 Média | Test E2E manual antes go-live; documentar configuração WSS Origin. |
| **R4 — Mobile/Postman requer documentação clara** | 🟡 Média | OpenAPI bearerAuth scheme + quickstart com curl examples. |
| **R5 — Filament e API tenant conflito de session** | 🟡 Média | Filament guard `web` separado; cookies de domínios distintos (`crm.{tld}` vs `app.crm.{tld}`). |
| **R6 — Constituição amendment rejeitada** | 🟢 Baixa | Discutir antecipadamente; amendment é MINOR (não MAJOR) — apenas aceita formato adicional. |
| **R7 — Duplicatas de email cross-tenant na base atual** (decisão Q4 /clarify exige UNIQUE global em users.email) | 🟡 Média | Migration de pré-flight: comando `users:dedupe-emails-cross-tenant` lista duplicatas, oferece append de sufixo `.tenant-{slug}`, notifica admins. Bloqueia migration até zero duplicatas. Audit log dedup. |

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
