# Runbook — Operações Super Admin

> **T293 (Fase 8 — Polish)** — Procedimentos do painel `crm.com.br/admin` para suporte/CSM.

## Pré-requisitos

- User com role `super-admin` (`tenant_id IS NULL`).
- Acesso ao painel Filament `https://crm.com.br/admin` (cookie session — Fase 4).
- Acesso aos logs `audit_logs` (consulta read-only via tabela ou Filament).

## 1. Impersonate de tenant

**Quando**: suporte precisa replicar bug reportado, validar feature em produção, ou demonstrar operação ao cliente.

**Procedimento**:

1. Painel → **Tenants** → linha do tenant alvo → botão **Impersonate**.
2. Modal de confirmação exibe:
   - Nome + slug do tenant.
   - Motivo (campo obrigatório).
   - Duração (default 30min, máx. 2h).
3. Confirmar → redirect para `https://{tenant.slug}.crm.com.br/panel` com banner sticky vermelho.
4. Banner contém **botão "Sair"** (fim explícito) — usar sempre ao terminar.
5. **Toda tela visitada** durante impersonate gera audit:
   - `super_admin.session.started` (ao iniciar)
   - `super_admin.screen.visited` (cada tela)
   - `super_admin.session.ended` (ao sair)

**Não faça**:

- **NUNCA** edite dados clínicos (anotações, receitas) durante impersonate — apenas observe.
- **NUNCA** envie mensagens a pacientes durante impersonate — use o canal do CSM.
- **NUNCA** delete dados do tenant via impersonate — use suspend/cancel.

**Gate 7 (Constitution)**: cada sessão deve ter pelo menos um audit log de `screen.visited`. Sessão sem audit é flagged como anomalia.

## 2. Suspender tenant (inadimplência)

**Quando**: tenant com `subscriptions.status='past_due'` por ≥30 dias OU violação de ToS.

**Procedimento**:

1. Painel → **Tenants** → tenant → botão **Suspender**.
2. Preencher:
   - `reason` (obrigatório, livre — ex.: "inadimplência 35d").
3. Confirmar.
4. Efeitos imediatos:
   - `tenants.suspended_at = now()`
   - `tenants.suspended_by = $superAdmin->id`
   - **API tenant**: middleware `tenant.not-suspended` retorna 403.
   - **API pública**: middleware `api_public.tenant_not_suspended` retorna 503.
   - **Webhooks**: dispatcher pula endpoints do tenant.
   - **SPA**: tenant não consegue logar (`401 tenant_suspended`).
   - **Onboarding/Billing**: continuam acessíveis (regularização).

**Evento emitido**: `TenantSuspenso` (Auditable, persistido em `audit_logs`).

## 3. Reativar tenant suspenso

**Quando**: pagamento regularizado OU violação resolvida.

**Procedimento**:

1. Painel → **Tenants** → tenant suspenso → botão **Reativar**.
2. Sistema valida que:
   - `subscriptions.status='active'` OU `billing_mode='offline_invoice'`.
3. Confirmar → `suspended_at=null, suspended_by=null, suspended_reason=null`.
4. Banner vermelho removido da SPA do tenant na próxima request.

**Evento**: `TenantReativado` (Auditable).

## 4. Cancelar tenant (definitivo)

**Quando**: tenant decide encerrar contrato OU violação grave.

⚠️ **Operação destrutiva — retention 30d antes de purge real.**

**Procedimento**:

1. Painel → **Tenants** → tenant → botão **Cancelar**.
2. Preencher:
   - `reason` (obrigatório).
   - `data_export_requested` (boolean — gera PortabilityRequest automática).
3. Confirmar.
4. Efeitos:
   - `tenants.canceled_at = now()`
   - `retention_policy = 'delete_after_30d'`
   - Tenant sai do escopo da API pública e interna.
   - Cron `tenants:apply-retention` (daily) purga após 30d.

**Evento**: `TenantCancelado` (Auditable + emite `RetentionPolicySet`).

## 5. Criar tenant com billing offline_invoice

**Quando**: cliente enterprise que paga via NF mensal (sem Stripe).

**Procedimento**:

1. Painel → **Tenants** → **+ Novo**.
2. Preencher cadastro padrão + selecionar `billing_mode = 'offline_invoice'`.
3. Selecionar plano versionado (lookup em `plan_versions`).
4. Confirmar → tenant criado sem cobrança automática, sem trial.
5. Tenant fica em status `active`. Suspensão por inadimplência é manual.

**Evento**: `TenantCriadoPorSuperAdmin` com `billing_mode='offline_invoice'`.

## 6. Gerenciar planos versionados (`plan_versions`)

**Quando**: criar plano novo ou alterar preço/limites de plano existente.

**Procedimento**:

1. Painel → **Plans** → **+ Nova versão**.
2. Selecionar plano base + campos:
   - `version_number` (auto-increment recomendado).
   - `daily_campaign_limit`, `api_rate_limit_per_minute`, `webhook_max_endpoints`.
   - `price_cents_monthly`.
   - `effective_from` (data — tenant existing continua na versão antiga até renovação).
3. Confirmar.
4. Tenants novos contratados após `effective_from` recebem a nova versão automaticamente.
5. Tenants existentes só migram via upgrade/downgrade explícito.

**Evento**: `PlanoAlteradoPeloSuperAdmin` com snapshot do diff (Auditable).

## 7. Investigar anomalias

**Painel**: `crm.com.br/admin/pages/anomalies-page`.

**Tipos de anomalias monitoradas**:

- `mass_data_export` — Exportações de >500 pacientes em 5min.
- `unusual_impersonate` — Sessões impersonate fora do horário comercial.
- `controlled_prescription_scan` — >10 acessos a controladas de pacientes diferentes em 5min.
- `webhook_delivery_failure_spike` — Endpoint com >50 falhas/hora.

**Procedimento**:

1. Abrir lista → filtrar `status=pending`.
2. Para cada anomalia, decidir:
   - **False positive** → marcar `dismissed` com nota.
   - **Real** → criar ticket no Linear + acionar tenant.

## Observabilidade

- Dashboard Grafana: `docs/observability/grafana-fase8.json`.
- Métricas-chave:
  - `impersonate_sessions_active` (Gauge)
  - `super_admin_anomaly_detected_total{type}` (Counter)
  - `tenant_lifecycle_total{event}` (Counter — created/suspended/reactivated/canceled)

## Auditoria

Todas as operações acima emitem eventos `Auditable` persistidos em `audit_logs`:

- Filtro útil: `actor_type='user' AND action LIKE 'super_admin.%'`
- Retenção: `audit_logs` mantém 5 anos (Q20, Princípio VII).
