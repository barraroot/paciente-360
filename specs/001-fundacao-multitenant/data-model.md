# Phase 1 Data Model — Fase 0 Fundação Multi-tenant

**Feature**: `001-fundacao-multitenant`
**Plan**: [plan.md](./plan.md)
**Database**: PostgreSQL 18
**Date**: 2026-05-10

Este documento descreve **todas as tabelas** e relações criadas
nesta fase. Tipos PostgreSQL nativos. Toda tabela de domínio carrega
`tenant_id BIGINT NOT NULL` com FK e índice composto começando por
ele (decisão R1 de `research.md`).

## Convenções

- PKs: `id BIGSERIAL` (BIGINT auto-increment).
- Timestamps: `created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()`,
  `updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()`.
- Soft delete onde aplicável: `deleted_at TIMESTAMPTZ NULL`.
- FK: sempre `ON DELETE RESTRICT` para tabelas que carregam
  histórico legal/auditoria; `ON DELETE CASCADE` somente em
  registros descartáveis explicitamente (ex.: tokens).
- Strings de domínio: `VARCHAR(N)` com tamanho concreto, não TEXT
  livre, salvo `payload` JSON e blobs.
- JSON: `JSONB` (não `JSON` — index e operadores são exclusivos do
  JSONB).
- Money: `BIGINT` em centavos (sem ponto flutuante para preço).
- E-mail: `VARCHAR(254)` (RFC 5321).

## Diagrama de relações (visão geral)

```
                    plans (global)
                      ▲
                      │ plan_id (snapshot)
                      │
          ┌──────► tenants (multi-tenant root)
          │           ▲
          │           │ tenant_id
          │           │
          │     ┌─────┴────────────────────┐
          │     │                          │
          │  users                     subscriptions
          │   ▲ │                          ▲
          │   │ │                          │ (Cashier)
          │   │ │                          │
          │   │ │  ┌───────────────────────┘
          │   │ │  │
          │   │ ▼  ▼
          │   │  audit_logs
          │   │  ai_usage_meters
          │   │  professionals (skeleton)
          │   │  invitations
          │   │  stripe_events (no tenant; correlated)
          │   │
          │  user_roles, user_permissions (Spatie pivots,
          │  com tenant_id na pivot)
          │
          └─ subscription_items (Cashier; child of subscriptions)
```

---

## 1. `tenants`

Raiz do multi-tenancy. **Não usa** `BelongsToTenant`. Identificação
pública via `slug` (subdomínio).

| Coluna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | BIGSERIAL | PK | |
| `slug` | VARCHAR(63) | NOT NULL, UNIQUE | Subdomínio. Lowercase, `[a-z0-9-]`, 3–63 chars. RFC 1035. |
| `name` | VARCHAR(150) | NOT NULL | Razão social/nome fantasia. |
| `cnpj` | VARCHAR(14) | NOT NULL, UNIQUE | Apenas dígitos (canonicalizado antes de gravar). |
| `responsible_name` | VARCHAR(150) | NOT NULL | Nome do contato responsável. |
| `responsible_email` | VARCHAR(254) | NOT NULL | E-mail principal do tenant; pode coincidir com primeiro Admin. |
| `responsible_phone` | VARCHAR(20) | NOT NULL | E.164. |
| `status` | VARCHAR(20) | NOT NULL, DEFAULT `'trial'` | Enum: `trial`, `active`, `overdue`, `suspended`, `cancelled`. |
| `trial_ends_at` | TIMESTAMPTZ | NULL | NOT NULL enquanto `status = 'trial'`. |
| `overdue_since` | TIMESTAMPTZ | NULL | Marcado quando status passa para `overdue`; usado para gate dos 7 dias. |
| `restrictions_applied_at` | TIMESTAMPTZ | NULL | Quando `ApplyOverdueRestrictionsJob` rodou. |
| `plan_id` | BIGINT | NULL, FK → `plans.id` ON DELETE RESTRICT | Plano vigente; NULL durante trial puro antes do checkout. |
| `stripe_customer_id` | VARCHAR(255) | NULL, UNIQUE | Preenchido quando assinatura é criada. |
| `subdomain_custom` | VARCHAR(255) | NULL, UNIQUE | Domínio customizado configurado posteriormente (fora do MVP). |
| `terms_accepted_at` | TIMESTAMPTZ | NOT NULL | Princípio I — registro do consentimento. |
| `terms_version` | VARCHAR(20) | NOT NULL | Ex.: `'2026-05-01'`. |
| `onboarding_state` | JSONB | NOT NULL, DEFAULT `'{}'::jsonb` | Estado do wizard de onboarding (US-1.2). Shape: `{ steps: [{ key, status, required, payload? }, ...], progress_percent }`. Atualizado pelo `OnboardingService` com `lockForUpdate`. |
| `created_at`, `updated_at`, `deleted_at` | TIMESTAMPTZ | | Soft delete: tenants nunca são deletados fisicamente nesta fase. |

**Índices**:

- `UNIQUE INDEX tenants_slug_uniq ON (slug)`
- `UNIQUE INDEX tenants_cnpj_uniq ON (cnpj)`
- `INDEX tenants_status_idx ON (status)`
- `INDEX tenants_overdue_since_idx ON (overdue_since)` para o job D+7.

**Estado/transições** (`status`):

```
trial ──(checkout success)──► active
trial ──(trial_ends_at < now AND no plan)──► overdue (sem plano = falha de pagamento equivalente)
active ──(3 invoice.payment_failed)──► overdue
overdue ──(invoice.payment_succeeded)──► active
overdue ──(super_admin action, +30d sem regularizar)──► suspended
suspended ──(super_admin reactivate)──► active
qualquer ──(super_admin cancel)──► cancelled (terminal)
```

Transições mediadas pelo `TenantStateService`; cada transição
emite `TenantStatusChanged` (auditável).

---

## 2. `plans`

Catálogo comercial **global** (não tem `tenant_id`). Editável apenas
pelo Super Admin via Filament. Tenants existentes guardam o
**snapshot** dos campos via `subscriptions.plan_snapshot` (JSONB)
para que mudanças no plano não retroajam.

| Coluna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | BIGSERIAL | PK | |
| `code` | VARCHAR(50) | NOT NULL, UNIQUE | Código curto; usado em config (`'starter'`, `'pro'`). |
| `name` | VARCHAR(100) | NOT NULL | Nome comercial exibido. |
| `description` | TEXT | NULL | Marketing. |
| `base_price_cents` | BIGINT | NOT NULL | Preço por profissional ativo, BRL em centavos. |
| `included_professionals` | INTEGER | NOT NULL, DEFAULT 0 | Quantidade base inclusa antes de cobrar add-ons; tipicamente 0 (cobra cada profissional). |
| `included_ai_messages` | INTEGER | NOT NULL | Cota mensal de mensagens IA inclusa. |
| `overage_price_cents` | INTEGER | NOT NULL | Preço unitário do excedente em centavos. |
| `max_users` | INTEGER | NOT NULL | Limite total de usuários internos do tenant; gate de FR-027. |
| `max_channels` | INTEGER | NOT NULL | Limite de canais conectados (preparação para fase 2). |
| `stripe_price_id_base` | VARCHAR(100) | NOT NULL | Price ID no Stripe para o item recorrente "base". |
| `stripe_price_id_overage` | VARCHAR(100) | NOT NULL | Price ID metered para overage IA. |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT TRUE | Plano oculto para novos clientes mas honrado para existentes. |
| `created_at`, `updated_at` | TIMESTAMPTZ | | Sem soft delete; planos descontinuados ficam `is_active = false`. |

**Índices**:

- `UNIQUE INDEX plans_code_uniq ON (code)`
- `INDEX plans_is_active_idx ON (is_active)`

**Seeders**:

- `DatabaseSeeder` insere 3 planos default: `starter`, `pro`,
  `enterprise` com valores placeholder (a ajustar com financeiro
  antes de produção).

---

## 3. `subscriptions` e `subscription_items` (Cashier)

Cashier-compatível. Cashier 16.x cria estas tabelas via migration
oficial. Adicionamos colunas custom (`plan_snapshot`, `tenant_id`).

### 3.1 `subscriptions`

| Coluna | Tipo | Constraints | Origem | Notas |
|---|---|---|---|---|
| `id` | BIGSERIAL | PK | Cashier | |
| `tenant_id` | BIGINT | NOT NULL, FK → `tenants.id` ON DELETE RESTRICT | custom | Cashier por padrão usa `user_id`; trocamos para `tenant_id` (Tenant é o Billable). |
| `type` | VARCHAR(50) | NOT NULL, DEFAULT `'default'` | Cashier | |
| `stripe_id` | VARCHAR(255) | NOT NULL, UNIQUE | Cashier | Subscription ID Stripe. |
| `stripe_status` | VARCHAR(20) | NOT NULL | Cashier | `active`, `past_due`, `canceled` etc. |
| `stripe_price` | VARCHAR(100) | NULL | Cashier | Price ID principal. |
| `quantity` | INTEGER | NULL | Cashier | Para single-item subs. Não usamos (multi-item via `subscription_items`). |
| `plan_id` | BIGINT | NOT NULL, FK → `plans.id` | custom | Plano contratado. |
| `plan_snapshot` | JSONB | NOT NULL | custom | Cópia dos campos relevantes do plan no momento da contratação (preços, cotas). Garante que edição posterior do plan global não afete o tenant. |
| `professionals_quantity` | INTEGER | NOT NULL, DEFAULT 0 | custom | Número de profissionais ativos contratados; reflete `quantity` do subscription_item base. |
| `current_period_start` | TIMESTAMPTZ | NOT NULL | Stripe | Sincronizado por webhook. |
| `current_period_end` | TIMESTAMPTZ | NOT NULL | Stripe | Idem. |
| `trial_ends_at` | TIMESTAMPTZ | NULL | Cashier | |
| `ends_at` | TIMESTAMPTZ | NULL | Cashier | Cancelamento agendado. |
| `created_at`, `updated_at` | TIMESTAMPTZ | | | |

**Índices**:

- `UNIQUE INDEX subscriptions_stripe_id_uniq ON (stripe_id)`
- `INDEX subscriptions_tenant_id_idx ON (tenant_id)`
- `INDEX subscriptions_status_idx ON (stripe_status)`

### 3.2 `subscription_items`

Cashier table. Mantém estrutura padrão; sem custom columns.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | BIGSERIAL | PK |
| `subscription_id` | BIGINT | FK → subscriptions; ON DELETE CASCADE |
| `stripe_id` | VARCHAR(255) | UNIQUE |
| `stripe_product` | VARCHAR(100) | |
| `stripe_price` | VARCHAR(100) | NOT NULL |
| `quantity` | INTEGER | NULL — null para metered |
| `created_at`, `updated_at` | | |

**Itens esperados por subscription**:

- 1× `base` — recorrente, `quantity` = professionals_quantity.
- 1× `ai-overage` — metered (Stripe usage records).

---

## 4. `users`

Identidade unificada. **`tenant_id NULLABLE`** porque Super Admins
não pertencem a nenhum tenant. Para usuários internos do tenant,
identidade é `(tenant_id, email)`.

| Coluna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | BIGSERIAL | PK | |
| `tenant_id` | BIGINT | NULL, FK → `tenants.id` ON DELETE RESTRICT | NULL ⇒ Super Admin (cross-tenant). |
| `name` | VARCHAR(150) | NOT NULL | |
| `email` | VARCHAR(254) | NOT NULL | Único POR tenant (NULL counta como tenant "platform"). Constraint composto abaixo. |
| `email_verified_at` | TIMESTAMPTZ | NULL | |
| `password` | VARCHAR(255) | NOT NULL | Hash argon2id. |
| `remember_token` | VARCHAR(100) | NULL | Laravel padrão. |
| `status` | VARCHAR(20) | NOT NULL, DEFAULT `'invited'` | Enum: `invited`, `active`, `disabled`. |
| `first_login_at` | TIMESTAMPTZ | NULL | Marcado no primeiro login com sucesso (info no painel). |
| `last_login_at` | TIMESTAMPTZ | NULL | Para info no painel. |
| `last_login_ip` | INET | NULL | |
| `failed_login_attempts` | SMALLINT | NOT NULL, DEFAULT 0 | Reset em login bem-sucedido. |
| `locked_until` | TIMESTAMPTZ | NULL | Bloqueio temporário (FR-023). |
| `created_at`, `updated_at`, `deleted_at` | TIMESTAMPTZ | | Soft delete = desativação preservando auditoria (FR-028). |

**Índices**:

- `UNIQUE INDEX users_tenant_email_uniq ON (COALESCE(tenant_id, 0), email)`
  — permite mesmo e-mail em tenants distintos; usa COALESCE para
  tratar NULL como sentinel "platform".
- `INDEX users_tenant_id_status_idx ON (tenant_id, status)`
- `INDEX users_email_idx ON (email)` para login (email entra com
  tenant resolvido pelo subdomínio).

**Notas de Cashier**:

Cashier por padrão assume `users` como Billable. Como Tenant é
nosso Billable (não User), o trait `Billable` de Cashier vai no
**Model `Tenant`**, não `User`. As colunas `stripe_id`, `pm_type`,
`pm_last_four`, `trial_ends_at` ficam em `tenants` (não em `users`).

---

## 5. Spatie Permissions: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

Geradas pela migration oficial do `spatie/laravel-permission`.
**Customização**: adicionar `tenant_id` em `roles` e `permissions`
para suportar papéis "globais" (Super Admin) e "por tenant".

### 5.1 `roles` (estendido)

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | BIGSERIAL | PK |
| `name` | VARCHAR(125) | NOT NULL |
| `guard_name` | VARCHAR(125) | NOT NULL — `web` (Filament/SPA) ou `api` (tokens). |
| `tenant_id` | BIGINT | NULL — global se NULL; específico de tenant caso contrário. |
| `created_at`, `updated_at` | | |

`UNIQUE INDEX roles_name_guard_tenant_uniq ON (name, guard_name, COALESCE(tenant_id, 0))`.

### 5.2 `permissions` (estendido)

Estrutura idêntica a `roles` com `tenant_id NULLABLE`. Permissões
default são globais (NULL); cada tenant pode customizar via UI
(fora do MVP — apenas seedamos os papéis default).

### 5.3 Pivots

`model_has_roles`, `model_has_permissions`, `role_has_permissions`:
estrutura padrão do pacote, **sem** custom columns. Como `roles` já
carrega `tenant_id`, a relação User↔Role já fica escopada.

### 5.4 Papéis seedados (`DatabaseSeeder`)

| Nome | Tenant | Permissões nesta fase |
|---|---|---|
| `super-admin` | NULL (global) | Todas; bypass via Gate `before` |
| `admin-clinica` | NULL (template) | Tudo dentro do tenant: gestão de usuários, plano, billing, audit log, onboarding, configurações |
| `medico` | NULL (template) | Login, ver próprio perfil, alterar senha. (Profissional reaproveita perfil — o domínio de agenda/pacientes vem em fases futuras.) |
| `atendente` | NULL (template) | Login, ver próprio perfil, alterar senha (idem) |
| `recepcionista` | NULL (template) | idem `atendente` |
| `financeiro` | NULL (template) | Login, ver próprio perfil, ver billing/cota IA, ver audit log |

**Mecanismo de "template"**: ao criar um tenant, o `TenantService`
clona os papéis NULL para `tenant_id = X`, permitindo customização
futura sem afetar outros tenants.

---

## 6. `professionals` (esqueleto)

Esta fase entrega **apenas a tabela** para suportar
`subscriptions.professionals_quantity` (count of active records).
Toda a funcionalidade de agenda/horários/atendimentos vem na fase 4.

| Coluna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | BIGSERIAL | PK | |
| `tenant_id` | BIGINT | NOT NULL, FK → `tenants.id` | |
| `user_id` | BIGINT | NULL, FK → `users.id` ON DELETE SET NULL | NULL permite cadastrar profissional sem login (ex.: médico que não acessa o sistema). |
| `name` | VARCHAR(150) | NOT NULL | |
| `council_type` | VARCHAR(10) | NULL | `CRM`, `CRO`, `CRP` etc. (futura validação). |
| `council_number` | VARCHAR(20) | NULL | |
| `council_state` | CHAR(2) | NULL | UF. |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT TRUE | Conta para `professionals_quantity` somente quando TRUE. |
| `created_at`, `updated_at`, `deleted_at` | TIMESTAMPTZ | | |

**Índices**:

- `INDEX professionals_tenant_id_active_idx ON (tenant_id, is_active)`
- `INDEX professionals_user_id_idx ON (user_id)`

**Trigger / Service**: criação/desativação chama
`SubscriptionItemService::syncProfessionalsQuantity($tenant)` que
ajusta a quantity do item Stripe `base` (com proration).

---

## 7. `invitations`

Convites de usuário interno (US-2.2). Tabela separada do `users`
porque o usuário só vira `User` ao aceitar. Permite re-envio,
expiração, auditoria.

| Coluna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | BIGSERIAL | PK | |
| `tenant_id` | BIGINT | NOT NULL, FK → `tenants.id` | |
| `email` | VARCHAR(254) | NOT NULL | |
| `intended_role` | VARCHAR(50) | NOT NULL | Nome do role Spatie a aplicar no aceite. |
| `inviter_user_id` | BIGINT | NOT NULL, FK → `users.id` | Admin que enviou. |
| `token` | VARCHAR(64) | NOT NULL, UNIQUE | Random URL-safe. |
| `token_hash` | VARCHAR(255) | NOT NULL | Hash bcrypt do token; `token` em si nunca persiste em claro (compatível com cenário de DB dump). Na verdade só persistimos `token_hash`; o `token` em claro vai apenas no e-mail. ⚠ Ajuste: removo a coluna `token` em claro — fica só `token_hash`. |
| `expires_at` | TIMESTAMPTZ | NOT NULL | created_at + 24h. |
| `accepted_at` | TIMESTAMPTZ | NULL | Marcado no aceite. |
| `revoked_at` | TIMESTAMPTZ | NULL | Admin pode revogar. |
| `created_at`, `updated_at` | TIMESTAMPTZ | | |

**Índices**:

- `INDEX invitations_tenant_email_idx ON (tenant_id, email)`
- `INDEX invitations_expires_at_idx ON (expires_at)` para job de limpeza.

**Limpeza**: job semanal `PurgeExpiredInvitationsJob` deleta
registros com `expires_at < now() - 30d` E não aceitos.

---

## 8. `password_reset_tokens` (Laravel padrão)

Mantemos a tabela padrão do Laravel para `Password::sendResetLink`,
com adaptação: já é por e-mail, mas como temos multi-tenant,
adicionamos `tenant_id` opcional para escopar.

| Coluna | Tipo | Notas |
|---|---|---|
| `email` | VARCHAR(254) | PK composto |
| `tenant_id` | BIGINT | PK composto, FK → tenants |
| `token` | VARCHAR(255) | NOT NULL — hash do token. |
| `created_at` | TIMESTAMPTZ | |

PK composto `(email, tenant_id)`. Cada novo request invalida o
anterior do mesmo par.

---

## 9. `audit_logs` (e `audit_logs_cold`)

Tabela canônica de auditoria desta fase em diante. Estrutura conforme
FR-035 + decisão R4 (modelo único custom).

| Coluna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | BIGSERIAL | PK | |
| `tenant_id` | BIGINT | NULL, FK → `tenants.id` ON DELETE RESTRICT | NULL = ação do Super Admin sem tenant alvo (raro). |
| `user_id` | BIGINT | NULL, FK → `users.id` ON DELETE RESTRICT | NULL = ação do sistema (job, webhook). |
| `actor_type` | VARCHAR(20) | NOT NULL | Enum: `user`, `system`, `webhook`. |
| `action` | VARCHAR(100) | NOT NULL | Snake_case dot-notation. Ex.: `tenant.registered`, `user.login.succeeded`, `user.login.failed`, `plan.upgraded`, `invitation.sent`, `subscription.payment_failed`. |
| `auditable_type` | VARCHAR(150) | NULL | FQCN do Model alvo, se aplicável. |
| `auditable_id` | BIGINT | NULL | ID do alvo. |
| `payload` | JSONB | NOT NULL, DEFAULT `'{}'::jsonb` | Snapshot do que mudou (`old`, `new`) ou contexto livre. **Sem PII desnecessária.** |
| `ip` | INET | NULL | Captura via `$request->ip()`. |
| `user_agent` | VARCHAR(500) | NULL | |
| `request_id` | VARCHAR(50) | NULL | Correlation ID; cruzado com logs estruturados. |
| `created_at` | TIMESTAMPTZ | NOT NULL | Sem `updated_at`: registros são imutáveis. |

**Índices** (cuidado com volume):

- `INDEX audit_tenant_created_idx ON (tenant_id, created_at DESC)` —
  query principal do painel.
- `INDEX audit_action_created_idx ON (action, created_at DESC)`.
- `INDEX audit_auditable_idx ON (auditable_type, auditable_id)`.
- `BRIN INDEX audit_created_at_brin ON (created_at)` —
  range scans em arquivamento.

**Imutabilidade**: ausência de `updated_at` mais aplicação no Model
(`$model->save()` lança exceção em update). Trigger PG opcional para
defesa em profundidade.

### 9.b `audit_logs_cold`

Mesma estrutura sem os índices pesados (apenas BRIN +
`tenant_id`). Carrega registros movidos do hot pelo
`ArchiveAuditLogsJob` mensal.

---

## 10. `ai_usage_meters`

Contador mensal de mensagens IA por tenant. Atualizado por
job/listener em fases futuras; nesta fase é tabela vazia mas com
schema pronto para garantir que cota apareça no painel (US-1.5)
mesmo retornando 0.

| Coluna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | BIGSERIAL | PK | |
| `tenant_id` | BIGINT | NOT NULL, FK → tenants | |
| `year_month` | CHAR(7) | NOT NULL | `YYYY-MM`. Time bucket. |
| `messages_count` | INTEGER | NOT NULL, DEFAULT 0 | Atualizado atomicamente via `UPDATE ... SET messages_count = messages_count + 1`. |
| `included_quota_snapshot` | INTEGER | NOT NULL | Cota inclusa no momento da abertura do bucket (do `plan_snapshot`). Garante histórico fiel se plano mudar mid-month. |
| `overage_count` | INTEGER | NOT NULL, DEFAULT 0 | `max(0, messages_count - included_quota_snapshot)`. Materializado por trigger ou recalculado em SELECT. |
| `hard_cap` | INTEGER | NULL | NULL = sem cap. Quando atingido, `HardCapService` ativa modo template/escalonamento (FR-019). |
| `hard_cap_triggered_at` | TIMESTAMPTZ | NULL | Marca quando o cap foi atingido no ciclo. |
| `last_reset_at` | TIMESTAMPTZ | NOT NULL | Início do ciclo (= primeiro dia do `year_month` 00:00 BRT). |
| `created_at`, `updated_at` | | |

**Índices**:

- `UNIQUE INDEX ai_usage_tenant_month_uniq ON (tenant_id, year_month)`
- `INDEX ai_usage_hard_cap_triggered_idx ON (hard_cap_triggered_at)` para painel.

---

## 11. `stripe_events`

Idempotência de webhooks (R3).

| Coluna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | VARCHAR(255) | PK | `stripe_event_id` (`evt_...`). |
| `type` | VARCHAR(100) | NOT NULL | Ex.: `invoice.payment_failed`. |
| `payload` | JSONB | NOT NULL | Cópia integral do evento Stripe. |
| `received_at` | TIMESTAMPTZ | NOT NULL, DEFAULT NOW() | |
| `processed_at` | TIMESTAMPTZ | NULL | NULL = ainda não processado (job pendente ou falhou). |
| `failure_reason` | TEXT | NULL | Em caso de falha de processamento. |

**Sem `tenant_id`**: alguns eventos Stripe não têm tenant óbvio
(ex.: ping inicial). Correlação ao tenant ocorre durante o
processamento (`stripe_customer_id` → `tenants.stripe_customer_id`).

---

## 12. Sanctum: `personal_access_tokens`

Migration default do Sanctum. Sem custom columns.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | BIGSERIAL | PK |
| `tokenable_type`, `tokenable_id` | | Polimórfica. |
| `name` | VARCHAR(255) | |
| `token` | CHAR(64) | UNIQUE, hash. |
| `abilities` | TEXT | JSON. |
| `last_used_at`, `expires_at` | TIMESTAMPTZ | |
| `created_at`, `updated_at` | | |

Nesta fase, tokens emitidos apenas para ferramentas internas (CLI
super-admin, integração com CI/test runner). API SPA usa cookie
de sessão Sanctum (não personal access token).

---

## 13. `sessions` (Laravel padrão)

Driver de sessão = `database`. Migration padrão Laravel; preserva
auditoria (`last_activity`) e permite kill remoto.

---

## Resumo: tabelas criadas nesta fase

| # | Tabela | Tem `tenant_id`? | Soft-delete? | Notas |
|---|---|---|---|---|
| 1 | `tenants` | ❌ (é a raiz) | ✅ | |
| 2 | `plans` | ❌ (global) | ❌ | `is_active` flag |
| 3 | `subscriptions` | ✅ NOT NULL | ❌ | Cashier-compatível |
| 3b | `subscription_items` | indireto | ❌ | Cashier |
| 4 | `users` | ✅ NULLABLE | ✅ | NULL = Super Admin |
| 5a | `roles` | ✅ NULLABLE | ❌ | Spatie estendido |
| 5b | `permissions` | ✅ NULLABLE | ❌ | Spatie estendido |
| 5c | Pivots Spatie | indireto | ❌ | |
| 6 | `professionals` | ✅ NOT NULL | ✅ | Esqueleto |
| 7 | `invitations` | ✅ NOT NULL | ❌ | Hard delete via job |
| 8 | `password_reset_tokens` | ✅ NOT NULL (PK composto) | ❌ | |
| 9a | `audit_logs` | ✅ NULLABLE | ❌ | Imutável |
| 9b | `audit_logs_cold` | ✅ NULLABLE | ❌ | Imutável |
| 10 | `ai_usage_meters` | ✅ NOT NULL | ❌ | |
| 11 | `stripe_events` | ❌ (correlato) | ❌ | Idempotência |
| 12 | `personal_access_tokens` | indireto | ❌ | Sanctum |
| 13 | `sessions` | indireto | ❌ | Laravel default |

Total: ~13 tabelas funcionais + 4 pivots Spatie. Toda tabela com
`tenant_id NOT NULL` aplica trait `BelongsToTenant`. Tabelas com
`tenant_id NULLABLE` exigem opt-in caso a caso (Super Admin path
desliga o scope explicitamente).
