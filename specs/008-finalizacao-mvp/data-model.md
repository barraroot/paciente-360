# Data Model: Finalização do MVP (Fase 8)

**Branch**: `008-finalizacao-mvp` | **Date**: 2026-05-21 | **Phase**: 1

---

## 0. Sumário

24 tabelas novas + 3 ALTERs em tabelas existentes, distribuídas pelos 5 módulos:

| Lote | Módulo | Tabelas novas | ALTERs |
|---|---|---|---|
| A | Privacidade | 4 | 1 (`patients`) |
| B | Super Admin | 5 | 2 (`tenants`, `plans`) |
| C | Campanhas | 4 | — |
| D | Integrações | 4 | — |
| E | Relatórios | 2 | — |
| Passport (opt-in) | Integrações | 5 (gated) | — |

**Convenção universal**:

- Toda tabela carrega `id bigserial PK`, `created_at timestamptz`, `updated_at timestamptz`.
- Tabelas tenant-scoped carregam `tenant_id bigint NOT NULL` com FK em `tenants(id)` e index `(tenant_id, ...)` no primeiro campo de query frequente.
- Tabelas globais do Super Admin **NÃO** têm `tenant_id` (são naturally global) — Princípio II exceção explícita.
- Soft delete (`deleted_at timestamptz NULL`) aplicado apenas onde justificado pela regulação (ex.: `consent_records` para manter prova).
- Timestamps em UTC; conversão para TZ tenant no Resource.

---

## 1. Lote A — Privacidade/LGPD

### 1.1 `consent_records` (TENANT-SCOPED)

Registro de cada consentimento dado, recusado ou revogado.

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK→tenants | |
| `patient_id` | bigint | NOT NULL, FK→patients | |
| `channel` | varchar(50) | NOT NULL | `whatsapp`, `instagram`, `web`, `form`, `manual` |
| `finalidade` | varchar(20) | NOT NULL, CHECK ∈ {transacional, marketing, pesquisa} | Q24 |
| `state` | varchar(20) | NOT NULL, CHECK ∈ {granted, refused, revoked} | |
| `granted_at` | timestamptz | NULL | |
| `revoked_at` | timestamptz | NULL | |
| `evidence_message_id` | bigint | NULL, FK→messages | Fase 3 — referência à mensagem prova |
| `evidence_snapshot` | jsonb | NULL | Snapshot textual quando `evidence_message_id` é null |
| `terms_version` | varchar(50) | NOT NULL | Versão dos T&C vigentes ao consentir |
| `scope` | jsonb | NULL | Reservado para granularidade futura (ex.: revogação só de canal X) |

**Indexes**:
- `(tenant_id, patient_id, finalidade)` — lookup mais comum
- UNIQUE PARTIAL `(patient_id, finalidade) WHERE state='granted'` — apenas 1 consentimento ativo por finalidade por paciente
- `(tenant_id, granted_at)` — relatórios de período

**Relações**: `patient_id → patients(id)`; `evidence_message_id → messages(id)`.

---

### 1.2 `forgetting_requests` (TENANT-SCOPED)

Solicitação formal de Direito ao Esquecimento (LGPD Art. 18º).

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK | |
| `patient_id` | bigint | NOT NULL, FK→patients | |
| `requested_at` | timestamptz | NOT NULL | |
| `deadline_at` | timestamptz | NOT NULL | D+15 dias úteis |
| `channel_of_request` | varchar(50) | NOT NULL | `whatsapp`, `instagram`, `web`, `form`, `email`, `manual` |
| `status` | varchar(30) | NOT NULL, CHECK ∈ {open, pending_verification, in_progress, executed, expired, denied} | |
| `executed_at` | timestamptz | NULL | |
| `executed_by_user_id` | bigint | NULL, FK→users | |
| `fields_anonymized` | jsonb | NULL | Array de campos efetivamente anonimizados (Q26) |
| `fields_deleted` | jsonb | NULL | Array de campos efetivamente deletados |
| `fields_preserved_reason` | jsonb | NULL | Lista de campos preservados com motivo legal (ex.: `[{field: "controlled_prescription_X", reason: "portaria_344_98", retention_until: "2028-..."}]`) |
| `denial_reason` | text | NULL | Quando `status=denied` |

**Indexes**:
- `(tenant_id, status, deadline_at)` — busca de tarefas pendentes
- `(deadline_at)` — cron de notificação D-5/D-2/vencido

---

### 1.3 `portability_requests` (TENANT-SCOPED)

Solicitação de Portabilidade de Dados (Q28, LGPD Art. 18º V).

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK | |
| `patient_id` | bigint | NOT NULL, FK→patients | |
| `requested_at` | timestamptz | NOT NULL | |
| `deadline_at` | timestamptz | NOT NULL | D+15 dias úteis |
| `status` | varchar(30) | NOT NULL, CHECK ∈ {open, generating, ready, downloaded, expired, denied} | |
| `executed_at` | timestamptz | NULL | quando arquivo gerado |
| `executed_by_user_id` | bigint | NULL, FK→users | |
| `file_path` | varchar(500) | NULL | Path S3: `privacy/portability/{patient_id}/{request_id}.json` |
| `file_size_bytes` | bigint | NULL | |
| `file_signed_url_id` | uuid | NULL | Token único para URL assinada |
| `url_expires_at` | timestamptz | NULL | 7 dias após geração |
| `downloaded_at` | timestamptz | NULL | Audit do download (primeira vez) |
| `schema_version` | varchar(10) | NOT NULL DEFAULT '1.0' | Versão do schema JSON exportado |

**Indexes**:
- `(tenant_id, status, deadline_at)`
- `(file_signed_url_id)` UNIQUE — lookup no download

---

### 1.4 `pseudonymization_audits` (GLOBAL — audit table)

Resultado de cada execução da auditoria semanal (Q29).

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `audited_at` | timestamptz | NOT NULL | |
| `audited_by_user_id` | bigint | NULL, FK→users | NULL = job automático |
| `mode` | varchar(30) | NOT NULL, CHECK ∈ {static_reflection, runtime_replay} | |
| `scope_event_types` | jsonb | NOT NULL | Array dos event_types auditados |
| `sample_size` | int | NULL | apenas para `runtime_replay` |
| `total_events_scanned` | int | NOT NULL | |
| `non_conformant_events` | int | NOT NULL | |
| `findings` | jsonb | NOT NULL | Array de `{event_id, field_path, pattern_matched, severity}` (sem o valor real) |
| `report_summary` | text | NULL | Markdown summary para painel |

**Indexes**: `(audited_at DESC)`.

---

### 1.5 ALTER `patients`

```sql
ALTER TABLE patients ADD COLUMN share_with_integrations_consent boolean NOT NULL DEFAULT false;
```

Consumido pelo `WebhookDispatcher` (AC-11.1.7) para mascarar paciente não consentido em payload de integração.

---

## 2. Lote B — Super Admin

### 2.1 `plan_versions` (GLOBAL)

Snapshot versionado de cada plano. Tenants existentes ficam vinculados à versão original ao editar (Q12.2.2).

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `plan_id` | bigint | NOT NULL, FK→plans | Plano de referência (Fase 0) |
| `version` | int | NOT NULL | Auto-incremento por plan_id (1, 2, 3...) |
| `valid_from` | timestamptz | NOT NULL | |
| `valid_to` | timestamptz | NULL | NULL = ativo |
| `snapshot` | jsonb | NOT NULL | Snapshot completo: `{name, base_price_cents, included_professionals, included_ai_messages, daily_campaign_limit, api_rate_limit_per_minute, webhook_max_endpoints, features_enabled[]}` |
| `created_by_user_id` | bigint | NOT NULL, FK→users | Super admin |

**Indexes**:
- UNIQUE `(plan_id, version)`
- `(plan_id, valid_to)` — busca da versão ativa

---

### 2.2 `tenant_plan_bindings` (GLOBAL)

Liga tenant à versão específica do plano.

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK→tenants | |
| `plan_version_id` | bigint | NOT NULL, FK→plan_versions | |
| `effective_from` | timestamptz | NOT NULL | |
| `effective_to` | timestamptz | NULL | NULL = vigente |
| `changed_by_user_id` | bigint | NULL, FK→users | NULL para vínculo inicial; Super Admin para alterações |
| `change_reason` | text | NULL | Obrigatório (≥10 chars) quando `changed_by_user_id IS NOT NULL` |

**Indexes**:
- UNIQUE PARTIAL `(tenant_id) WHERE effective_to IS NULL` — apenas 1 vínculo vigente por tenant
- `(tenant_id, effective_from DESC)` — histórico

---

### 2.3 `impersonate_sessions` (GLOBAL)

Registro de cada sessão de impersonate (Q19).

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `super_admin_id` | bigint | NOT NULL, FK→users | |
| `tenant_id` | bigint | NOT NULL, FK→tenants | Tenant alvo |
| `started_at` | timestamptz | NOT NULL | |
| `ended_at` | timestamptz | NULL | NULL = ativa |
| `duration_seconds` | int | NULL | Calculado ao encerrar |
| `scope` | varchar(20) | NOT NULL DEFAULT 'full' | Q19 |
| `ip_address` | inet | NOT NULL | |
| `user_agent` | text | NULL | |
| `screens_visited_count` | int | NOT NULL DEFAULT 0 | Incrementado por trigger via `super_admin_audit_screens` |
| `reason` | text | NOT NULL | ≥10 chars — justificativa de abertura |

**Indexes**:
- `(super_admin_id, started_at DESC)`
- `(tenant_id, started_at DESC)`
- PARTIAL `(super_admin_id) WHERE ended_at IS NULL` — bloqueia 2 sessões abertas simultâneas pelo mesmo Super Admin

---

### 2.4 `super_admin_audit_screens` (GLOBAL)

Audit granular de cada tela visitada durante impersonate (Q19/Gate 7).

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `impersonate_session_id` | bigint | NOT NULL, FK→impersonate_sessions | |
| `route` | varchar(255) | NOT NULL | Nome da rota Laravel (ex.: `tenant.patients.show`) |
| `path` | varchar(500) | NOT NULL | URL completa visitada |
| `method` | varchar(10) | NOT NULL | GET, POST, etc. |
| `visited_at` | timestamptz | NOT NULL | |
| `ip_address` | inet | NOT NULL | |
| `query_params` | jsonb | NULL | Filtros aplicados (sem corpo) |

**Indexes**:
- `(impersonate_session_id, visited_at)`
- `(route, visited_at DESC)` — análise por superfície

---

### 2.5 `anomalies_detected` (GLOBAL)

Histórico de anomalias detectadas pelo `super-admin:detect-anomalies` (Q22).

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `categoria` | varchar(50) | NOT NULL, CHECK ∈ {conversion_drop, ai_usage_spike, webhook_failure_rate, payment_overdue} | |
| `tenant_id` | bigint | NULL, FK→tenants | NULL para anomalias globais (ex.: queda de conversão da plataforma) |
| `severity` | varchar(10) | NOT NULL, CHECK ∈ {warning, critical} | |
| `threshold_breached` | jsonb | NOT NULL | `{metric, threshold, observed_value}` |
| `detected_at` | timestamptz | NOT NULL | |
| `notified_via` | jsonb | NOT NULL DEFAULT '[]' | `["inbox", "email"]` — canais usados |
| `acknowledged_at` | timestamptz | NULL | |
| `acknowledged_by_user_id` | bigint | NULL, FK→users | |
| `resolved_at` | timestamptz | NULL | |

**Indexes**:
- `(categoria, severity, detected_at DESC)`
- `(tenant_id, detected_at DESC)` partial WHERE `tenant_id IS NOT NULL`

---

### 2.6 ALTER `tenants` (5 cols)

```sql
ALTER TABLE tenants
  ADD COLUMN suspended_at timestamptz NULL,
  ADD COLUMN suspended_by_user_id bigint NULL REFERENCES users(id),
  ADD COLUMN suspended_reason text NULL,
  ADD COLUMN canceled_at timestamptz NULL,
  ADD COLUMN retention_policy varchar(50) NOT NULL DEFAULT 'differentiated_per_category',
  ADD COLUMN billing_mode varchar(20) NOT NULL DEFAULT 'stripe' CHECK (billing_mode IN ('stripe', 'offline_invoice'));
```

Indexes adicionais:
- `(suspended_at)` PARTIAL `WHERE suspended_at IS NOT NULL` — busca de tenants suspensos
- `(canceled_at)` PARTIAL `WHERE canceled_at IS NOT NULL` — busca de cancelados em janela
- `(billing_mode)` — para queries do Filament

### 2.7 ALTER `plans` (3 cols)

```sql
ALTER TABLE plans
  ADD COLUMN daily_campaign_limit int NOT NULL DEFAULT 200,
  ADD COLUMN api_rate_limit_per_minute int NOT NULL DEFAULT 100,
  ADD COLUMN webhook_max_endpoints int NOT NULL DEFAULT 5;
```

Para tenants existentes, defaults aplicam (tier mais restritivo). Migration de B inclui seed que ajusta planos `pro` (1000/1000/20) e `enterprise` (5000/5000/100) se existirem.

---

## 3. Lote C — Campanhas

### 3.1 `campaigns` (TENANT-SCOPED)

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK | |
| `name` | varchar(200) | NOT NULL | |
| `status` | varchar(20) | NOT NULL, CHECK ∈ {draft, scheduled, dispatching, completed, canceled} | |
| `channel` | varchar(20) | NOT NULL, CHECK ∈ {whatsapp, instagram, sms_future} | Q3 — canal único |
| `template_id` | bigint | NULL, FK→message_templates | Fase 3 |
| `audience_filters` | jsonb | NOT NULL | `{inactivity_months, tags[], last_professional_id, age_range, gender, last_procedure_type_id}` |
| `scheduled_for` | timestamptz | NULL | NULL = "disparar agora" |
| `dispatched_at` | timestamptz | NULL | |
| `total_eligible` | int | NULL | Snapshot do público no momento do dispatch |
| `total_dispatched` | int | NOT NULL DEFAULT 0 | |
| `total_blocked` | int | NOT NULL DEFAULT 0 | |
| `daily_limit_applied` | int | NOT NULL | Snapshot do `plan.daily_campaign_limit` no momento do dispatch (Q2) |
| `canceled_at` | timestamptz | NULL | |
| `canceled_by_user_id` | bigint | NULL, FK→users | |
| `canceled_reason` | text | NULL | |
| `created_by_user_id` | bigint | NOT NULL, FK→users | |

**Indexes**:
- `(tenant_id, status)`
- `(tenant_id, scheduled_for)` PARTIAL `WHERE status='scheduled'` — cron `dispatch-scheduled`
- `(tenant_id, created_at DESC)`

---

### 3.2 `campaign_recipients` (TENANT-SCOPED)

Snapshot de cada destinatário no momento do disparo.

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK | |
| `campaign_id` | bigint | NOT NULL, FK→campaigns | |
| `patient_id` | bigint | NOT NULL, FK→patients | |
| `dispatched_at` | timestamptz | NULL | |
| `status` | varchar(20) | NOT NULL DEFAULT 'pending', CHECK ∈ {pending, sent, delivered, read, responded, blocked, failed} | |
| `blocked_reason` | varchar(50) | NULL | `no_marketing_opt_in`, `no_template_approved`, `outside_business_hours`, `daily_limit_exceeded`, `no_reachable_channel`, `sair_received_24h`, `template_no_longer_approved` |
| `external_message_id` | varchar(255) | NULL | ID retornado pela Meta após envio |
| `delivered_at` | timestamptz | NULL | |
| `read_at` | timestamptz | NULL | |
| `responded_at` | timestamptz | NULL | |
| `attributed_appointment_id` | bigint | NULL, FK→appointments | Vinculação SC-9.3 (≤7d) |

**Indexes**:
- UNIQUE `(campaign_id, patient_id)` — idempotência absoluta (deduplicação Q AC-9.1.6)
- `(tenant_id, campaign_id, status)`
- `(tenant_id, patient_id, dispatched_at DESC)` — timeline do paciente

---

### 3.3 `campaign_dispatch_log` (TENANT-SCOPED)

Log granular de cada tentativa de envio (para auditoria de bloqueios — Princípio VI).

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK | |
| `campaign_id` | bigint | NOT NULL, FK | |
| `patient_id` | bigint | NOT NULL, FK | |
| `attempted_at` | timestamptz | NOT NULL | |
| `result` | varchar(20) | NOT NULL, CHECK ∈ {sent, blocked, failed} | |
| `block_reason` | varchar(50) | NULL | mesmas opções de `campaign_recipients.blocked_reason` |
| `details` | jsonb | NULL | Contexto adicional para debug |

**Indexes**:
- `(campaign_id, attempted_at DESC)`
- `(tenant_id, result, attempted_at)` — relatório de compliance

---

### 3.4 `campaign_templates_meta` (TENANT-SCOPED)

Cache do status Meta de cada template HSM aprovado (Fase 3 tem `message_templates`; aqui adicionamos metadados específicos de campanha).

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK | |
| `template_id` | bigint | NOT NULL, FK→message_templates | |
| `meta_status` | varchar(20) | NOT NULL, CHECK ∈ {pending, approved, rejected, expired} | |
| `meta_status_last_checked_at` | timestamptz | NOT NULL | |
| `meta_template_id` | varchar(255) | NULL | ID do template na Meta Cloud API |
| `has_unsubscribe` | boolean | NOT NULL | Validado na criação (AC-9.3.3) |
| `language` | varchar(10) | NOT NULL DEFAULT 'pt_BR' | |

**Indexes**:
- UNIQUE `(template_id)`
- `(meta_status, meta_status_last_checked_at)` — cron de refresh

---

## 4. Lote D — Integrações

### 4.1 `webhook_endpoints` (TENANT-SCOPED)

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK | |
| `url` | varchar(500) | NOT NULL | HTTPS obrigatório |
| `secret_hash` | varchar(255) | NOT NULL | SHA-256 do segredo (plaintext só na criação) |
| `events_subscribed` | jsonb | NOT NULL | Array de event_types (Q17 — máx 13 valores) |
| `status` | varchar(20) | NOT NULL DEFAULT 'active', CHECK ∈ {active, paused, deleted} | |
| `created_by_user_id` | bigint | NOT NULL, FK→users | |
| `last_delivery_at` | timestamptz | NULL | |
| `last_delivery_status` | varchar(20) | NULL | |

**Indexes**:
- `(tenant_id, status)` PARTIAL `WHERE status='active'`
- `(tenant_id)` — listagem
- Constraint: tenant não pode exceder `plan.webhook_max_endpoints`

---

### 4.2 `webhook_deliveries` (TENANT-SCOPED)

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK | |
| `webhook_endpoint_id` | bigint | NOT NULL, FK→webhook_endpoints | |
| `event_type` | varchar(100) | NOT NULL | |
| `event_id` | varchar(64) | NOT NULL | Correlation id |
| `payload` | jsonb | NOT NULL | |
| `attempt_number` | smallint | NOT NULL DEFAULT 1 | 1–5 |
| `status` | varchar(20) | NOT NULL, CHECK ∈ {pending, delivered, failed, dlq, expired} | |
| `scheduled_for` | timestamptz | NOT NULL | Quando a tentativa deve rodar |
| `executed_at` | timestamptz | NULL | |
| `response_status_code` | smallint | NULL | |
| `response_body_excerpt` | text | NULL | Truncado em 1KB |
| `latency_ms` | int | NULL | |
| `last_error` | text | NULL | |

**Indexes**:
- `(webhook_endpoint_id, event_type, attempt_number)` — histórico
- `(status, scheduled_for)` PARTIAL `WHERE status='pending'` — cron picker
- `(event_id)` — correlation
- `(tenant_id, executed_at DESC)`

---

### 4.3 `webhook_dead_letter` (TENANT-SCOPED)

Eventos que esgotaram 5 tentativas (Q16). Retenção 30 dias.

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK | |
| `webhook_endpoint_id` | bigint | NOT NULL, FK | |
| `event_type` | varchar(100) | NOT NULL | |
| `event_id` | varchar(64) | NOT NULL | |
| `final_payload` | jsonb | NOT NULL | |
| `last_error` | text | NOT NULL | |
| `moved_to_dlq_at` | timestamptz | NOT NULL | |
| `expires_at` | timestamptz | NOT NULL | moved_to_dlq_at + 30 dias |
| `resent_at` | timestamptz | NULL | Quando Admin Clínica clica reenviar |
| `resent_attempt_id` | bigint | NULL, FK→webhook_deliveries | |

**Indexes**:
- `(tenant_id, moved_to_dlq_at DESC)`
- `(expires_at)` — cron de purge

---

### 4.4 `oauth_clients` (TENANT-SCOPED, GATED)

Apenas criada se `config('integrations.oauth_enabled')` ativo no tenant.

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK | |
| `client_id` | varchar(64) | NOT NULL UNIQUE | UUID v4 |
| `client_secret_hash` | varchar(255) | NOT NULL | SHA-256 |
| `name` | varchar(200) | NOT NULL | Descrição amigável |
| `scopes` | jsonb | NOT NULL | Array de escopos OAuth permitidos |
| `revoked_at` | timestamptz | NULL | |
| `created_by_user_id` | bigint | NOT NULL, FK→users | |

**Indexes**: `(client_id)` UNIQUE.

**Tabelas Passport adicionais** (criadas via `php artisan passport:install` quando primeiro tenant habilita — não são parte desta migration): `oauth_access_tokens`, `oauth_refresh_tokens`, `oauth_auth_codes`, `oauth_personal_access_clients`, `oauth_clients` (do Passport, separada de `oauth_clients` desta fase — renomeia para evitar colisão).

> ⚠️ **Naming conflict**: se Passport já tem tabela `oauth_clients`, esta fase usa `tenant_oauth_clients` para evitar colisão. Decision em research.md §3.2.

**Revisão da decisão**: tabela acima renomeada para **`tenant_oauth_clients`**.

---

## 5. Lote E — Relatórios

### 5.1 `metric_aggregations` (TENANT-SCOPED)

Pré-computação horária de KPIs por tenant.

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK | |
| `metric_name` | varchar(100) | NOT NULL | Ex.: `leads_by_channel`, `conversion_rate`, `no_show_rate`, `estimated_revenue`, `response_time_first_p95`, `ai_autonomous_resolution_rate` |
| `period` | varchar(10) | NOT NULL, CHECK ∈ {hour, day, week, month} | |
| `period_start` | timestamptz | NOT NULL | Início do bucket |
| `dimensions` | jsonb | NULL | `{channel, professional_id, etc.}` |
| `value_numeric` | numeric(20,4) | NULL | Para métricas escalares |
| `value_json` | jsonb | NULL | Para métricas estruturadas (ranking, agregações) |
| `computed_at` | timestamptz | NOT NULL | |

**Indexes**:
- UNIQUE `(tenant_id, metric_name, period, period_start, dimensions)` — idempotência do job
- `(tenant_id, metric_name, period_start DESC)` — query do dashboard
- `(period, period_start)` PARTIAL `WHERE period='hour'` — busca de agregações stale

---

### 5.2 `report_exports` (TENANT-SCOPED)

Audit de cada exportação CSV/PDF.

| Coluna | Tipo | Constraints | Descrição |
|---|---|---|---|
| `id` | bigserial | PK | |
| `tenant_id` | bigint | NOT NULL, FK | |
| `tipo` | varchar(30) | NOT NULL, CHECK ∈ {executive_dashboard, operational, clinical, campaigns} | |
| `formato` | varchar(10) | NOT NULL, CHECK ∈ {csv, pdf} | |
| `filters_applied` | jsonb | NOT NULL | Snapshot dos filtros |
| `exported_by_user_id` | bigint | NOT NULL, FK→users | |
| `exported_at` | timestamptz | NOT NULL | |
| `file_path` | varchar(500) | NULL | S3 path quando file grande (assinada TTL 1h) |
| `file_size_bytes` | bigint | NULL | |
| `row_count` | int | NULL | Para CSV |

**Indexes**:
- `(tenant_id, exported_at DESC)`
- `(exported_by_user_id, exported_at DESC)` — auditoria por usuário

---

## 6. Relacionamentos cross-módulo

```
patients (Fase 2) ─┬─→ consent_records (A)
                   ├─→ forgetting_requests (A)
                   ├─→ portability_requests (A)
                   ├─→ campaign_recipients (C)
                   └─→ (share_with_integrations_consent col em ALTER)

tenants (Fase 0) ─┬─→ tenant_plan_bindings (B) ─→ plan_versions (B) ─→ plans (Fase 0)
                  ├─→ campaigns (C)
                  ├─→ webhook_endpoints (D)
                  ├─→ tenant_oauth_clients (D, gated)
                  ├─→ metric_aggregations (E)
                  └─→ (5 cols em ALTER: suspended_*, canceled_at, retention_policy, billing_mode)

users (Fase 0) ──┬─→ impersonate_sessions.super_admin_id (B)
                 ├─→ campaigns.created_by_user_id (C)
                 ├─→ webhook_endpoints.created_by_user_id (D)
                 ├─→ tenant_oauth_clients.created_by_user_id (D)
                 ├─→ forgetting_requests.executed_by_user_id (A)
                 ├─→ portability_requests.executed_by_user_id (A)
                 └─→ report_exports.exported_by_user_id (E)

impersonate_sessions (B) ──→ super_admin_audit_screens (B)

webhook_endpoints (D) ──┬─→ webhook_deliveries (D)
                        └─→ webhook_dead_letter (D)

campaigns (C) ──┬─→ campaign_recipients (C)
                ├─→ campaign_dispatch_log (C)
                └─→ campaign_templates_meta (C, via template_id)

appointments (Fase 5) ←──── campaign_recipients.attributed_appointment_id (C)

messages (Fase 3) ←──── consent_records.evidence_message_id (A)
```

---

## 7. State Transitions

### 7.1 Campaign lifecycle

```
draft → scheduled       (set scheduled_for, AC-9.2.1)
draft → dispatching     ("Disparar agora")
scheduled → dispatching (cron `campaigns:dispatch-scheduled` cruza scheduled_for)
scheduled → canceled    (admin clica cancelar antes do disparo)
dispatching → completed (todos os recipients processados)
dispatching → canceled  (admin clica pausar/cancelar)
```

### 7.2 ForgettingRequest lifecycle

```
open → pending_verification  (identidade não confirmada)
open → in_progress          (admin clica "Executar")
pending_verification → in_progress  (admin confirma identidade)
pending_verification → denied (admin nega após verificação)
in_progress → executed       (anonimização aplicada)
open|pending_verification|in_progress → expired (deadline_at < now() sem ação)
```

### 7.3 WebhookDelivery lifecycle

```
pending (attempt=1) → delivered (HTTP 2xx)
pending → failed (HTTP 5xx/timeout) → pending (attempt+1) → ...
... → pending (attempt=5) → failed → dlq (move para webhook_dead_letter)
dlq → expired (depois de 30d)
dlq → pending (admin clica "Reenviar manualmente" — cria novo delivery com attempt=1)
```

### 7.4 Tenant lifecycle (delta desta fase)

```
trial → ativo                  (Fase 0)
ativo → inadimplente           (Fase 0)
ativo|inadimplente → suspenso  (Super Admin clica, AC-12.1.3) — fila pausada
suspenso → ativo               (Super Admin reativa, AC-12.1.4)
ativo|inadimplente|suspenso → cancelado (AC-12.1.10) — aplica retention_policy diferenciada
```

---

## 8. Migrations (ordem cronológica)

Cada lote produz um conjunto numerado. Migrations dentro do lote são ordenadas para que FK references sejam válidas.

| Date | Lote | File |
|---|---|---|
| 2026_05_22_000001 | A | create_consent_records_table |
| 2026_05_22_000002 | A | create_forgetting_requests_table |
| 2026_05_22_000003 | A | create_portability_requests_table |
| 2026_05_22_000004 | A | create_pseudonymization_audits_table |
| 2026_05_22_000005 | A | alter_patients_add_share_with_integrations_consent |
| 2026_05_23_000001 | B | alter_tenants_add_lifecycle_columns |
| 2026_05_23_000002 | B | alter_plans_add_limits_columns |
| 2026_05_23_000003 | B | create_plan_versions_table |
| 2026_05_23_000004 | B | create_tenant_plan_bindings_table |
| 2026_05_23_000005 | B | seed_initial_plan_versions_from_existing_plans |
| 2026_05_23_000006 | B | create_impersonate_sessions_table |
| 2026_05_23_000007 | B | create_super_admin_audit_screens_table |
| 2026_05_23_000008 | B | create_anomalies_detected_table |
| 2026_05_24_000001 | C | create_campaigns_table |
| 2026_05_24_000002 | C | create_campaign_recipients_table |
| 2026_05_24_000003 | C | create_campaign_dispatch_log_table |
| 2026_05_24_000004 | C | create_campaign_templates_meta_table |
| 2026_05_25_000001 | D | create_webhook_endpoints_table |
| 2026_05_25_000002 | D | create_webhook_deliveries_table |
| 2026_05_25_000003 | D | create_webhook_dead_letter_table |
| 2026_05_25_000004 | D | create_tenant_oauth_clients_table |
| 2026_05_26_000001 | E | create_metric_aggregations_table |
| 2026_05_26_000002 | E | create_report_exports_table |

Total: **22 migrations** (24 tabelas — algumas combinadas em uma única migration).

---

## 9. Considerações de performance e indexação

- **`campaign_recipients`** terá volume alto (~milhões de rows por tenant grande); particionamento por `tenant_id` opcional em fase posterior se p95 degradar.
- **`webhook_deliveries`** crescimento rápido (centenas/dia por tenant ativo); cron de purge agressivo (>90 dias = delete) deve ser adicionado em fase futura.
- **`metric_aggregations`** com índice composto `(tenant_id, metric_name, period_start DESC)` cobre todas as queries do dashboard.
- **`audit_logs`** (Fase 2, não esta fase) ganha novos `auditable_type` values: `Campaign`, `Webhook`, `Tenant` (impersonate), `ForgettingRequest`, etc. Sem mudança de schema.

---

## 10. Conclusão Phase 1 (data-model)

✅ 24 tabelas + 3 ALTERs definidos com colunas, tipos, constraints e indexes
✅ Relacionamentos cross-módulo mapeados
✅ State transitions documentados para 4 entidades principais
✅ Ordem de migrations definida
✅ Considerações de performance e crescimento antecipadas

→ Próximos: `contracts/` (OpenAPI) e `quickstart.md` (smoke E2E).
