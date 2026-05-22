# Implementation Plan: Finalização do MVP (Fase 8 — Épicos 9, 10, 11, 12 e 13)

**Branch**: `008-finalizacao-mvp` | **Date**: 2026-05-21 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/008-finalizacao-mvp/spec.md`

---

## 0. Sumário Executivo

A Fase 8 fecha o MVP entregando **cinco módulos** em uma única feature: Campanhas (Épico 9), Relatórios (Épico 10), Integrações/Webhooks/API Pública (Épico 11), Painel Super Admin (Épico 12) e Privacidade/LGPD (Épico 13). Cobertura completa de **RF-054 a RF-064** + **RNF-006 a RNF-012**.

**Abordagem técnica de alto nível**:

- **Modularização por domínio**: cada um dos 5 módulos vive em seu próprio sub-namespace `app/Domain/{Campaigns,Reports,Integrations,SuperAdmin,Privacy}/` e expõe Services injetáveis. Lotes de implementação são organizados por módulo (A–E) com dependências explícitas entre eles (B depende de A para métricas de campanha; D depende de A/B para impersonate ver dados desses módulos; E é gate regulatório que toca todos).
- **Reaproveitamento agressivo de patterns existentes**: cron `withoutOverlapping()` (Fase 5), idempotência dual Redis+DB (Fase 7), marker interface `ContainsNoClinicalData` (Fase 7), `BelongsToTenant` global scope (Fase 1), Sanctum tokens hashados (Fase 4), audit_logs + listener pattern (Fase 2).
- **Stack mínima nova**: zero novas dependências obrigatórias. Filament 5 já presente (Fase 0); Cashier 16 já em uso (Fase 0); HMAC SHA-256 via openssl_hmac built-in PHP; OAuth opt-in usando `laravel/passport` apenas para enterprise (gateado por flag — se nenhum tenant usar, não instalar).
- **Conformidade Meta + LGPD em runtime**: dispatcher de campanha aplica 4 validações sequenciais antes de cada envio individual (opt-in marketing, template aprovado, business_hours, daily limit do plano); auditoria de pseudonimização vira gate de CI estendendo o teste já existente da Fase 7.
- **Painel Super Admin tem quebra de scope explícita**: único contexto em todo o produto onde `withoutGlobalScopes()` é permitido — gateado por perfil `super_admin` + audit por tela visitada (clarify Q19).

**Estado**: Spec **Clarified** (29/29 NEEDS CLARIFICATION resolvidos em 2026-05-21). Nenhuma ambiguidade técnica remanescente que impeça Phase 0.

---

## 1. Discrepâncias com o estado real — Alinhamentos aplicados

| Suposição inicial | Realidade do projeto | Decisão |
|---|---|---|
| "Fase 4 = IA Matricial" referenciado no briefing original do spec | Fase 4 real é `004-token-auth-migration` (Cookie→Bearer Sanctum). IA Matricial é **fase futura**. | Eventos pseudonimizados desta fase (auditoria Q29) reforçam o **contrato forward-looking** para a fase IA. `ai_decision_logs` referenciado no Módulo 2 será um placeholder de schema mínimo até a fase IA real entregar — esta fase **não cria** essa tabela. |
| "Fase 6 = Retornos" referenciado no escopo do Módulo 2 | Fase 6 real é `006-agenda-ux-polish` (UX da Agenda). Retornos é **fase futura** sem spec. | Métricas de retorno no Módulo 2 ficam **dependentes de feature flag**; se `app.modules.returns.enabled === false`, o card "Retornos completados vs. perdidos" exibe estado "Em breve". Sem bloqueio de go-live por isso. |
| Briefing menciona `specs/006-retornos/data-model.md` | Diretório não existe. | Spec ajustada nesta plan — métricas de retorno consultam tabela `return_cadences` **se existir**; teste de integração marcado como `skipIf(!table_exists)`. |
| Plano de tenant carrega "limite de envio diário de campanha" e "rate limit API por plano" | Tabela `plans` existente (Fase 0) **NÃO tem** essas colunas. | Migration desta fase adiciona 3 colunas em `plans`: `daily_campaign_limit` (int default 200), `api_rate_limit_per_minute` (int default 100), `webhook_max_endpoints` (int default 5). Tenants existentes recebem defaults do tier mais restritivo. |
| "Pseudonimização da Fase 5" mencionada no spec | Fase 5 estabeleceu pseudonimização de payload **Google Calendar** (FR-038), não LLM. Pseudonimização LLM real veio da Fase 7 (`ContainsNoClinicalData` marker). | Esta fase **estende** o padrão da Fase 7 para todos os eventos consumidos pela IA futura. Sem mudar o pattern. |

---

## 2. Technical Context

**Language/Version**: PHP 8.5
**Backend Framework**: Laravel 13 (laravel/framework)
**Frontend Framework**: Vue 3 (Composition API) + Pinia + Vite + Tailwind v4
**Admin Panel**: Filament 5 (super admin global — gestão de tenants, planos, métricas globais, anomalias)
**Auth**: Laravel Sanctum (Bearer tokens — Fase 4) + Spatie Permission (guard `web` pinned). **OAuth 2.0 Client Credentials**: `laravel/passport` instalado **somente** quando primeiro tenant enterprise habilita (gate por `tenant.settings.api.oauth_enabled`).
**Database**: PostgreSQL 16 (com `pg_trgm`, `unaccent` já habilitados desde Fase 2)
**Cache & Queues**: Redis 7
**Queue Workers**: Laravel Horizon — filas dedicadas: `campaigns`, `reports`, `webhooks`, `privacy`. Padrão `withoutOverlapping()` em todos os crons.
**Storage**: S3-compatível (reutiliza disk da Fase 3) — paths: `campaigns/{tenant_id}/{campaign_id}/`, `reports/{tenant_id}/{export_id}/`, `privacy/portability/{patient_id}/{request_id}.json`
**Realtime**: Laravel Reverb v1 + Laravel Echo v2 — **não usado nesta fase** (decisão Q6: polling 30s para campanhas; relatórios não usam realtime).
**Testing**: PHPUnit 12 (feature + unit) + Playwright (E2E)
**Observability**: Prometheus exporter (métricas customizadas por módulo) + Sentry com scrub de PII + Pail (dev)
**Target Platform**: Linux server (Docker via Sail), navegadores evergreen, mobile responsivo
**Project Type**: Web application monolítica (backend Laravel + SPA Vue + admin Filament)

**Performance Goals**:

- p95 do dashboard executivo ≤ **1,5s** para tenants com até 50k pacientes em janela ≤30d (SC-10.1)
- p95 de relatórios operacionais/clínicos ≤ **3s** com filtros padrão
- p95 webhook delivery ≤ **5s** fim a fim sem retries (SC-11.1)
- p95 API pública ≤ **300ms** reads / ≤ **800ms** writes
- Tempo do disparo de campanha de 100 destinatários ≤ **5 min** (do "Disparar agora" ao último envio fila) — SC-9.1
- Latência de anonimização de paciente ≤ **30s** para 100% dos casos

**Constraints**:

- Multi-tenancy estrito — `BelongsToTenant` herdado da Fase 1; única quebra autorizada é Super Admin via `withoutGlobalScopes()` + gate de perfil.
- LGPD: zero PII em prompts da IA (validado por reflection + replay semanal — Q29).
- Conformidade Meta: dispatcher de campanha aplica 4 validações sequenciais em runtime (Princípio VI).
- Tokens API hashados SHA-256 (Sanctum default da Fase 4); plaintext só na emissão.
- HMAC SHA-256 em todo payload de webhook + URL HTTPS obrigatória.
- Rate limit 100/1000/5000 req/min por plano (Q15) + hard cap IP 10k req/min anti-DDoS.
- Idempotência dual layer (Redis NX + DB UNIQUE) em todo job de fila desta fase, replicando padrão Fase 7.
- Cobertura ≥ 70% (constituição) com testes obrigatórios mapeados em §6.

**Scale/Scope**:

- ~**60 endpoints REST** novos: 12 campanhas + 8 relatórios + 9 webhooks + 11 API tokens/OAuth + 14 super admin + 6 privacidade
- ~**24 tabelas novas** distribuídas pelos 5 módulos (detalhe em data-model.md)
- **3 ALTERs** em tabelas existentes: `plans` (3 colunas), `tenants` (5 colunas), `patients` (1 coluna `share_with_integrations_consent`)
- ~**41 eventos de domínio** + ~**30 listeners** (auto-discovered Laravel 13)
- **8 jobs** + **6 cron commands** agendados via `routes/console.php`
- **15 páginas Vue** novas + **8 Resources Filament** (super admin global)
- **2 Form Requests + Policy + Service + Resource** por endpoint REST (pipeline obrigatório da constituição §466)

---

## 3. Constitution Check (Pre-Phase 0)

Verificação contra os 7 princípios da Constituição v1.4.0 + módulo Filament/SaaS gates.

> **Status**: ✅ Pass nos 7 princípios sem amendment. Sem violações que exijam revisão constitucional. Detalhamento abaixo.

| # | Princípio | Status | Como esta fase atende |
|---|---|---|---|
| I | **Privacidade / LGPD** (NON-NEGOTIABLE) | ✅ Pass | (a) Módulo 5 é o entregável formal — consentimento hierárquico Q24 + esquecimento Q26 + portabilidade Q28 + auditoria dual Q29. (b) Painel de privacidade para Admin Clínica (US-13.1.5/13.1.6). (c) Mapeamento reversível para LLM mantido apenas em memória de processo (FR-13.9). (d) Sentry scrub de PII (FR-13.12). (e) Audit log de toda exportação, impersonate, esquecimento e portabilidade. |
| II | **Isolamento Multi-Tenant** (NON-NEGOTIABLE) | ✅ Pass | (a) `BelongsToTenant` em todas as 24 tabelas novas exceto as **5 globais do Super Admin** (`plan_versions`, `impersonate_sessions`, `anomalies_detected`, `super_admin_inbox_tasks`, `super_admin_audit_screens`) que são naturally global por design. (b) API pública resolve tenant **exclusivamente pelo token**, nunca por URL (AC-11.2.3). (c) Super Admin é única quebra de scope — gateado por perfil + audit por tela. (d) Teste `CrossTenantFinalizationTest` cobre cada módulo (campanhas, webhooks, API, privacy). |
| III | **Segurança Clínica e Auditabilidade da IA** (NON-NEGOTIABLE) | ✅ Pass | (a) Módulo 5 entrega `PoliticaPseudonimizacaoAuditada` (US-13.3.3) com varredura estática + replay. (b) Gate de CI estende o `ContainsNoClinicalData` da Fase 7 para qualquer evento novo consumido pela IA. (c) IA continua sem mencionar medicamento em renovação (regra já enforçada na Fase 7). |
| IV | **Spec-Driven e Test-First** | ✅ Pass | (a) Spec Clarified com 73 ACs em Given/When/Then. (b) Testes mapeados 1:1 aos ACs em §6 (~180 tests planejados — ver `tasks.md`). (c) PHPUnit feature obrigatório antes de merge de cada lote. (d) Playwright E2E cobre as 4 jornadas críticas da §Definição de Pronto (campanha, esquecimento, portabilidade, impersonate). |
| V | **Observabilidade e Excelência Operacional** | ✅ Pass | (a) Métricas Prometheus por módulo (detalhe em §5 deste plan). (b) Audit_logs obrigatórios em 9 ações: criar/disparar/cancelar campanha, exportar relatório, configurar webhook, emitir/revogar token API, suspender/reativar/cancelar tenant, alterar plano, iniciar/encerrar impersonate, executar esquecimento/portabilidade, registrar/revogar consentimento. (c) Logs estruturados com `correlation_id` em webhooks (entrega cross-tentativa). |
| VI | **Conformidade Meta nos Disparos** (NON-NEGOTIABLE) | ✅ Pass | (a) Dispatcher de campanha (US-9.3) bloqueia em runtime: opt-in marketing (Q25), template aprovado, `business_hours` (Q7), daily limit do plano (Q2). (b) Comando `/sair` revoga apenas marketing (Q25), `/sair tudo` revoga marketing+transacional. (c) Link de unsubscribe obrigatório em todo template não-transacional (validação na criação do template). |
| VII | **Segurança Operacional** (NON-NEGOTIABLE) | ✅ Pass | (a) Sanctum hashado SHA-256 reaproveitado (Fase 4); OAuth Passport opt-in com `client_secret_hash`. (b) Rate limit por token + IP (Q15). (c) HMAC SHA-256 em webhooks (FR-11.2). (d) Suspend/reactivate/cancel exigem motivo ≥10 chars + audit (AC-12.1.3). (e) URLs assinadas TTL 7d em portabilidade (Q28) + audit do download. (f) Webhook URLs com IP privado/localhost rejeitadas no cadastro. |
| VIII | **Sustentabilidade do Plano SaaS** | ✅ Pass | (a) Módulos gateable via `plan.features_enabled[]`: `campaigns`, `webhooks`, `api_public`, `oauth_enabled`. Plano básico não vê configuração de webhook ou OAuth. (b) Limites quantitativos por plano: `daily_campaign_limit`, `api_rate_limit_per_minute`, `webhook_max_endpoints`. (c) Tenant suspenso → 503 em API pública (AC-11.2.11) + jobs pausados (AC-12.1.3). |

### Gates obrigatórios para esta fase (consolidados)

1. **Gate Conformidade Meta** — `CampaignDispatcherComplianceTest` cobre os 4 bloqueios em runtime (opt-in / template / horário / daily limit). Falha = blocker.
2. **Gate Multi-tenancy cross-module** — `CrossTenantFinalizationTest` valida que campanha/webhook/token/consentimento de tenant A não vaza para tenant B mesmo via API pública. Falha = blocker.
3. **Gate LGPD anonimização** — `RightToBeForgottenMapTest` valida que `DireitoEsquecimentoExecutado` aplica o mapa Q26 corretamente (campos anonimizados, deletados, preservados). Falha = blocker.
4. **Gate pseudonimização IA** — `EventsForAiPseudonymizationTest` estende reflexão para todos os eventos consumidos pela IA (extensão direta do `PrescriptionEventPayloadLgpdTest` da Fase 7). Falha = blocker.
5. **Gate Super Admin scope break** — `SuperAdminScopeBreakTest` valida que `withoutGlobalScopes()` é usado **exclusivamente** em controllers/Resources do Filament super admin, nunca em endpoints da API tenant. Falha = blocker.
6. **Gate retention policy** — `TenantCancellationRetentionTest` valida que após cancelamento, dados são preservados/anonimizados/deletados conforme Q20 (4 categorias). Falha = blocker.
7. **Gate impersonate audit granular** — `ImpersonateScreenAuditTest` valida que toda navegação durante sessão de impersonate emite `ImpersonateTelaVisitada`. Falha = blocker.

---

## 4. Project Structure

### Documentation (this feature)

```text
specs/008-finalizacao-mvp/
├── plan.md              # This file (/speckit-plan output)
├── research.md          # Phase 0 output (29 clarifications consolidadas + tradeoffs)
├── data-model.md        # Phase 1 output (24 tabelas + 3 ALTERs)
├── quickstart.md        # Phase 1 output (smoke E2E por módulo)
├── contracts/           # Phase 1 output (OpenAPI v1 + webhook schemas)
│   ├── api-v1-patients.yaml
│   ├── api-v1-appointments.yaml
│   ├── api-v1-messages.yaml
│   ├── api-v1-prescriptions.yaml
│   ├── api-v1-types-professionals.yaml
│   ├── webhook-events.yaml
│   └── README.md
├── checklists/
│   └── requirements.md  # Pre-existente (16/16 itens pass)
└── tasks.md             # Phase 2 output (/speckit-tasks command)
```

### Source Code (repository root)

Estrutura modular dentro do monorepo Laravel existente:

```text
app/
├── Domain/
│   ├── Campaigns/                       # Módulo 1 — Épico 9
│   │   ├── Models/                      # Campaign, CampaignRecipient, CampaignTemplate
│   │   ├── Services/                    # CampaignBuilder, CampaignDispatcher, CampaignComplianceGate
│   │   ├── Events/                      # CampanhaCriada, MensagemCampanhaEnviada, etc.
│   │   ├── Listeners/                   # auto-discovered
│   │   ├── Jobs/                        # DispatchCampaignJob, ProcessCampaignBatchJob
│   │   └── Policies/                    # CampaignPolicy
│   ├── Reports/                         # Módulo 2 — Épico 10
│   │   ├── Models/                      # MetricAggregation, ReportExport
│   │   ├── Services/                    # ExecutiveDashboardService, OperationalReportService, ClinicalReportService, ReportExportService
│   │   ├── Jobs/                        # AggregateHourlyMetricsJob
│   │   ├── Policies/                    # ReportPolicy
│   │   └── Resources/                   # Pdf/DashboardPdfRenderer (DOMPDF wrapper)
│   ├── Integrations/                    # Módulo 3 — Épico 11
│   │   ├── Models/                      # WebhookEndpoint, WebhookDelivery, WebhookDeadLetter, OauthClient
│   │   ├── Services/                    # WebhookDispatcher, HmacSigner, ApiTokenService, OauthClientService
│   │   ├── Events/                      # WebhookEntregue, WebhookFalhou, etc.
│   │   ├── Jobs/                        # DispatchWebhookJob, RetryWebhookJob, PurgeExpiredDlqJob
│   │   ├── Policies/                    # WebhookPolicy, ApiTokenPolicy
│   │   └── Http/Middleware/             # ApiPublicRateLimiter, OauthAuthenticator
│   ├── SuperAdmin/                      # Módulo 4 — Épico 12
│   │   ├── Models/                      # PlanVersion, TenantPlanBinding, ImpersonateSession, AnomalyDetected
│   │   ├── Services/                    # TenantLifecycleService, PlanVersioningService, ImpersonateService, GlobalMetricsService, AnomalyDetectorService
│   │   ├── Events/                      # TenantSuspenso, ImpersonateIniciado, AnomaliaDetectada, etc.
│   │   ├── Jobs/                        # ComputeGlobalMetricsJob, DetectAnomaliesJob, ApplyRetentionPolicyJob
│   │   └── Http/Middleware/             # EnsureSuperAdmin, ImpersonateContextResolver
│   └── Privacy/                         # Módulo 5 — Épico 13
│       ├── Models/                      # ConsentRecord, ForgettingRequest, PortabilityRequest, PseudonymizationAudit
│       ├── Services/                    # ConsentService, ForgettingExecutor, PortabilityExporter, PseudonymizationAuditor
│       ├── Events/                      # ConsentimentoRegistrado, DireitoEsquecimentoExecutado, etc.
│       ├── Jobs/                        # ExecuteForgettingJob, GeneratePortabilityArchiveJob, AuditPseudonymizationJob
│       ├── Policies/                    # PrivacyPolicy
│       └── Support/                     # AnonymizationMap (mapa Q26 explícito), PiiDetector (regex Q29)
├── Filament/                            # Super Admin panel (Módulo 4 entrega)
│   ├── Pages/Global/                    # GlobalMetricsPage, AnomaliesPage
│   └── Resources/                       # TenantResource, PlanResource, ImpersonateResource, etc.
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/Public/               # API pública v1 — Q14 scope
│   │   ├── Api/V1/Campaigns/            # tenant API — endpoints internos
│   │   ├── Api/V1/Reports/
│   │   ├── Api/V1/Integrations/
│   │   ├── Api/V1/Privacy/
│   │   └── Webhooks/Stripe/             # já existente Fase 0
│   ├── Requests/                        # 1 FormRequest por endpoint
│   ├── Resources/                       # API Resources (1 por entidade exposta)
│   └── Middleware/
│       ├── EnsureTenantSlugHeader.php   # já existente Fase 4
│       └── ApiPublicRateLimiter.php     # novo — diferenciado por plano
├── Console/Commands/                    # 6 novos comandos crons
│   ├── Campaigns/DispatchScheduledCampaignsCommand.php
│   ├── Reports/AggregateHourlyMetricsCommand.php
│   ├── Integrations/PurgeExpiredDlqCommand.php
│   ├── SuperAdmin/DetectAnomaliesCommand.php
│   ├── SuperAdmin/ApplyRetentionPolicyCommand.php
│   └── Privacy/AuditPseudonymizationWeeklyCommand.php
├── Support/
│   └── Lgpd/
│       ├── ContainsNoClinicalData.php   # já existente Fase 7
│       └── PiiScrubber.php              # novo — Sentry scrub
└── Providers/
    └── EventServiceProvider.php         # zero registrações manuais (Laravel 11+ discovery)

database/migrations/
└── 2026_05_22_* through 2026_05_28_*    # ~27 migrations (24 create + 3 alter)

routes/
├── api.php                              # rotas tenant /api/v1/*
├── api-public.php                       # NOVO — rotas API pública /v1/* (Q14 scope restrito)
└── console.php                          # 6 novos schedules

resources/js/
├── pages/
│   ├── Campaigns/                       # 4 pages — Index, Create, Show, Report
│   ├── Reports/                         # 3 pages — Executive, Operational, Clinical
│   ├── Settings/Webhooks/               # 2 pages — Index, Edit
│   ├── Settings/ApiTokens/              # 1 page
│   ├── Privacy/                         # 3 pages — Consents, Forgetting, Portability
│   └── ...
└── stores/                              # Pinia stores correspondentes

tests/
├── Feature/
│   ├── Campaigns/                       # ~35 tests
│   ├── Reports/                         # ~25 tests
│   ├── Integrations/                    # ~40 tests
│   ├── SuperAdmin/                      # ~30 tests
│   └── Privacy/                         # ~45 tests (incl. mapa anonimização, pseudonimização auditor)
├── Unit/                                # services puros (PiiDetector, AnonymizationMap, HmacSigner)
└── E2E/                                 # Playwright — 4 jornadas críticas
```

**Structure Decision**: estrutura segue convenção Laravel + organização por **bounded context** (`app/Domain/{módulo}/`). Cada módulo é autocontido — pode evoluir independentemente; quebras entre módulos passam por Events. Sem novas pastas raiz. Filament tem pasta própria reutilizada da Fase 0/7.

---

## 5. Phased Implementation Strategy

A fase é grande (~250 tasks estimadas). Para preservar reviewability e permitir merge incremental, o trabalho é dividido em **5 lotes A–E** alinhados aos módulos da spec, **na ordem que minimiza dependências e maximiza valor early**.

### Ordenação dos lotes (justificativa)

| Lote | Módulo | Justificativa de ordem |
|---|---|---|
| **A** | Módulo 5 — Privacidade/LGPD | **Primeiro** porque é **gate regulatório de toda a fase**. Sem consentimento hierárquico Q24 implementado, o dispatcher de campanhas (Módulo 1) não pode validar opt-in. Auditoria de pseudonimização Q29 estende padrão da Fase 7 — entrega cedo evita drift. |
| **B** | Módulo 4 — Super Admin | **Segundo** porque adiciona colunas em `plans` e `tenants` que outros módulos consomem (daily_campaign_limit, api_rate_limit, billing_mode, retention_policy). Painel Filament inicial entrega visibilidade de toda a fase desde o dia 1 de B. |
| **C** | Módulo 1 — Campanhas | **Terceiro** porque depende de A (opt-in marketing) e B (daily_campaign_limit do plano). É o módulo de maior valor de produto — entregá-lo cedo permite QA paralelo. |
| **D** | Módulo 3 — Integrações | **Quarto** porque webhooks consomem eventos de A, B, C (ConsentimentoRegistrado, TenantSuspenso, CampanhaDisparada). API pública precisa de tudo isso já operacional para expor de forma consistente. |
| **E** | Módulo 2 — Relatórios | **Último** porque agrega métricas dos quatro módulos anteriores. Dashboard executivo só faz sentido com volume de dados real produzido pelos lotes A–D em ambiente de staging. Auditoria de exportação fecha a observabilidade. |

> **Nota**: ordem dos lotes ≠ ordem das US/Épicos na spec. A spec organiza por épico/módulo (numeração 9→13) por clareza de produto; a implementação organiza por dependência técnica.

### Lote A — Privacidade / LGPD (Épico 13)

**Goals**: entregar consentimento hierárquico operável, fluxo de direito ao esquecimento com mapa explícito, portabilidade JSON, auditoria dual de pseudonimização.

**Outputs**:
- Migrations: `consent_records`, `forgetting_requests`, `portability_requests`, `pseudonymization_audits` + ALTER em `patients` (`share_with_integrations_consent` boolean default false)
- Services: `ConsentService`, `ForgettingExecutor`, `PortabilityExporter`, `PseudonymizationAuditor`
- Support: `App\Support\Lgpd\AnonymizationMap` (mapa Q26 explícito), `App\Support\Lgpd\PiiDetector` (regex CPF/telefone/email)
- 6 endpoints REST (`/api/v1/privacy/consents`, `/forgetting-requests`, `/portability-requests`, etc.)
- 3 páginas Vue (`PrivacyConsentsPage`, `PrivacyForgettingPage`, `PrivacyPortabilityPage`)
- 2 cron schedules: `privacy:audit-pseudonymization-weekly` (semanal), `privacy:notify-deadlines` (daily 09:00 BRT — D-5 inbox, D-2 inbox+e-mail)
- CI gate: `EventsForAiPseudonymizationTest` estende `PrescriptionEventPayloadLgpdTest` para os 13 eventos da fase consumidos pela IA
- ~45 testes feature + ~15 unit (mapa anonimização, regex PII)

**Acceptance**: AC-13.1.* + AC-13.2.* + AC-13.3.* (21 ACs); 5 SCs validados; Gate 3 e 4 verdes.

### Lote B — Super Admin (Épico 12)

**Goals**: painel Filament super admin com listagem/filtros/ações sobre tenants, CRUD de planos com versioning, métricas globais, impersonate auditado, detecção de anomalias.

**Outputs**:
- Migrations: `plan_versions`, `tenant_plan_bindings`, `impersonate_sessions`, `super_admin_audit_screens`, `anomalies_detected` + ALTER em `tenants` (5 cols: `suspended_at`, `suspended_by`, `suspended_reason`, `canceled_at`, `retention_policy`, `billing_mode`) + ALTER em `plans` (3 cols: `daily_campaign_limit`, `api_rate_limit_per_minute`, `webhook_max_endpoints`)
- Services: `TenantLifecycleService`, `PlanVersioningService`, `ImpersonateService`, `GlobalMetricsService`, `AnomalyDetectorService`
- 8 Filament Resources (TenantResource, PlanResource, etc.) + 2 Filament Pages (GlobalMetricsPage, AnomaliesPage)
- Middleware: `EnsureSuperAdmin`, `ImpersonateContextResolver` (resolve tenant durante sessão de impersonate)
- 3 cron schedules: `super-admin:compute-global-metrics` (hourly), `super-admin:detect-anomalies` (every 15min), `super-admin:apply-retention-policy` (daily 02:00 BRT)
- Listener `ApplyTenantSuspensionEffectsListener` (revoga sessões, pausa jobs) em `TenantSuspenso`
- ~30 testes feature + ~10 unit

**Acceptance**: AC-12.1.* + AC-12.2.* + AC-12.3.* (21 ACs); 4 SCs validados; Gates 5, 6 e 7 verdes.

### Lote C — Campanhas (Épico 9)

**Goals**: criação/agendamento/disparo de campanhas tenant-scoped com guardrails de conformidade Meta + LGPD em runtime.

**Outputs**:
- Migrations: `campaigns`, `campaign_recipients`, `campaign_dispatch_log`, `campaign_templates_meta`
- Services: `CampaignBuilder`, `CampaignDispatcher`, `CampaignComplianceGate` (4 validações sequenciais — Princípio VI), `CampaignAudienceCalculator` (Q1 — `ConsultaRealizada` lookup)
- Jobs: `DispatchScheduledCampaignsJob`, `ProcessCampaignBatchJob`, `SendCampaignMessageJob` (1 por destinatário — idempotent via DB UNIQUE `(campaign_id, patient_id)`)
- 12 endpoints REST (`/api/v1/campaigns`, `/recipients`, `/dispatch`, `/cancel`, `/preview`, `/report`)
- 4 páginas Vue (`CampaignsIndexPage`, `CampaignCreatePage`, `CampaignShowPage`, `CampaignReportPage`)
- 1 cron schedule: `campaigns:dispatch-scheduled` (every 5min — detecta campanhas com `scheduled_for` ≤ now())
- Listener `ProcessSairCommandListener` em `MensagemRecebida` da Fase 3 — detecta `/sair` e `/sair tudo` e emite `ConsentimentoRevogado`
- ~35 testes feature incluindo Gate 1 (Compliance Dispatcher) e Gate 2 (Cross-tenant)
- Métricas Prometheus: `campaign_dispatched_total{tenant,status}`, `campaign_blocked_total{reason,tenant}`, `campaign_recipients_total{campaign_id}`

**Acceptance**: AC-9.1.* + AC-9.2.* + AC-9.3.* (20 ACs); 3 SCs validados; Gate 1 e 2 verdes.

### Lote D — Integrações (Épico 11)

**Goals**: webhooks de saída com HMAC + retry exponencial + DLQ; API pública v1 com escopo restrito (Q14) + autenticação dual (Sanctum default + OAuth opt-in) + rate limit por plano.

**Outputs**:
- Migrations: `webhook_endpoints`, `webhook_deliveries`, `webhook_dead_letter`, `oauth_clients` (gateado — só criado se algum tenant habilita)
- Services: `WebhookDispatcher`, `HmacSigner` (SHA-256), `ApiTokenService`, `OauthClientService`
- Listener universal `BroadcastDomainEventToWebhooksListener` que escuta os **13 eventos do catálogo Q17** e enfileira `DispatchWebhookJob` para cada endpoint subscrito.
- Jobs: `DispatchWebhookJob` (com retry policy 5×exponential 30s→6h), `MoveToDeadLetterJob`, `PurgeExpiredDlqJob`
- 9 endpoints REST internos (`/api/v1/integrations/webhooks`, `/api-tokens`, `/oauth-clients`)
- 11 endpoints API pública v1 (`/v1/patients`, `/v1/appointments`, `/v1/messages`, `/v1/prescriptions`, `/v1/appointment-types`, `/v1/professionals` — todos respeitando o escopo Q14)
- 2 páginas Vue (`WebhooksSettingsPage`, `ApiTokensSettingsPage`)
- Middleware: `ApiPublicRateLimiter` (por token + cap por IP), `OauthAuthenticator` (opt-in via Passport)
- 1 cron schedule: `integrations:purge-expired-dlq` (daily 03:00 BRT — remove entradas DLQ >30 dias)
- OpenAPI publicado em `/docs/api/v1.yaml` (servido por Scribe, já em uso desde Fase 4)
- ~40 testes feature

**Acceptance**: AC-11.1.* + AC-11.2.* (17 ACs); 4 SCs validados.

### Lote E — Relatórios (Épico 10)

**Goals**: dashboards executivo/operacional/clínico tenant-scoped; agregações horárias; drill-down; exportação PDF formatado.

**Outputs**:
- Migrations: `metric_aggregations`, `report_exports`
- Services: `ExecutiveDashboardService`, `OperationalReportService`, `ClinicalReportService`, `ReportExportService`, `MetricAggregator`
- Jobs: `AggregateHourlyMetricsJob` (consome eventos de A, B, C, D), `RenderReportPdfJob`
- 8 endpoints REST (`/api/v1/reports/executive`, `/operational`, `/clinical`, `/export`)
- 3 páginas Vue (`ExecutiveDashboardPage`, `OperationalReportPage`, `ClinicalReportPage`)
- 1 cron schedule: `reports:aggregate-hourly` (hourly :05 — janela ≥7d; janelas ≤24h usam queries live)
- PDF renderer reutilizando library já presente (DOMPDF se disponível, caso contrário Browsershot) — escolha em research.md
- ~25 testes feature
- Métricas Prometheus: `reports_exported_total{type,format,tenant}`, `metric_aggregation_lag_seconds`

**Acceptance**: AC-10.1.* + AC-10.2.* + AC-10.3.* (18 ACs); 3 SCs validados.

### Dependências cruzadas entre lotes

```
A (Privacidade)
 └─→ B (Super Admin) — plano carrega limits que C/D consomem
       └─→ C (Campanhas) — depende de A.opt-in marketing + B.plan.daily_campaign_limit
             └─→ D (Integrações) — webhooks consomem eventos de A, B, C; API rate limit usa B.plan.api_rate_limit
                   └─→ E (Relatórios) — agrega tudo
```

Cada lote tem **gate de testes verdes + Constitution Check parcial** antes de merge. Suite full roda ao fim de cada lote para detectar regressão.

---

## 6. Test Strategy

> Mapeamento detalhado de testes está em `tasks.md`. Resumo dos gates por lote:

| Lote | Testes feature | Testes unit | E2E Playwright | Gates constitucionais |
|---|---|---|---|---|
| A | ~45 | ~15 | Esquecimento + Portabilidade | Gates 3, 4 |
| B | ~30 | ~10 | Impersonate full flow | Gates 5, 6, 7 |
| C | ~35 | ~10 | Criar/disparar campanha + `/sair` | Gates 1, 2 |
| D | ~40 | ~5 | Configurar webhook + receber em mock | — |
| E | ~25 | ~5 | Exportar PDF dashboard | — |
| **Total** | **~175** | **~45** | **5 jornadas** | **7 gates** |

**Patterns de teste reaproveitados das fases anteriores**:

- `Sanctum::actingAs($user, ['*'])` para auth (Fase 4 pattern).
- `RefreshDatabase` em feature tests; `assertDatabaseHas`/`assertDatabaseMissing`.
- Factories com states para tenants/users/plans (Fase 0).
- `Bus::fake()` + `Queue::fake()` para jobs sem rodar workers.
- `Http::fake()` para webhooks outbound e templates Meta.
- `Event::fake([...])` para isolar testes que não dependem de listener.

---

## 7. Observability & Métricas Prometheus

Métricas customizadas expostas em `/metrics` (já configurado desde Fase 4 via prom-php). Naming convention: `{module}_{action}_{status}` com label `tenant`.

| Módulo | Métricas |
|---|---|
| **Campanhas (C)** | `campaign_dispatched_total{tenant,status}`, `campaign_blocked_total{reason,tenant}`, `campaign_recipients_total{campaign_id}`, `campaign_dispatch_duration_seconds{tenant}` |
| **Relatórios (E)** | `reports_exported_total{type,format,tenant}`, `metric_aggregation_lag_seconds`, `dashboard_load_duration_seconds{type,tenant}` |
| **Integrações (D)** | `webhook_delivered_total{tenant,event_type,status}`, `webhook_dlq_size{tenant}`, `webhook_delivery_latency_ms_p95`, `api_public_requests_total{tenant,endpoint,status,auth_method}`, `api_public_rate_limited_total{tenant,reason}` |
| **Super Admin (B)** | `tenant_lifecycle_total{action,from_status,to_status}`, `impersonate_sessions_total{result}`, `anomalies_detected_total{category,severity}`, `mrr_total`, `arr_total`, `churn_rate_percent` |
| **Privacidade (A)** | `consent_recorded_total{finalidade,channel}`, `consent_revoked_total{finalidade,channel}`, `forgetting_requests_total{status}`, `portability_requests_total{status}`, `pseudonymization_audit_findings_total{severity}` |

Sentry tags por módulo: `campaign.id`, `report.type`, `webhook.id`, `impersonate.session_id`, `forgetting.request_id`. Scrub de PII universal aplicado em `App\Support\Lgpd\PiiScrubber` (FR-13.12).

---

## 8. Cron Schedule (consolidado — 6 novos comandos)

Adicionados em `routes/console.php`. Todos com `withoutOverlapping()` (padrão Fase 5).

```php
// Lote A — Privacidade
Schedule::command('privacy:audit-pseudonymization-weekly')->weekly()->mondays()->at('04:00');
Schedule::command('privacy:notify-deadlines')->dailyAt('09:00');

// Lote B — Super Admin
Schedule::command('super-admin:compute-global-metrics')->hourly()->withoutOverlapping();
Schedule::command('super-admin:detect-anomalies')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('super-admin:apply-retention-policy')->dailyAt('02:00');

// Lote C — Campanhas
Schedule::command('campaigns:dispatch-scheduled')->everyFiveMinutes()->withoutOverlapping();

// Lote D — Integrações
Schedule::command('integrations:purge-expired-dlq')->dailyAt('03:00');

// Lote E — Relatórios
Schedule::command('reports:aggregate-hourly')->hourlyAt(5);
```

Total: **8 schedules**. Workers Horizon precisam de filas dedicadas: `campaigns` (concurrency 10), `reports` (concurrency 3), `webhooks` (concurrency 20), `privacy` (concurrency 2).

---

## 9. Risk Tracking

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| **R-8-1** Anonimização de paciente quebra audit_logs referenciando o paciente (FK violation) | Alta | Alto | Anonimização **não deleta** registros; apenas substitui campos. FK do paciente continua válida. Banner "Dados preservados" na UI. Teste `ForgettingPreservesReferentialIntegrityTest`. |
| **R-8-2** Webhook URL maliciosa apontando para IP interno (SSRF) | Média | Alto | Validação em cadastro: rejeita URLs com IP privado (10.x, 172.16.x, 192.168.x, 127.x, ::1, fc00::/7) usando `app/Support/UrlGuard.php`. Teste `WebhookUrlSsrfGuardTest`. |
| **R-8-3** Super Admin impersonate vaza dados clínicos para suporte sem auditoria | Média | Alto | Audit granular por tela visitada (Q19/Gate 7). Banner amarelo persistente. Sessão tem TTL máximo de 2h (auto-logout). Métrica `impersonate_sessions_total{result}` reportada ao Sentry quando duração > 30min. |
| **R-8-4** API pública expõe receitas controladas via endpoint `/v1/prescriptions` | Baixa | Crítico | Mascaramento **server-side obrigatório** no `PublicPrescriptionResource` — independentemente do scope do token. Teste `PublicApiControlledMaskingTest`. |
| **R-8-5** Job de agregação horária atrasa > 90min → dashboard mostra dados desatualizados | Média | Médio | (a) Banner "Dados podem estar com até X minutos de atraso" exibido quando `metric_aggregation_lag_seconds > 5400`. (b) Métrica alerta Sentry quando lag > 90min. (c) Janelas ≤24h usam queries live (não dependem do job). |
| **R-8-6** Pseudonimização auditor regex tem falso positivo (rejeita evento legítimo) | Média | Médio | Whitelist de campos confirmados em CI gate. Regex roda em **amostra de 1%** semanal (Q29) — hit gera ticket, não bloqueia evento. Operacionalmente: tickets revisados antes do replay decidir merge gate definitivo. |
| **R-8-7** Anomaly detector dispara falsos positivos no e-mail crítico do Super Admin | Média | Médio | Threshold configurável por categoria + tempo mínimo entre alertas da mesma categoria (30min). Inbox interna sempre recebe; e-mail apenas em severity=critical com cooldown. |
| **R-8-8** Cancelamento de tenant em `billing_mode=offline_invoice` deixa dados órfãos no Stripe | Baixa | Médio | Modo offline **não cria** customer no Stripe — sem orphan. Teste `OfflineBillingModeTest`. Migração de offline para Stripe permitida; reverso (Stripe → offline) **rejeitado** (proteção contra perda de billing history). |
| **R-8-9** OAuth Client Credentials adiciona dependência (`laravel/passport`) só para enterprise | Baixa | Baixo | Pacote instalado mas registrado **apenas** se `config('integrations.oauth_enabled')` for true. Sem tenant enterprise → 0 overhead. Migration de Passport rodada lazy quando primeiro tenant habilita. Documentado em research.md. |
| **R-8-10** Conflict entre `BroadcastDomainEventToWebhooksListener` e listeners existentes de domínio | Baixa | Médio | Listener desta fase é **adicional** — escuta os mesmos eventos mas não substitui nenhum. Laravel discovery resolve naturalmente. Teste `DomainEventsHaveAllListenersTest` valida que cada evento do catálogo Q17 tem pelo menos 1 listener de webhook ativo. |

---

## 10. Out of Scope desta plan (mas mencionado na spec)

Alinhamento explícito com a §8 do spec — estes pontos **não** geram tasks nesta fase:

- Coleta automática de NPS (Q8 → placeholder)
- Re-envio multi-step de campanha (Q5 → disparo único)
- Aprovação intermediária de campanha (Q4 → sem etapa)
- Modo "lado a lado" de comparativo (Q11 → variação %)
- Multi-canal por campanha (Q3 → canal único)
- Sandbox de API pública (AC-11.2.9 nice-to-have)
- Cancelamento automático após N dias de inadimplência (AC-12.1.8 feature futura)
- Compartilhamento com convênio (Q24 deixou fora)
- Notificações push para Admin Clínica (Q27 — inbox + e-mail apenas)
- App mobile nativo, telemedicina, prontuário eletrônico, multi-unidade, SSO Google, TISS/HL7/FHIR

---

## 11. Complexity Tracking

> **Constitution Check passou sem violações.** Nenhuma justificativa em "Complexity Tracking" é necessária.

Pontos de complexidade alta mas **justificados pela própria spec** (não desvio):

- **5 módulos em uma feature**: o spec deliberadamente agrupa porque o produto só é vendável quando todos os 5 estão prontos simultaneamente (§0 do spec). Quebrar em 5 features separadas atrasaria go-live e geraria múltiplos merges em main com produto parcial.
- **3 ALTERs em tabelas existentes**: `plans`, `tenants`, `patients`. Cada um justificado por uma decisão de clarification (Q2, Q20, AC-11.1.7).
- **24 tabelas novas**: distribuídas 5+5+4+5+5 entre módulos. Cada tabela mapeia a uma entidade conceitual da spec (§3 Key Entities).
- **8 schedules adicionais**: necessários para idempotência, agregação, anomalias, retention, DLQ purge. Todos com `withoutOverlapping()`.

---

## 12. Próximos passos

1. **`/speckit-tasks`** — gera `tasks.md` ordenado por dependência. Estimativa: ~250 tasks divididas em 5 lotes A–E.
2. **`/speckit-analyze`** (opcional) — cross-check spec ↔ plan ↔ tasks antes de iniciar.
3. **`/speckit-implement`** — começa pelo Lote A (Privacidade). Cada lote merge no `008-finalizacao-mvp` antes de iniciar o próximo. Suite full deve estar verde ao fim de cada lote.

---

## 13. Phase Outputs

- ✅ `spec.md` (já existente — Clarified)
- ✅ `plan.md` (este arquivo)
- ⏭️ `research.md` (próximo — Phase 0)
- ⏭️ `data-model.md` (próximo — Phase 1)
- ⏭️ `contracts/` (próximo — Phase 1)
- ⏭️ `quickstart.md` (próximo — Phase 1)
- ⏭️ `tasks.md` (gerado por `/speckit-tasks`)
