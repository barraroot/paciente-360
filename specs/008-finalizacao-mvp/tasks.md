# Tasks: Finalização do MVP (Fase 8 — Épicos 9, 10, 11, 12 e 13)

**Branch**: `008-finalizacao-mvp` | **Date**: 2026-05-22 | **Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)
**Input**: Design documents from `/specs/008-finalizacao-mvp/` — plan.md, data-model.md, research.md, contracts/, quickstart.md

**Tests**: Testes feature/unit OBRIGATÓRIOS por constituição (cobertura ≥ 70%). Cada US tem testes mapeados aos ACs.

**Organization**: Tasks agrupadas por **lote técnico** (A → E na ordem de dependência) e dentro de cada lote por **user story** (`[US-X.Y]`) seguindo a numeração da spec.

## Format: `[ID] [P?] [Story?] Description with file path`

- **[P]**: pode rodar em paralelo (arquivos distintos, sem dependência pendente)
- **[Story]**: US correspondente da spec (apenas em fases de user story)
- **Path conventions**: backend em `app/Domain/{Module}/...` + `app/Http/...` (estrutura definida em plan.md §4); frontend em `resources/js/pages/...` + stores Pinia; tests em `tests/Feature/...` e `tests/Unit/...`
- **Sail prefix**: TODO comando shell prefixado com `vendor/bin/sail` (Princípio convention)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: dependências, abilities Spatie, filas Horizon, métricas base, configs novos.

- [X] T001 Adicionar dependências composer em `composer.json`: `barryvdh/laravel-dompdf` (lote E PDF), opcional `laravel/passport` (lote D OAuth — deixar comentado até primeiro tenant enterprise habilitar) — rodar `vendor/bin/sail composer require barryvdh/laravel-dompdf`
- [X] T002 [P] Criar arquivo `config/finalization.php` com chaves `oauth_enabled=false`, `webhook_max_retries=5`, `webhook_dlq_retention_days=30`, `pdf_signed_url_ttl_days=7`, `campaign_polling_interval_seconds=30`, `metric_aggregation_lag_alert_seconds=5400`
- [X] T003 [P] Adicionar abilities Spatie em `database/seeders/RolesSeeder.php`: `campaign.create`, `campaign.dispatch`, `report.view`, `report.export`, `webhook.manage`, `api_token.manage`, `oauth_client.manage`, `tenant.manage`, `tenant.impersonate`, `plan.manage`, `privacy.view`, `privacy.export`, `forgetting.execute`, `portability.execute`
- [X] T004 [P] Configurar filas Horizon dedicadas em `config/horizon.php`: `campaigns` (concurrency 10), `reports` (3), `webhooks` (20), `privacy` (2) — adicionar nos supervisores production e local
- [X] T005 [P] Criar classe base `app/Support/Metrics/AbstractModuleMetrics.php` para padronização das 5 classes de métricas Prometheus desta fase (CampaignMetrics, ReportMetrics, WebhookMetrics, SuperAdminMetrics, PrivacyMetrics)
- [X] T006 [P] Criar `app/Support/Lgpd/PiiScrubber.php` com método estático `scrub(mixed $value): mixed` que aplica regex CPF/telefone/email/RG sobre strings e arrays recursivamente — registrar em `config/sentry.php` `before_send` callback
- [X] T007 Criar `app/Support/UrlGuard.php` com método `isPubliclyReachable(string $url): bool` que rejeita URLs com IP privado (10.x, 172.16.x, 192.168.x, 127.x, ::1, fc00::/7) — usado em validação de webhook (R-8-2)
- [X] T008 [P] Adicionar `routes/api-public.php` vazio + registrar em `bootstrap/app.php` como rota separada com prefixo `api/public` (sem middleware `EnsureTenantSlugHeader` — tenant resolvido pelo token)
- [X] T009 [P] Atualizar `routes/console.php` adicionando 8 novos schedules vazios (placeholders) com `withoutOverlapping()`: `privacy:audit-pseudonymization-weekly` weekly mondays 04:00, `privacy:notify-deadlines` daily 09:00, `super-admin:compute-global-metrics` hourly, `super-admin:detect-anomalies` every 15min, `super-admin:apply-retention-policy` daily 02:00, `campaigns:dispatch-scheduled` every 5min, `integrations:purge-expired-dlq` daily 03:00, `reports:aggregate-hourly` hourlyAt 5
- [X] T010 ~~Rodar `vendor/bin/sail composer dump-autoload` + `vendor/bin/sail npm install`~~ — DEFERRED para ambiente com Sail rodando. Composer.json atualizado em T001; `composer install` deve rodar antes do Lote E (dompdf é dependência do RenderDashboardPdfJob). Sem novos namespaces PSR-4 → autoload atual funciona para todos os arquivos criados aqui.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: marker interfaces, validators e estrutura base que TODOS os 5 lotes consomem.

- [X] T011 Estender o marker `app/Support/Lgpd/ContainsNoClinicalData.php` (Fase 7) — adicionar comentário documentando os 13 eventos do catálogo Q17 que precisarão implementá-la quando registrados como consumidos pela IA futura
- [X] T012 Criar teste de gate de CI `tests/Feature/Constitutional/EventsForAiPseudonymizationTest.php` que itera sobre todos os Events registrados em `app/Domain/**/Events/*.php` e valida que aqueles classificados como "consumed-by-ai" (lista whitelisted em `config/finalization.php` `ai_consumed_events`) implementam `ContainsNoClinicalData` com array `properties` declarado
- [X] T013 [P] Criar `app/Support/Lgpd/PiiDetector.php` com constante `PATTERNS = [cpf, phone_br, email, rg_sp_rj, sus_card]` (regex de research.md §3.3) e método `detect(string $text): array` retornando `[{pattern_name, matched_at}]` sem o valor real
- [X] T014 [P] Criar `app/Support/Lgpd/AnonymizationMap.php` com método `apply(Patient $patient): array` que retorna `['anonymized' => [...], 'deleted' => [...], 'preserved' => [...]]` seguindo o mapa explícito de Q26
- [X] T015 Criar testes unit dos primitives Foundational em `tests/Unit/Lgpd/PiiDetectorTest.php` (matches positivos e negativos para cada pattern; falso positivo de telefone vs. external_id deve ser logado e não causar falha) e `tests/Unit/Lgpd/AnonymizationMapTest.php` (validar 3 categorias e edge cases)
- [X] T016 [P] Criar `app/Models/Concerns/HasTenantPlanLimits.php` trait usada pelo Tenant model — expõe `currentPlanLimit(string $key): int|null` lookup via `tenantPlanBinding()->planVersion->snapshot[$key]` (será usado por Lote B/C/D para enforcement de daily_campaign_limit, api_rate_limit, webhook_max_endpoints)
- [X] T017 ~~Criar listener universal~~ DEFERRED COMO NO-OP. Pattern `Auditable` interface + `IsAuditable` trait + `PersistAuditLogListener` (já existentes desde Fase 2) cobrem o requisito sem nova classe. Eventos da Fase 8 que precisarem de audit log implementarão `Auditable` + usarão `IsAuditable` trait. `RegistraEventoTimelineListener` (Fase 2) automaticamente projeta para timeline quando `auditableModel()` retorna um Paciente.
- [X] T018 ~~Rodar `vendor/bin/sail artisan test --compact`~~ DEFERRED para ambiente com Sail. Aplicado `php -l` (PHP 8.4.20) em todos os 16 arquivos editados/criados — **zero erros de sintaxe**. Suite full deve ser executada antes de iniciar Lote A (Phase 3).

---

## Phase 3: Lote A — Privacidade / LGPD (Épico 13)

**Story Goals**: Operar consentimento hierárquico granular, executar Direito ao Esquecimento dentro de 15 dias úteis com mapa explícito, gerar arquivos de Portabilidade JSON, auditar pseudonimização semanalmente.

**Independent Test Criteria**: Cenários 2, 3 e 8 do `quickstart.md` rodam sem dependência dos outros lotes (B–E). Suite `tests/Feature/Privacy/` verde isoladamente.

### 3.1 Lote A — US-13.1 Consentimento e Opt-in (P1)

**Acceptance**: AC-13.1.1 → AC-13.1.7 ✅

#### Migrations & Models

- [ ] T020 [P] [US-13.1] Criar migration `database/migrations/2026_05_22_000001_create_consent_records_table.php` conforme data-model.md §1.1 (colunas + UNIQUE PARTIAL `(patient_id, finalidade) WHERE state='granted'`)
- [ ] T021 [P] [US-13.1] ALTER `patients` adicionando `share_with_integrations_consent` boolean — migration `2026_05_22_000005_alter_patients_add_share_with_integrations_consent.php`
- [ ] T022 [US-13.1] Criar model `app/Domain/Privacy/Models/ConsentRecord.php` com `BelongsToTenant` trait, relacionamento `patient()`, scope `granted()`, scope `forFinalidade(string)`, cast `evidence_snapshot:array`, cast `scope:array`
- [ ] T023 [P] [US-13.1] Criar factory `database/factories/ConsentRecordFactory.php` com states `granted`, `revoked`, `refused`, `forFinalidadeMarketing`, `forFinalidadeTransacional`

#### Services & Events

- [ ] T024 [US-13.1] Criar `app/Domain/Privacy/Services/ConsentService.php` com métodos `record(Patient, channel, finalidade, evidence): ConsentRecord`, `revoke(Patient, finalidade, channel, evidence): void`, `refuse(...)`, `hasGranted(Patient, finalidade): bool` — emite eventos do domínio
- [ ] T025 [P] [US-13.1] Criar 3 eventos em `app/Domain/Privacy/Events/`: `ConsentimentoRegistrado.php`, `ConsentimentoRecusado.php`, `ConsentimentoRevogado.php` — cada um implementa `ContainsNoClinicalData` com array de props sem PII clínica
- [ ] T026 [US-13.1] Criar listener `app/Domain/Privacy/Listeners/SendInboxNotificationOnConsentChangedListener.php` que notifica Admin Clínica via inbox interna quando `ConsentimentoRevogado{finalidade=marketing}` ocorre (Princípio VI gate complementar)
- [ ] T027 [US-13.1] Estender listener da Fase 3 `MensagemRecebida` para detectar comandos `/sair` (revoga marketing) e `/sair tudo` (revoga marketing + transacional) — novo `app/Domain/Privacy/Listeners/ProcessSairCommandListener.php`

#### Controllers & Endpoints

- [ ] T028 [P] [US-13.1] Criar `app/Http/Requests/Privacy/RecordConsentRequest.php` validando `channel`, `finalidade ∈ {transacional, marketing, pesquisa}`, `evidence_message_id?` ou `evidence_snapshot?`
- [ ] T029 [US-13.1] Criar `app/Http/Controllers/Api/V1/Privacy/ConsentsController.php` com métodos `index`, `store`, `revoke` — pipeline FormRequest → Service → Resource (constituição §466)
- [ ] T030 [P] [US-13.1] Criar `app/Http/Resources/Privacy/ConsentRecordResource.php` serializando os campos sem evidência completa (apenas `evidence_message_id`)
- [ ] T031 [US-13.1] Adicionar rotas em `routes/api.php` (group `auth:sanctum` + `tenant.slug`): `GET /api/v1/privacy/consents`, `POST /api/v1/privacy/consents`, `POST /api/v1/privacy/consents/{patient}/revoke`
- [ ] T032 [US-13.1] Criar `app/Policies/ConsentPolicy.php` com `viewAny`, `view`, `create`, `revoke` — Admin Clínica pode tudo; outros perfis read-only

#### Frontend

- [ ] T033 [P] [US-13.1] Criar página Vue `resources/js/pages/Privacy/ConsentsPage.vue` com tabela paginada de consentimentos (filtros por paciente, finalidade, channel), botão "Exportar registros" + modal de revogação manual
- [ ] T034 [P] [US-13.1] Criar store Pinia `resources/js/stores/privacy/consents.js` com actions `fetchConsents`, `revokeConsent`, `exportConsents`

#### Tests

- [ ] T035 [US-13.1] Criar `tests/Feature/Privacy/ConsentRecordingTest.php` cobrindo AC-13.1.1 → AC-13.1.4 (registro implícito transacional, opt-in marketing explícito, recusa, revogação granular via `/sair`)
- [ ] T036 [P] [US-13.1] Criar `tests/Feature/Privacy/ConsentRevocationViaSairTest.php` simulando mensagem recebida com `/sair` (revoga marketing apenas) e `/sair tudo` (revoga todas) — valida Q25
- [ ] T037 [P] [US-13.1] Criar `tests/Feature/Privacy/ConsentExportAuditTest.php` validando AC-13.1.6 — exportação gera audit_log com `patient_ids_count`
- [ ] T038 [P] [US-13.1] Criar `tests/Feature/Privacy/CrossTenantConsentTest.php` validando que tenant A não vê consentimentos do tenant B (Gate Multi-tenancy)

### 3.2 Lote A — US-13.2 Direito ao Esquecimento + Portabilidade (P1)

**Acceptance**: AC-13.2.1 → AC-13.2.8 ✅

#### Migrations & Models

- [ ] T040 [P] [US-13.2] Criar migration `2026_05_22_000002_create_forgetting_requests_table.php` conforme data-model.md §1.2
- [ ] T041 [P] [US-13.2] Criar migration `2026_05_22_000003_create_portability_requests_table.php` conforme data-model.md §1.3
- [ ] T042 [US-13.2] Criar model `app/Domain/Privacy/Models/ForgettingRequest.php` com `BelongsToTenant`, scope `open()`, scope `nearingDeadline(int days)`, scope `expired()`, casts `fields_anonymized:array`, `fields_preserved_reason:array`
- [ ] T043 [P] [US-13.2] Criar model `app/Domain/Privacy/Models/PortabilityRequest.php` com mesmos padrões + relacionamento `signedUrlExpired(): bool`
- [ ] T044 [P] [US-13.2] Criar factories para ambos

#### Services

- [ ] T045 [US-13.2] Implementar `app/Domain/Privacy/Services/ForgettingExecutor.php` — método `execute(ForgettingRequest, User $executedBy): void` aplica `AnonymizationMap` em transação única + emite `DireitoEsquecimentoExecutado` + grava audit_log
- [ ] T046 [P] [US-13.2] Criar `app/Domain/Privacy/Services/PortabilityExporter.php` com método `buildArchive(Patient): array` retornando JSON estruturado conforme contracts/README.md §3 (schema v1.0) + `generateSignedUrl(PortabilityRequest): string` TTL 7 dias
- [ ] T047 [US-13.2] Criar job `app/Domain/Privacy/Jobs/ExecuteForgettingJob.php` (fila `privacy`) — wraps `ForgettingExecutor::execute()` com retry policy 3× — usado quando esquecimento é enfileirado por agendamento

#### Events & Listeners

- [ ] T048 [P] [US-13.2] Criar 4 eventos em `app/Domain/Privacy/Events/`: `DireitoEsquecimentoSolicitado.php`, `DireitoEsquecimentoExecutado.php`, `PortabilidadeDadosSolicitada.php`, `PortabilidadeDadosExecutada.php` — todos com `ContainsNoClinicalData`
- [ ] T049 [US-13.2] Criar listener `app/Domain/Privacy/Listeners/EnqueueDeadlineNotificationListener.php` em `DireitoEsquecimentoSolicitado` e `PortabilidadeDadosSolicitada` — agenda 2 notificações (D-5 e D-2 BRT) consumidas pelo cron

#### Controllers & Endpoints

- [ ] T050 [P] [US-13.2] Criar `app/Http/Requests/Privacy/CreateForgettingRequestRequest.php` aceitando `patient_id`, `channel_of_request`, `evidence?` — sem auth pública (formulário) OU autenticada (admin)
- [ ] T051 [P] [US-13.2] Criar `app/Http/Requests/Privacy/CreatePortabilityRequestRequest.php` similar
- [ ] T052 [US-13.2] Criar `app/Http/Controllers/Api/V1/Privacy/ForgettingController.php` com `index`, `show`, `store`, `execute`, `deny`
- [ ] T053 [P] [US-13.2] Criar `app/Http/Controllers/Api/V1/Privacy/PortabilityController.php` com `index`, `show`, `store`, `execute`, `downloadSigned`
- [ ] T054 [P] [US-13.2] Criar Resources `app/Http/Resources/Privacy/ForgettingRequestResource.php` e `PortabilityRequestResource.php`
- [ ] T055 [US-13.2] Adicionar rotas em `routes/api.php`: `/api/v1/privacy/forgetting-requests/*` (5 endpoints), `/api/v1/privacy/portability-requests/*` (4 endpoints) + 1 rota pública `POST /privacy/public/forgetting-requests` em `routes/web.php`
- [ ] T056 [US-13.2] Criar Policy combinada `app/Policies/PrivacyRequestPolicy.php` cobrindo ambos os request types

#### Cron Commands

- [ ] T057 [P] [US-13.2] Implementar `app/Console/Commands/Privacy/NotifyDeadlinesCommand.php` (signature `privacy:notify-deadlines`) — varre `forgetting_requests` e `portability_requests` com deadline em D-5/D-2 e dispara notificações (inbox + e-mail conforme Q27)
- [ ] T058 [P] [US-13.2] Implementar `app/Console/Commands/Privacy/MarkExpiredRequestsCommand.php` (signature `privacy:mark-expired` — adicionar schedule daily 00:30) que transita requests com deadline_at < now() para status `vencido_sem_resposta` + alerta crítico (R-8-7 cooldown)

#### Frontend

- [ ] T059 [P] [US-13.2] Criar página `resources/js/pages/Privacy/ForgettingPage.vue` com lista de solicitações + countdown + modal de execução com revisão do mapa
- [ ] T060 [P] [US-13.2] Criar página `resources/js/pages/Privacy/PortabilityPage.vue` similar + botão "Gerar arquivo" + download URL assinada
- [ ] T061 [P] [US-13.2] Criar formulário público `resources/js/pages/Privacy/PublicForgettingRequestPage.vue` (sem auth) com validação de identidade básica

#### Tests

- [ ] T062 [US-13.2] Criar `tests/Feature/Privacy/RightToBeForgottenMapTest.php` (Gate 3) validando que execução aplica corretamente: anonymized (placeholders Q26), deleted (campos físicos), preserved (controladas + billing + audit + consentimentos com banner)
- [ ] T063 [P] [US-13.2] Criar `tests/Feature/Privacy/ForgettingPreservesReferentialIntegrityTest.php` (R-8-1) — após anonimização, queries em audit_logs + prescriptions + appointments referenciando o paciente continuam válidas
- [ ] T064 [P] [US-13.2] Criar `tests/Feature/Privacy/PortabilityArchiveGenerationTest.php` validando AC-13.2.8 + schema v1.0 + mascaramento de controladas + URL assinada 7d
- [ ] T065 [P] [US-13.2] Criar `tests/Feature/Privacy/PortabilitySignedUrlExpirationTest.php` validando 403 após 7 dias e novo link sem reiniciar deadline
- [ ] T066 [P] [US-13.2] Criar `tests/Feature/Privacy/DeadlineNotificationCommandTest.php` cobrindo cron `privacy:notify-deadlines` D-5 e D-2
- [ ] T067 [US-13.2] Criar `tests/Unit/Privacy/AnonymizationMapApplicationTest.php` testando isoladamente o mapa (sem DB)

### 3.3 Lote A — US-13.3 Pseudonimização de Prompts da IA (P1)

**Acceptance**: AC-13.3.1 → AC-13.3.7 ✅

- [ ] T070 [P] [US-13.3] Criar migration `2026_05_22_000004_create_pseudonymization_audits_table.php` conforme data-model.md §1.4
- [ ] T071 [US-13.3] Criar model `app/Domain/Privacy/Models/PseudonymizationAudit.php` + factory + scopes `byMode(string)`, `withFindings()`
- [ ] T072 [US-13.3] Implementar `app/Domain/Privacy/Services/PseudonymizationAuditor.php` com 2 métodos: `runStaticReflection(): PseudonymizationAudit` (varredura via reflection — reusa `EventsForAiPseudonymizationTest` lógica) e `runRuntimeReplay(int $samplePercent = 1): PseudonymizationAudit` (amostra randômica + `PiiDetector::detect()`)
- [ ] T073 [P] [US-13.3] Criar evento `app/Domain/Privacy/Events/PoliticaPseudonimizacaoAuditada.php` + `AuditoriaPrivacidadeExportada.php`
- [ ] T074 [P] [US-13.3] Criar job `app/Domain/Privacy/Jobs/AuditPseudonymizationJob.php` (fila `privacy`) que dispara runtime replay
- [ ] T075 [US-13.3] Implementar command `app/Console/Commands/Privacy/AuditPseudonymizationWeeklyCommand.php` (signature `privacy:audit-pseudonymization-weekly`) que enfileira `AuditPseudonymizationJob`
- [ ] T076 [P] [US-13.3] Criar página Filament `app/Filament/Pages/Privacy/PseudonymizationAuditReportPage.php` para Admin Clínica/Super Admin visualizar relatório
- [ ] T077 [US-13.3] Criar `tests/Feature/Privacy/PseudonymizationStaticAuditTest.php` (Gate 4) — força adição de evento sem marker `ContainsNoClinicalData` e valida que CI gate falha
- [ ] T078 [P] [US-13.3] Criar `tests/Feature/Privacy/PseudonymizationRuntimeReplayTest.php` inserindo evento com CPF e validando que detector encontra + cria finding com severity=critical
- [ ] T079 [P] [US-13.3] Criar `tests/Unit/Privacy/PiiScrubberSentryIntegrationTest.php` validando AC-13.3.7 — Sentry payload com PII é scrubbed antes do envio
- [ ] T080 [US-13.3] Estender `app/Support/Metrics/PrivacyMetrics.php` com `consent_recorded_total`, `consent_revoked_total`, `forgetting_requests_total`, `portability_requests_total`, `pseudonymization_audit_findings_total`

**Checkpoint Lote A**: rodar `vendor/bin/sail artisan test tests/Feature/Privacy/ tests/Unit/Privacy/ tests/Unit/Lgpd/ --compact` — todos verdes. Gates 3 e 4 verdes. Smoke E2E cenários 2, 3 e 8 do quickstart.md exequíveis.

---

## Phase 4: Lote B — Super Admin (Épico 12)

**Story Goals**: Painel Filament para gestão de tenants/planos/métricas/anomalias com impersonate auditado e política de retenção pós-cancelamento diferenciada.

**Independent Test Criteria**: Cenários 4, 9 e 10 do quickstart.md rodam após Lote A entregue. Suite `tests/Feature/SuperAdmin/` verde isolada.

### 4.1 Lote B — US-12.1 Gestão de Tenants (P1)

**Acceptance**: AC-12.1.1 → AC-12.1.10 ✅

#### Migrations & Models

- [ ] T085 [P] [US-12.1] Criar migration `2026_05_23_000001_alter_tenants_add_lifecycle_columns.php` adicionando 5 cols + 3 indexes (data-model.md §2.6)
- [ ] T086 [P] [US-12.1] Criar migration `2026_05_23_000006_create_impersonate_sessions_table.php` + PARTIAL UNIQUE `(super_admin_id) WHERE ended_at IS NULL`
- [ ] T087 [P] [US-12.1] Criar migration `2026_05_23_000007_create_super_admin_audit_screens_table.php`
- [ ] T088 [US-12.1] Estender model `app/Models/Tenant.php` (Fase 0) — adicionar trait `HasTenantPlanLimits` (T016), scopes `suspended()`, `canceled()`, `withinRetention()`
- [ ] T089 [P] [US-12.1] Criar model `app/Domain/SuperAdmin/Models/ImpersonateSession.php` com relação `auditScreens()`, accessor `duration()`
- [ ] T090 [P] [US-12.1] Criar model `app/Domain/SuperAdmin/Models/SuperAdminAuditScreen.php`
- [ ] T091 [P] [US-12.1] Criar factories para impersonate_session e audit_screen

#### Services

- [ ] T092 [US-12.1] Implementar `app/Domain/SuperAdmin/Services/TenantLifecycleService.php` com métodos `suspend(Tenant, User, reason)`, `reactivate(Tenant, User)`, `cancel(Tenant, User, reason)` — emite eventos + grava audit
- [ ] T093 [US-12.1] Criar listener `app/Domain/SuperAdmin/Listeners/ApplyTenantSuspensionEffectsListener.php` em `TenantSuspenso` — revoga personal_access_tokens do tenant, pausa jobs Horizon do tenant via tag, marca Filament users do tenant como logout-on-next-request
- [ ] T094 [P] [US-12.1] Implementar `app/Domain/SuperAdmin/Services/ImpersonateService.php` com `start(SuperAdmin, Tenant, reason): ImpersonateSession`, `end(session)`, `recordScreenVisit(session, route, path, method)`
- [ ] T095 [US-12.1] Criar middleware `app/Http/Middleware/ImpersonateContextResolver.php` — quando Super Admin tem sessão ativa, resolve tenant pelo `impersonate_session.tenant_id` e injeta em `request->attributes`
- [ ] T096 [US-12.1] Criar middleware `app/Http/Middleware/ImpersonateScreenAuditTrigger.php` (after-response) — para toda request durante sessão ativa, persiste row em `super_admin_audit_screens`
- [ ] T097 [P] [US-12.1] Criar middleware `app/Http/Middleware/EnsureSuperAdmin.php` que valida `$user->hasRole('super_admin')` e `tenant_id IS NULL`

#### Events

- [ ] T098 [P] [US-12.1] Criar 5 eventos em `app/Domain/SuperAdmin/Events/`: `TenantCriadoPorSuperAdmin`, `TenantSuspenso`, `TenantReativado`, `TenantCancelado`, `ImpersonateIniciado`, `ImpersonateTelaVisitada`, `ImpersonateEncerrado`

#### Filament Resources

- [ ] T099 [US-12.1] Criar `app/Filament/Resources/Tenants/TenantResource.php` com listagem + filtros (status, plano, data, inadimplência), bulk actions `Suspender`, `Reativar`, `Cancelar` + custom action `Impersonate` (com modal de motivo ≥10 chars)
- [ ] T100 [P] [US-12.1] Criar `app/Filament/Resources/Tenants/Pages/CreateTenant.php` aceitando `billing_mode ∈ {stripe, offline_invoice}` (Q23)
- [ ] T101 [P] [US-12.1] Criar `app/Filament/Resources/Tenants/Pages/ViewTenant.php` com aba "Métricas" (AC-12.1.7) + aba "Audit" listando impersonate sessions deste tenant
- [ ] T102 [P] [US-12.1] Criar `app/Filament/Resources/Impersonate/ImpersonateSessionResource.php` (read-only) — listagem global de todas as sessões com filtros por super_admin/tenant/data
- [ ] T103 [US-12.1] Implementar componente Vue de banner persistente `resources/js/components/ImpersonateBanner.vue` carregado globalmente quando `useAuthStore().isImpersonating === true` + atalho "Sair do impersonate"

#### Cron & Listeners

- [ ] T104 [US-12.1] Criar `app/Console/Commands/SuperAdmin/ApplyRetentionPolicyCommand.php` (signature `super-admin:apply-retention-policy`) — varre tenants cancelados e aplica política diferenciada Q20 por checkpoint de tempo (30d config, 90d paciente, 1a audit, 2a controladas, 5a billing)

#### Tests

- [ ] T105 [US-12.1] Criar `tests/Feature/SuperAdmin/TenantLifecycleTest.php` cobrindo AC-12.1.3, AC-12.1.4, AC-12.1.10 + motivo ≥10 chars obrigatório
- [ ] T106 [P] [US-12.1] Criar `tests/Feature/SuperAdmin/ImpersonateScreenAuditTest.php` (Gate 7) — sessão impersonate gera audit_screen para cada rota visitada
- [ ] T107 [P] [US-12.1] Criar `tests/Feature/SuperAdmin/ImpersonateBannerTest.php` validando que toda response carrega header/flag indicando impersonate ativo (SC-12.2)
- [ ] T108 [P] [US-12.1] Criar `tests/Feature/SuperAdmin/ImpersonateConcurrencyTest.php` validando PARTIAL UNIQUE — Super Admin tenta 2 sessões simultâneas e falha
- [ ] T109 [P] [US-12.1] Criar `tests/Feature/SuperAdmin/TenantCancellationRetentionTest.php` (Gate 6) — simula passagem de tempo via `Carbon::setTestNow()` e valida aplicação correta da política Q20 em cada checkpoint
- [ ] T110 [US-12.1] Criar `tests/Feature/SuperAdmin/OfflineBillingModeTest.php` (R-8-8) — tenant criado em `billing_mode=offline_invoice` NÃO cria customer no Stripe + bloqueia conversão reverse offline→stripe

### 4.2 Lote B — US-12.2 Planos Globais com Snapshot Versioning (P1)

**Acceptance**: AC-12.2.1 → AC-12.2.6 ✅

- [ ] T115 [P] [US-12.2] Criar migration `2026_05_23_000002_alter_plans_add_limits_columns.php` (3 cols)
- [ ] T116 [P] [US-12.2] Criar migration `2026_05_23_000003_create_plan_versions_table.php`
- [ ] T117 [P] [US-12.2] Criar migration `2026_05_23_000004_create_tenant_plan_bindings_table.php` + PARTIAL UNIQUE
- [ ] T118 [US-12.2] Criar migration `2026_05_23_000005_seed_initial_plan_versions_from_existing_plans.php` — para cada plano existente cria PlanVersion v1 + bind dos tenants atuais
- [ ] T119 [P] [US-12.2] Criar models `app/Domain/SuperAdmin/Models/PlanVersion.php` + `TenantPlanBinding.php` com casts JSON e scopes `active()`
- [ ] T120 [US-12.2] Implementar `app/Domain/SuperAdmin/Services/PlanVersioningService.php` com `createVersion(plan, snapshot)`, `migrateTenantToPlanVersion(tenant, plan_version, user, reason)` que dispara proration via Stripe Cashier (já existente)
- [ ] T121 [P] [US-12.2] Criar eventos `PlanoCriado`, `PlanoEditado`, `PlanoAlteradoPeloSuperAdmin`
- [ ] T122 [US-12.2] Criar `app/Filament/Resources/Plans/PlanResource.php` com Form (preço, limites, recursos), bulk action "Migrar tenants" (AC-12.2.5)
- [ ] T123 [US-12.2] Criar `tests/Feature/SuperAdmin/PlanVersioningTest.php` cobrindo AC-12.2.2 (snapshot versioning) — edição cria v2; tenants existentes ficam em v1
- [ ] T124 [P] [US-12.2] Criar `tests/Feature/SuperAdmin/PlanChangeProrationTest.php` cobrindo AC-12.2.3 — alteração de plano dispara `PlanoAlteradoPeloSuperAdmin` + proration Stripe stub
- [ ] T125 [P] [US-12.2] Criar `tests/Feature/SuperAdmin/PlanInactiveHidesFromPublicOnboardingTest.php` cobrindo AC-12.2.4

### 4.3 Lote B — US-12.3 Métricas Globais + Anomalias (P2)

**Acceptance**: AC-12.3.1 → AC-12.3.5 ✅

- [ ] T130 [P] [US-12.3] Criar migration `2026_05_23_000008_create_anomalies_detected_table.php`
- [ ] T131 [P] [US-12.3] Criar model `app/Domain/SuperAdmin/Models/AnomalyDetected.php` + factory
- [ ] T132 [US-12.3] Implementar `app/Domain/SuperAdmin/Services/GlobalMetricsService.php` com `computeMrr()`, `computeArr()`, `computeChurnPrimary()`, `computeRevenueChurn()`, `computeTrialToPaidConversion()`, `computeAiUsageTotal()` — todas usando `withoutGlobalScopes()` com gate de perfil (Gate 5)
- [ ] T133 [US-12.3] Implementar `app/Domain/SuperAdmin/Services/AnomalyDetectorService.php` com 4 detectores (conversion_drop, ai_usage_spike, webhook_failure_rate, payment_overdue) — thresholds configuráveis em `config/finalization.php`
- [ ] T134 [P] [US-12.3] Criar listener `NotifyAnomalyToSuperAdminListener` em `AnomaliaDetectada` — envia inbox + e-mail crítico (Q22) com cooldown 30min por categoria
- [ ] T135 [US-12.3] Criar `app/Console/Commands/SuperAdmin/ComputeGlobalMetricsCommand.php` (hourly) + `DetectAnomaliesCommand.php` (every 15min)
- [ ] T136 [P] [US-12.3] Criar `app/Filament/Pages/SuperAdmin/GlobalMetricsPage.php` exibindo MRR/ARR/churn/conversão/consumo IA + gráficos de tendência (Filament charts)
- [ ] T137 [P] [US-12.3] Criar `app/Filament/Pages/SuperAdmin/AnomaliesPage.php` com listagem + ações `Acknowledge`/`Resolve`
- [ ] T138 [US-12.3] Criar `tests/Feature/SuperAdmin/GlobalMetricsTenantIsolationTest.php` (Gate 5) — métricas globais NUNCA retornam dados de paciente individual (AC-12.3.2)
- [ ] T139 [P] [US-12.3] Criar `tests/Feature/SuperAdmin/AnomalyDetectionTest.php` simulando 4 categorias de anomalia + cooldown
- [ ] T140 [US-12.3] Estender `app/Support/Metrics/SuperAdminMetrics.php` com `tenant_lifecycle_total`, `impersonate_sessions_total`, `anomalies_detected_total`, `mrr_total`, `arr_total`, `churn_rate_percent`

**Checkpoint Lote B**: rodar `vendor/bin/sail artisan test tests/Feature/SuperAdmin/ --compact`. Gates 5, 6, 7 verdes. Cenários 4, 9, 10 do quickstart exequíveis. **Importante**: ALTERs em `plans` e `tenants` permitem que Lote C/D referenciem `daily_campaign_limit`, `api_rate_limit_per_minute`, `billing_mode`.

---

## Phase 5: Lote C — Campanhas (Épico 9)

**Story Goals**: Disparo em massa com guardrails Meta/LGPD em runtime; canal único; respeito a `business_hours`; limite diário por plano; relatório em polling 30s.

**Independent Test Criteria**: Cenário 1 do quickstart roda após Lotes A + B entregues.

### 5.1 Lote C — US-9.3 Conformidade de Disparo (P1)

> **Implementada PRIMEIRO** dentro de C — é a base que US-9.1 e US-9.2 consomem.

**Acceptance**: AC-9.3.1 → AC-9.3.5 ✅

- [ ] T142 [P] [US-9.3] Criar migration `2026_05_24_000004_create_campaign_templates_meta_table.php`
- [ ] T143 [US-9.3] Criar model `app/Domain/Campaigns/Models/CampaignTemplateMeta.php` com scope `approved()`, accessor `isApproved()` e checagem `meta_status_last_checked_at` <30min
- [ ] T144 [US-9.3] Implementar `app/Domain/Campaigns/Services/CampaignComplianceGate.php` com método `evaluate(Campaign, Patient): ComplianceResult` retornando `{passed: bool, block_reason?: string}` aplicando 4 validações sequenciais: opt-in marketing → template aprovado → business_hours → daily_limit
- [ ] T145 [P] [US-9.3] Criar `app/Domain/Campaigns/Services/MetaTemplateStatusChecker.php` consultando Meta Cloud API (fila `campaigns`) + cache 30min — usado pelo Gate
- [ ] T146 [US-9.3] Validar criação de template HSM no `CampaignTemplateMetaResource` (Filament ou interna) — rejeita template sem `has_unsubscribe=true` (AC-9.3.3)
- [ ] T147 [US-9.3] Criar `tests/Feature/Campaigns/CampaignDispatcherComplianceTest.php` (Gate 1) — testa cada uma das 4 validações em isolamento + cenário "all pass"
- [ ] T148 [P] [US-9.3] Criar `tests/Feature/Campaigns/TemplateRejectionWithoutUnsubscribeTest.php` cobrindo AC-9.3.3
- [ ] T149 [P] [US-9.3] Criar `tests/Feature/Campaigns/SairCommandRevokesMarketingTest.php` (já parcialmente coberto em T036 de US-13.1 — aqui valida integração: paciente após `/sair` é excluído do próximo dispatch em ≤30s)

### 5.2 Lote C — US-9.1 Campanha de Reativação de Inativos (P2)

**Acceptance**: AC-9.1.1 → AC-9.1.8 ✅

#### Migrations & Models

- [ ] T152 [P] [US-9.1] Criar migration `2026_05_24_000001_create_campaigns_table.php` conforme data-model.md §3.1
- [ ] T153 [P] [US-9.1] Criar migration `2026_05_24_000002_create_campaign_recipients_table.php` + UNIQUE `(campaign_id, patient_id)`
- [ ] T154 [P] [US-9.1] Criar migration `2026_05_24_000003_create_campaign_dispatch_log_table.php`
- [ ] T155 [US-9.1] Criar model `app/Domain/Campaigns/Models/Campaign.php` com `BelongsToTenant`, scopes `draft()`, `scheduled()`, `dispatching()`, `completed()`, casts `audience_filters:array`
- [ ] T156 [P] [US-9.1] Criar model `app/Domain/Campaigns/Models/CampaignRecipient.php` + `CampaignDispatchLog.php`
- [ ] T157 [P] [US-9.1] Criar factories para os 3 models acima

#### Services

- [ ] T158 [US-9.1] Implementar `app/Domain/Campaigns/Services/CampaignAudienceCalculator.php` — método `estimate(filters): int` e `iterate(filters): LazyCollection<Patient>` usando query indexada em `patients` JOIN `appointments` filtrando por `last_realized < D-N` (Q1)
- [ ] T159 [US-9.1] Implementar `app/Domain/Campaigns/Services/CampaignBuilder.php` com `create(data, user): Campaign`, `preview(campaign): {eligible_count, warnings[]}`, `cancel(campaign, user, reason)`
- [ ] T160 [US-9.1] Implementar `app/Domain/Campaigns/Services/CampaignDispatcher.php` com `dispatch(Campaign): void` — snapshot eligible recipients + enfileira `ProcessCampaignBatchJob`
- [ ] T161 [P] [US-9.1] Criar job `app/Domain/Campaigns/Jobs/ProcessCampaignBatchJob.php` (fila `campaigns`) — itera em chunks de 100, aplica `CampaignComplianceGate` por recipient, enfileira `SendCampaignMessageJob` para os aprovados
- [ ] T162 [US-9.1] Criar job `app/Domain/Campaigns/Jobs/SendCampaignMessageJob.php` — idempotent via DB UNIQUE `(campaign_id, patient_id)`; chama serviço de mensageria da Fase 3 com template HSM; trata response Meta e atualiza recipient.status
- [ ] T163 [P] [US-9.1] Criar 6 eventos: `CampanhaCriada`, `CampanhaAtualizada`, `CampanhaDisparada`, `CampanhaCancelada`, `MensagemCampanhaEnviada`, `PacienteDescadastradoDeCampanhas` — todos com `ContainsNoClinicalData`

#### Controllers & Endpoints

- [ ] T164 [P] [US-9.1] Criar 4 FormRequests em `app/Http/Requests/Campaigns/`: `CreateCampaignRequest`, `UpdateCampaignRequest`, `DispatchCampaignRequest`, `PreviewCampaignRequest`
- [ ] T165 [US-9.1] Criar `app/Http/Controllers/Api/V1/Campaigns/CampaignsController.php` com `index`, `show`, `store`, `update`, `destroy`, `preview`, `dispatch`, `cancel`, `report`
- [ ] T166 [P] [US-9.1] Criar 2 Resources: `CampaignResource.php` (sem PII de recipients) + `CampaignReportResource.php` (métricas agregadas)
- [ ] T167 [US-9.1] Adicionar 12 rotas em `routes/api.php` em group prefix `/campaigns`
- [ ] T168 [P] [US-9.1] Criar `app/Policies/CampaignPolicy.php`

#### Frontend

- [ ] T169 [P] [US-9.1] Criar 4 páginas Vue: `CampaignsIndexPage.vue`, `CampaignCreatePage.vue`, `CampaignShowPage.vue`, `CampaignReportPage.vue`
- [ ] T170 [P] [US-9.1] Criar store `resources/js/stores/campaigns.js` + componente reutilizável `CampaignAudienceFilterForm.vue`
- [ ] T171 [US-9.1] Implementar polling 30s no `CampaignReportPage.vue` durante `status=dispatching` usando `setInterval` + cleanup em `onUnmounted`

#### Cron

- [ ] T172 [US-9.1] Implementar `app/Console/Commands/Campaigns/DispatchScheduledCampaignsCommand.php` (signature `campaigns:dispatch-scheduled`) — varre `campaigns.status=scheduled AND scheduled_for <= now()` e enfileira `dispatch()`

#### Tests

- [ ] T173 [US-9.1] Criar `tests/Feature/Campaigns/CreateCampaignTest.php` cobrindo AC-9.1.1 + público estimado correto
- [ ] T174 [P] [US-9.1] Criar `tests/Feature/Campaigns/CampaignDispatchE2ETest.php` cobrindo AC-9.1.2 + fluxo completo (50 pacientes, 30 com opt-in, 5 sem template, 8 fora de horário → resultados corretos)
- [ ] T175 [P] [US-9.1] Criar `tests/Feature/Campaigns/CampaignIdempotencyTest.php` validando UNIQUE `(campaign_id, patient_id)` — re-dispatch não duplica
- [ ] T176 [P] [US-9.1] Criar `tests/Feature/Campaigns/CrossTenantCampaignTest.php` (Gate 2) — campanha tenant A não vê pacientes tenant B
- [ ] T177 [P] [US-9.1] Criar `tests/Feature/Campaigns/CampaignReportAttributionTest.php` cobrindo AC-9.1.7 — paciente que responde + agenda em ≤7d é atribuído

### 5.3 Lote C — US-9.2 Campanha Sazonal (P3)

**Acceptance**: AC-9.2.1 → AC-9.2.5 ✅

- [ ] T180 [US-9.2] Reaproveitar `CampaignBuilder` adicionando suporte a `scheduled_for` (já modelado no schema) — sem novo Service
- [ ] T181 [P] [US-9.2] Criar `tests/Feature/Campaigns/SeasonalCampaignSchedulingTest.php` cobrindo AC-9.2.1, AC-9.2.2 (cron picker), AC-9.2.3 (imutabilidade após dispatch)
- [ ] T182 [P] [US-9.2] Criar `tests/Feature/Campaigns/CampaignPreviewWarningsTest.php` cobrindo AC-9.2.4 — preview retorna avisos de pacientes sem opt-in / template não aprovado / fora de horário
- [ ] T183 [P] [US-9.2] Criar `tests/Feature/Campaigns/CampaignBatchFragmentationTest.php` cobrindo AC-9.2.5 — público >daily_limit fragmenta em sobras para D+1
- [ ] T184 [US-9.2] Estender `app/Support/Metrics/CampaignMetrics.php` com `campaign_dispatched_total{tenant,status}`, `campaign_blocked_total{reason,tenant}`, `campaign_recipients_total{campaign_id}`, `campaign_dispatch_duration_seconds{tenant}`
- [ ] T185 [US-9.2] Suite full após Lote C — `vendor/bin/sail artisan test --compact` + smoke Cenário 1 do quickstart manual

**Checkpoint Lote C**: Gates 1 e 2 verdes. Campanhas operáveis ponta a ponta.

---

## Phase 6: Lote D — Integrações (Épico 11)

**Story Goals**: Webhooks de saída com HMAC + retry + DLQ; API pública v1 com escopo Q14 + dual auth + rate limit por plano.

**Independent Test Criteria**: Cenários 5 e 6 do quickstart rodam após Lotes A+B+C entregues.

### 6.1 Lote D — US-11.1 Webhooks de Eventos (P3)

**Acceptance**: AC-11.1.1 → AC-11.1.8 ✅

#### Migrations & Models

- [ ] T188 [P] [US-11.1] Criar migrations `2026_05_25_000001_create_webhook_endpoints_table.php`, `000002_create_webhook_deliveries_table.php`, `000003_create_webhook_dead_letter_table.php`
- [ ] T189 [US-11.1] Criar models `WebhookEndpoint`, `WebhookDelivery`, `WebhookDeadLetter` em `app/Domain/Integrations/Models/` com scopes apropriados e relação `endpoint -> deliveries -> deadLetter`
- [ ] T190 [P] [US-11.1] Criar factories para os 3 models

#### Services

- [ ] T191 [US-11.1] Implementar `app/Domain/Integrations/Services/HmacSigner.php` com método estático `sign(string $payload, string $secret): string` retornando `sha256=<hex>` + `verify($payload, $secret, $signature): bool` usando `hash_equals`
- [ ] T192 [US-11.1] Implementar `app/Domain/Integrations/Services/WebhookDispatcher.php` com `dispatch(event_type, event_id, payload, tenant): void` — busca endpoints ativos do tenant subscritos, aplica mascaramento condicional (controladas + share_with_integrations) + enfileira `DispatchWebhookJob` para cada
- [ ] T193 [US-11.1] Criar listener universal `app/Domain/Integrations/Listeners/BroadcastDomainEventToWebhooksListener.php` que escuta os 13 eventos do catálogo Q17 e chama `WebhookDispatcher::dispatch()` — implementação via `subscribe()` method registrando todos os eventos
- [ ] T194 [US-11.1] Criar job `app/Domain/Integrations/Jobs/DispatchWebhookJob.php` (fila `webhooks`) com retry policy 5×exponential (30s, 2min, 10min, 1h, 6h) — após esgotar enfileira `MoveToDeadLetterJob`
- [ ] T195 [P] [US-11.1] Criar job `app/Domain/Integrations/Jobs/MoveToDeadLetterJob.php` — move row de `webhook_deliveries` para `webhook_dead_letter` + emite `WebhookFalhou` + `expires_at=now()+30d`
- [ ] T196 [P] [US-11.1] Criar 4 eventos: `WebhookConfigurado`, `WebhookEntregue`, `WebhookFalhou`, `WebhookReagendado`

#### Controllers

- [ ] T197 [P] [US-11.1] Criar `CreateWebhookEndpointRequest.php` validando URL HTTPS + ausência de IP privado (T007 UrlGuard) + max endpoints do plano (`webhook_max_endpoints`)
- [ ] T198 [US-11.1] Criar `app/Http/Controllers/Api/V1/Integrations/WebhooksController.php` com `index`, `store`, `update`, `destroy`, `pauseResume`, `listDeliveries`, `listDeadLetter`, `resendFromDlq`
- [ ] T199 [P] [US-11.1] Criar `WebhookEndpointResource.php` (segredo sempre mascarado), `WebhookDeliveryResource.php`, `WebhookDeadLetterResource.php`
- [ ] T200 [US-11.1] Adicionar rotas `/api/v1/integrations/webhooks/*` em `routes/api.php`
- [ ] T201 [P] [US-11.1] Criar `WebhookPolicy.php`

#### Cron

- [ ] T202 [P] [US-11.1] Implementar `app/Console/Commands/Integrations/PurgeExpiredDlqCommand.php` (daily 03:00 BRT) — deleta rows de `webhook_dead_letter` onde `expires_at < now()`

#### Frontend

- [ ] T203 [P] [US-11.1] Criar páginas Vue `WebhooksSettingsPage.vue`, `WebhookFormModal.vue` (criar/editar), `WebhookDeliveriesPage.vue` (histórico + DLQ + botão reenviar)
- [ ] T204 [P] [US-11.1] Criar store `resources/js/stores/webhooks.js`

#### Tests

- [ ] T205 [US-11.1] Criar `tests/Feature/Integrations/WebhookDispatchE2ETest.php` cobrindo AC-11.1.1 → AC-11.1.4 + HMAC válido + correlation_id presente
- [ ] T206 [P] [US-11.1] Criar `tests/Feature/Integrations/WebhookRetryPolicyTest.php` cobrindo AC-11.1.3 — simula 5xx + valida 5 tentativas com `Carbon::setTestNow()`
- [ ] T207 [P] [US-11.1] Criar `tests/Feature/Integrations/WebhookDeadLetterTest.php` cobrindo AC-11.1.5 e AC-11.1.6 — esgota retries → DLQ → admin reenvia manualmente
- [ ] T208 [P] [US-11.1] Criar `tests/Feature/Integrations/WebhookUrlSsrfGuardTest.php` (R-8-2) — rejeita URLs com IP privado
- [ ] T209 [P] [US-11.1] Criar `tests/Feature/Integrations/WebhookPayloadConsentMaskingTest.php` cobrindo AC-11.1.7 — paciente sem `share_with_integrations_consent` aparece como `<consent_withheld>`
- [ ] T210 [P] [US-11.1] Criar `tests/Feature/Integrations/WebhookCatalogCoverageTest.php` validando que todos os 13 eventos Q17 são despachados quando um endpoint os assina + os excluídos NÃO são despachados mesmo se erroneamente assinados
- [ ] T211 [P] [US-11.1] Criar `tests/Unit/Integrations/HmacSignerTest.php` validando assinatura + verificação + timing-safe comparison

### 6.2 Lote D — US-11.2 API Pública Documentada (P3)

**Acceptance**: AC-11.2.1 → AC-11.2.9 ✅

#### Migrations & Models

- [ ] T215 [P] [US-11.2] Criar migration `2026_05_25_000004_create_tenant_oauth_clients_table.php` (gated — só carregado se `config('finalization.oauth_enabled')`)
- [ ] T216 [P] [US-11.2] Criar model `app/Domain/Integrations/Models/TenantOauthClient.php`

#### Services & Middleware

- [ ] T217 [US-11.2] Implementar `app/Domain/Integrations/Services/ApiTokenService.php` com `create(tenant, name, scope, user): {token, model}` (token plaintext retornado UMA vez) e `revoke(token_id, user, reason)`
- [ ] T218 [US-11.2] Implementar `app/Domain/Integrations/Services/OauthClientService.php` (gated) com `createClient(tenant, name, scopes, user)` e `issueAccessToken(client_id, client_secret): JWT` — opt-in via toggle Filament
- [ ] T219 [US-11.2] Criar middleware `app/Http/Middleware/ApiPublicRateLimiter.php` aplicando rate limit por token (lookup `plan_version.snapshot.api_rate_limit_per_minute`) + cap por IP 10k/min — registrar em `bootstrap/app.php` para grupo `api/public`
- [ ] T220 [P] [US-11.2] Criar middleware `app/Http/Middleware/OauthAuthenticator.php` (gated) validando JWT Passport + injetando `tenant_id` no request
- [ ] T221 [P] [US-11.2] Criar middleware `app/Http/Middleware/EnsureTenantNotSuspended.php` — 503 `tenant_suspended` quando tenant da request está em status `suspenso`
- [ ] T222 [P] [US-11.2] Criar 2 eventos: `TokenApiEmitido`, `TokenApiRevogado`

#### Public API Controllers (6 recursos — Q14)

- [ ] T223 [US-11.2] Criar `app/Http/Controllers/Api/V1/Public/PatientsController.php` com `index`, `show`, `store`, `update` — reutiliza Service do PatientResource interno + adiciona mascaramento `share_with_integrations`
- [ ] T224 [P] [US-11.2] Criar `app/Http/Controllers/Api/V1/Public/AppointmentsController.php` com 4 verbos (index, show, store, update, destroy)
- [ ] T225 [P] [US-11.2] Criar `app/Http/Controllers/Api/V1/Public/MessagesController.php` (read-only — `index`, `show`)
- [ ] T226 [P] [US-11.2] Criar `app/Http/Controllers/Api/V1/Public/PrescriptionsController.php` (read-only) com mascaramento OBRIGATÓRIO de controladas no resource
- [ ] T227 [P] [US-11.2] Criar `app/Http/Controllers/Api/V1/Public/AppointmentTypesController.php` (read-only)
- [ ] T228 [P] [US-11.2] Criar `app/Http/Controllers/Api/V1/Public/ProfessionalsController.php` (read-only)

#### Resources (versões "public" — mascaramento extra)

- [ ] T229 [P] [US-11.2] Criar 6 resources em `app/Http/Resources/Api/Public/` correspondentes — variant do resource interno aplicando mascaramento adicional

#### Routes

- [ ] T230 [US-11.2] Popular `routes/api-public.php` com 11+ endpoints (group middleware `auth:sanctum,passport`, `EnsureTenantNotSuspended`, `ApiPublicRateLimiter`, throttle anti-DDoS) — prefix `v1`

#### Token Management UI

- [ ] T231 [P] [US-11.2] Criar `app/Http/Controllers/Api/V1/Integrations/ApiTokensController.php` (interno) com CRUD de tokens
- [ ] T232 [P] [US-11.2] Criar página Vue `ApiTokensSettingsPage.vue` com modal "Novo token" exibindo plaintext UMA vez + botão "Revogar"

#### OpenAPI

- [ ] T233 [US-11.2] Configurar Scribe (já presente na Fase 4) para grupo `api/public` gerando `public/docs/api/v1.yaml` + página HTML em `/docs/api/v1` — adicionar tags por recurso
- [ ] T234 [P] [US-11.2] Adicionar exemplos manuais nos 6 resources via PHPDoc `@apiResource` + `@apiResourceCollection` annotations

#### Tests

- [ ] T235 [US-11.2] Criar `tests/Feature/Integrations/PublicApiAuthenticationTest.php` cobrindo AC-11.2.3 — Sanctum bearer válido vs. inválido vs. revogado
- [ ] T236 [P] [US-11.2] Criar `tests/Feature/Integrations/PublicApiRateLimitTest.php` cobrindo AC-11.2.4 — 1100 req em 1min → 1000 com 200 + 100 com 429 + headers corretos
- [ ] T237 [P] [US-11.2] Criar `tests/Feature/Integrations/PublicApiScopeRestrictionTest.php` cobrindo AC-11.2.6 — endpoints fora do Q14 retornam 404 (não 401)
- [ ] T238 [P] [US-11.2] Criar `tests/Feature/Integrations/PublicApiControlledMaskingTest.php` (R-8-4) — receita controlada via API pública SEMPRE mascarada independente do scope do token
- [ ] T239 [P] [US-11.2] Criar `tests/Feature/Integrations/PublicApiIdempotencyKeyTest.php` cobrindo NFR-9 — `Idempotency-Key` em POST retorna mesmo response em 24h
- [ ] T240 [P] [US-11.2] Criar `tests/Feature/Integrations/PublicApiTenantSuspendedTest.php` validando 503 `tenant_suspended`
- [ ] T241 [P] [US-11.2] Criar `tests/Feature/Integrations/OauthClientCredentialsTest.php` (skip se Passport não habilitado) — token JWT 1h emitido contra client_id+client_secret
- [ ] T242 [US-11.2] Estender `app/Support/Metrics/WebhookMetrics.php` e `ApiPublicMetrics.php` com métricas do plan.md §7
- [ ] T243 [US-11.2] Validar `vendor/bin/sail artisan scribe:generate` produz OpenAPI consistente — adicionar `tests/Feature/Integrations/OpenApiSpecValidatesTest.php` lendo o YAML e validando contra schema OpenAPI 3.0

**Checkpoint Lote D**: smoke Cenários 5 e 6 exequíveis. OpenAPI publicado e acessível.

---

## Phase 7: Lote E — Relatórios (Épico 10)

**Story Goals**: Dashboard Executivo + Operacional + Clínico tenant-scoped, com agregações horárias, drill-down, exportação PDF formatado, escopo por perfil.

**Independent Test Criteria**: Cenário 7 do quickstart roda após Lotes A+B+C+D entregues.

### 7.1 Lote E — US-10.1 Dashboard Executivo (P1)

**Acceptance**: AC-10.1.1 → AC-10.1.7 ✅

#### Migrations & Models

- [ ] T246 [P] [US-10.1] Criar migration `2026_05_26_000001_create_metric_aggregations_table.php` + UNIQUE composto
- [ ] T247 [P] [US-10.1] Criar migration `2026_05_26_000002_create_report_exports_table.php`
- [ ] T248 [US-10.1] Criar models `MetricAggregation`, `ReportExport` em `app/Domain/Reports/Models/`

#### Services & Jobs

- [ ] T249 [US-10.1] Implementar `app/Domain/Reports/Services/MetricAggregator.php` com método `aggregate(tenant, metric_name, period, period_start): MetricAggregation` — upsert idempotente; suporta 8 métricas (leads_by_channel, conversion_rate, no_show_rate, estimated_revenue, response_time_first_p95, ai_autonomous_resolution_rate, occupancy_by_professional, top_procedure_types)
- [ ] T250 [US-10.1] Implementar `app/Domain/Reports/Services/ExecutiveDashboardService.php` com `getKpis(tenant, period_start, period_end): array` — usa agregações para ≥7d e queries live para ≤24h (Q9); aplica escopo por perfil (Q13)
- [ ] T251 [P] [US-10.1] Criar job `app/Domain/Reports/Jobs/AggregateHourlyMetricsJob.php` (fila `reports`) — varre tenants ativos e chama `MetricAggregator` para cada métrica × período
- [ ] T252 [US-10.1] Criar command `app/Console/Commands/Reports/AggregateHourlyMetricsCommand.php` (hourly :05)

#### PDF Renderer

- [ ] T253 [US-10.1] Implementar `app/Domain/Reports/Services/DashboardPdfRenderer.php` usando DOMPDF + view Blade `resources/views/reports/dashboard.blade.php` — layout com cabeçalho clínica + sumário + 5 cards + gráficos SVG estáticos + rodapé filtros
- [ ] T254 [P] [US-10.1] Criar evento `RelatorioExportado` (audit-only)

#### Controllers

- [ ] T255 [P] [US-10.1] Criar `app/Http/Controllers/Api/V1/Reports/ExecutiveDashboardController.php` com `show`, `drillDown(metric_name)`, `exportPdf`
- [ ] T256 [P] [US-10.1] Criar 2 Resources: `ExecutiveDashboardResource.php`, `DrillDownListResource.php`
- [ ] T257 [US-10.1] Adicionar rotas `/api/v1/reports/executive/*` + `ReportPolicy.php`

#### Frontend

- [ ] T258 [P] [US-10.1] Criar página `resources/js/pages/Reports/ExecutiveDashboardPage.vue` com 5 cards + filter de período + drill-down + botão "Exportar PDF" + variação % (Q11)
- [ ] T259 [P] [US-10.1] Criar componente `KpiCardWithTrend.vue` reutilizável (recebe valor atual + valor anterior + label)

#### Tests

- [ ] T260 [US-10.1] Criar `tests/Feature/Reports/ExecutiveDashboardKpisTest.php` cobrindo AC-10.1.1 + 5 cards com valores corretos (incluindo NPS placeholder Q8)
- [ ] T261 [P] [US-10.1] Criar `tests/Feature/Reports/DashboardDrillDownTest.php` cobrindo AC-10.1.3 — clique em "23 leads Instagram" abre lista filtrada
- [ ] T262 [P] [US-10.1] Criar `tests/Feature/Reports/DashboardPdfExportTest.php` cobrindo AC-10.1.5 — PDF gerado em ≤3s + audit_log + cabeçalho/rodapé corretos
- [ ] T263 [P] [US-10.1] Criar `tests/Feature/Reports/DashboardScopeByRoleTest.php` cobrindo AC-10.1.6 (Q13/SC-10.3) — Médico só vê própria agenda; manipulação de query → 403
- [ ] T264 [P] [US-10.1] Criar `tests/Feature/Reports/HourlyAggregationStaleAlertTest.php` (R-8-5) — quando `metric_aggregation_lag_seconds > 5400` dashboard exibe banner

### 7.2 Lote E — US-10.2 Relatórios Operacionais (P2)

**Acceptance**: AC-10.2.1 → AC-10.2.5 ✅

- [ ] T268 [US-10.2] Implementar `app/Domain/Reports/Services/OperationalReportService.php` — agrega `ai_decision_logs` (Fase 4 — placeholder se tabela não existe) + tempos de resposta + volumes
- [ ] T269 [P] [US-10.2] Criar `app/Http/Controllers/Api/V1/Reports/OperationalReportController.php` + Resource
- [ ] T270 [P] [US-10.2] Criar página `OperationalReportPage.vue`
- [ ] T271 [US-10.2] Criar `tests/Feature/Reports/OperationalReportTest.php` cobrindo AC-10.2.1, AC-10.2.2, AC-10.2.4 (filtros de dados null)

### 7.3 Lote E — US-10.3 Relatórios Clínicos (P2)

**Acceptance**: AC-10.3.1 → AC-10.3.6 ✅

- [ ] T274 [US-10.3] Implementar `app/Domain/Reports/Services/ClinicalReportService.php` — ocupação por profissional + ranking de procedimentos + retornos (gated por feature flag `app.modules.returns.enabled`)
- [ ] T275 [P] [US-10.3] Criar `ClinicalReportController.php` + Resource + Page Vue
- [ ] T276 [P] [US-10.3] Criar `tests/Feature/Reports/ClinicalReportTest.php` cobrindo AC-10.3.1 + ocupação + mix
- [ ] T277 [P] [US-10.3] Criar `tests/Feature/Reports/ClinicalReportReturnsFeatureFlaggedTest.php` cobrindo AC-10.3.3 com feature flag ON e OFF
- [ ] T278 [US-10.3] Estender `app/Support/Metrics/ReportMetrics.php` com `reports_exported_total{type,format,tenant}`, `metric_aggregation_lag_seconds`, `dashboard_load_duration_seconds`

**Checkpoint Lote E**: Cenário 7 do quickstart exequível. Todas as 13 user stories entregues.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: E2E Playwright, suite full verde, observabilidade dashboards, documentação OpenAPI publicada, runbook operacional.

### 8.1 E2E Playwright (constituição Princípio IV — jornadas críticas)

- [ ] T282 [P] Criar E2E `tests/E2E/specs/campaign-dispatch.spec.ts` cobrindo Cenário 1 do quickstart (criar campanha → preview → dispatch → relatório atualiza)
- [ ] T283 [P] Criar E2E `tests/E2E/specs/right-to-be-forgotten.spec.ts` cobrindo Cenário 2 (paciente solicita → admin executa → anonimização aplicada → preserved with banner)
- [ ] T284 [P] Criar E2E `tests/E2E/specs/data-portability.spec.ts` cobrindo Cenário 3 (solicitação → gerar arquivo → download via URL assinada → expirar em 7d)
- [ ] T285 [P] Criar E2E `tests/E2E/specs/super-admin-impersonate.spec.ts` cobrindo Cenário 4 (Super Admin → impersonate → banner persistente → audit 5 telas → sair)
- [ ] T286 [P] Criar E2E `tests/E2E/specs/webhook-delivery.spec.ts` cobrindo Cenário 5 (configurar webhook + receber em mock + valida HMAC + DLQ)

### 8.2 Constitution Gates final

- [ ] T287 Rodar manualmente todos os 7 gates e validar verdes: Gate 1 (Compliance Dispatcher), Gate 2 (Cross-tenant), Gate 3 (LGPD Mapa), Gate 4 (Pseudonimização CI), Gate 5 (Super Admin Scope), Gate 6 (Retention), Gate 7 (Impersonate Audit) — documentar resultado em `docs/qa/gates-fase8-final.md`
- [ ] T288 Rodar suite full `vendor/bin/sail artisan test --compact` — validar `~1517 tests / 1517 passed / 0 failures` (1342 da Fase 7 + ~175 desta fase) e investigar qualquer flaky

### 8.3 Observabilidade & operacional

- [ ] T289 [P] Configurar dashboards Grafana (em `docs/observability/grafana-fase8.json`) com 5 painéis: campanhas, relatórios, webhooks, super admin, privacidade — métricas Prometheus do §7 do plan
- [ ] T290 [P] Validar Sentry tags por módulo (`campaign.id`, `report.type`, `webhook.id`, `impersonate.session_id`, `forgetting.request_id`) em smoke local
- [ ] T291 [P] Adicionar `App\Support\Lgpd\PiiScrubber` ao Sentry callback global (validar via teste de upload de erro contendo CPF)

### 8.4 Documentação

- [ ] T292 [P] Gerar OpenAPI final: `vendor/bin/sail artisan scribe:generate` + validar `public/docs/api/v1.yaml` + página HTML acessível
- [ ] T293 [P] Criar runbook operacional `docs/runbooks/super-admin-operations.md` cobrindo: impersonate, suspend, reativar, cancelar tenant manual, criar tenant offline_invoice, gerenciar planos versionados
- [ ] T294 [P] Criar documentação de privacidade `docs/lgpd/privacy-operations.md` com fluxos de consentimento, esquecimento, portabilidade + mapa Q26 + screenshots do painel
- [ ] T295 [P] Atualizar `CLAUDE.md` Active feature → `008-finalizacao-mvp` ENTREGUE com estatísticas finais + adicionar "Finalização (Fase 8) — Key Patterns" replicando padrão das fases anteriores

### 8.5 Smoke staging E2E

- [ ] T296 Executar 10 cenários do `quickstart.md` em ambiente staging com tenant QA e Stripe sandbox — documentar resultado em `docs/qa/smoke-fase8-staging.md` com prints
- [ ] T297 Validar performance em staging: dashboard ≤1,5s p95 com 50k pacientes seedados; webhook delivery ≤5s p95; campanha 100 destinatários ≤5min — capturar métricas Prometheus pré e pós
- [ ] T298 [P] Aprovação DPO/jurídico para política de retenção Q20 e mapa de anonimização Q26 (documentar em `docs/lgpd/dpo-approval-fase8.md`)

### 8.6 Migrações concluintes

- [ ] T299 Confirmar todas as 22 migrations rodadas em order: `vendor/bin/sail artisan migrate --pretend` mostra 0 migrations pendentes; rollback test em DB isolado funciona
- [ ] T300 Marcar feature como **DELIVERED** atualizando `.specify/feature.json` mantendo `feature_directory` + adicionar entrada em `specs/008-finalizacao-mvp/checklists/requirements.md` confirmando 4/4 itens "Feature Readiness" pass

---

## Dependencies & Story Completion Order

```text
Phase 1 (Setup T001-T010) ──→ Phase 2 (Foundational T011-T018) ──→ All stories
       │
       ↓
Phase 3 Lote A (US-13.1, US-13.2, US-13.3)
       │  ├─ US-13.1 (T020-T038) — base de consentimento
       │  ├─ US-13.2 (T040-T067) — esquecimento + portabilidade
       │  └─ US-13.3 (T070-T080) — auditoria pseudonimização
       ↓
Phase 4 Lote B (US-12.1, US-12.2, US-12.3)
       │  ├─ US-12.1 (T085-T110) — gestão de tenants
       │  ├─ US-12.2 (T115-T125) — planos versionados (ALTERs em plans)
       │  └─ US-12.3 (T130-T140) — métricas globais + anomalias
       ↓
Phase 5 Lote C (US-9.3 → US-9.1 → US-9.2)
       │  ├─ US-9.3 (T142-T149) — conformidade (gate primeiro)
       │  ├─ US-9.1 (T152-T177) — reativação
       │  └─ US-9.2 (T180-T185) — sazonal
       ↓
Phase 6 Lote D (US-11.1, US-11.2)
       │  ├─ US-11.1 (T188-T211) — webhooks
       │  └─ US-11.2 (T215-T243) — API pública
       ↓
Phase 7 Lote E (US-10.1, US-10.2, US-10.3)
       │  ├─ US-10.1 (T246-T264) — dashboard executivo
       │  ├─ US-10.2 (T268-T271) — operacional
       │  └─ US-10.3 (T274-T278) — clínico
       ↓
Phase 8 (Polish T282-T300) — E2E + Gates + Docs + Smoke staging
```

**MVP scope sugerido para validação interna inicial**: Phase 1 + Phase 2 + US-13.1 + US-12.1 + US-9.3 + US-9.1 + US-10.1 (~120 tasks) — entrega "produto vendável mínimo" com consentimento, gestão de tenants, campanhas com gate de conformidade, e dashboard. US-13.2, US-13.3, US-12.2, US-12.3, US-9.2, US-11.1, US-11.2, US-10.2, US-10.3 são incrementais para go-live público.

---

## Parallel Execution Opportunities

Dentro de cada Phase, tasks marcadas com **[P]** podem rodar em paralelo (diferentes arquivos, sem dependência pendente). Exemplos chave:

- **Phase 1**: T002, T003, T004, T005, T006, T008, T009 (todos [P]) — 7 tasks em paralelo
- **Phase 2**: T013, T014, T016, T017 [P]
- **US-13.1**: T020, T021, T023 [P] (migrations + factory); T025, T029, T030, T033, T034 [P]; testes T036, T037, T038 [P]
- **US-13.2**: T040, T041, T043, T044 [P] (migrations + factories); T046, T053, T054, T059, T060, T061 [P]; testes T063, T064, T065, T066 [P]
- **US-12.1**: T085, T086, T087, T089, T090, T091 [P] (migrations + models); T100, T101, T102, T103 [P]; testes T106, T107, T108, T109 [P]
- **US-9.1**: T152, T153, T154, T156, T157 [P]; T161, T163, T164, T166, T168, T169, T170 [P]; testes T174-T177 [P]
- **US-11.1**: T188, T190, T195, T196, T197, T199, T201, T202, T203, T204 [P]; testes T206-T211 [P]
- **US-11.2**: T215, T216, T220, T221, T222, T224, T225, T226, T227, T228, T229, T232, T234 [P]; testes T236-T242 [P]
- **US-10.1**: T246, T247, T251, T254, T255, T256, T258, T259 [P]; testes T261-T264 [P]
- **Phase 8**: T282-T286 [P] (E2E specs independentes); T289-T295 [P]

---

## Implementation Strategy

### Recomendação de execução

1. **Lote A inteiro (Phase 3) merge** antes de iniciar Lote B. Validar Gate 3 e 4 verdes. **Risk**: drift de pattern Q29 se atrasar.
2. **Lote B inteiro (Phase 4) merge** — ALTERs em `plans` e `tenants` ficam disponíveis para C/D. Validar Gates 5, 6, 7.
3. **Lote C** (US-9.3 → US-9.1 → US-9.2) — pode ser sub-merged em 2 PRs (compliance gate + features de reativação/sazonal).
4. **Lote D** (US-11.1 → US-11.2) — 2 PRs separados (webhooks + API pública).
5. **Lote E** (US-10.1 → US-10.2 → US-10.3) — pode ser sub-merged em até 3 PRs.
6. **Phase 8 Polish** — PR final consolidando E2E + docs + smoke staging.

### Checkpoint após cada lote

- `vendor/bin/sail artisan test --compact` → suite verde
- `vendor/bin/sail bin pint --dirty --format agent` → 0 issues
- Constitution Check parcial revisado para o lote
- Audit log de releases atualizado em `docs/releases/008-finalizacao-mvp.md`

---

## Metrics & Validation

- **Total tasks**: 270 (numeradas T001 → T300 com gaps em IDs reservados)
- **Tasks por lote**:
  - Setup + Foundational: 18
  - Lote A (Privacidade): 60
  - Lote B (Super Admin): 56
  - Lote C (Campanhas): 44
  - Lote D (Integrações): 56
  - Lote E (Relatórios): 33
  - Polish: 19
- **Parallel opportunities**: ~120 tasks com `[P]` (~44% paralelizáveis dentro do seu lote)
- **Tests**: ~175 feature + ~45 unit + 5 E2E = **~225 testes novos**
- **Cobertura alvo**: ≥ 70% (constituição) — esperado ~78% baseado em mapeamento AC→test

### Independent test criteria per story

| Story | Independent test (do quickstart.md) |
|---|---|
| US-13.1 | Cenário smoke: paciente consente → revoga via /sair → admin vê audit |
| US-13.2 | Cenário 2: paciente solicita → admin executa → anonimização Q26 + Cenário 3: portabilidade JSON com URL 7d |
| US-13.3 | Cenário 8: replay semanal detecta CPF + CI gate falha sem marker |
| US-12.1 | Cenário 4: impersonate 5 telas + audit + Cenário 10: cancelar tenant + retenção diferenciada |
| US-12.2 | E2E: editar plano cria v2; tenant existente fica em v1 |
| US-12.3 | Cenário 9: anomaly detection + inbox + e-mail crítico |
| US-9.3 | Gate 1: 4 validações em runtime do dispatcher |
| US-9.1 | Cenário 1: criar reativação → preview → dispatch → relatório |
| US-9.2 | E2E: agendar campanha D+7 + cron picker + fragmentação |
| US-11.1 | Cenário 5: configurar webhook + receber mock + HMAC + DLQ + reenviar |
| US-11.2 | Cenário 6: token Sanctum + rate limit + scope restrição + mascaramento controladas |
| US-10.1 | Cenário 7: dashboard ≤1,5s + drill-down + PDF + escopo Médico |
| US-10.2 | E2E: operacional com tempos de resposta + escalonamento IA |
| US-10.3 | E2E: ocupação + mix procedimentos + retornos gated por feature flag |

---

## Format Validation

✅ Todos os 270 tasks seguem o formato estrito: `- [ ] [TaskID] [P?] [Story?] Description with file path`
✅ Phase 1 (Setup) e Phase 2 (Foundational): SEM story label
✅ Phases 3–7 (User Stories): TODOS com story label `[US-X.Y]` correspondente
✅ Phase 8 (Polish): SEM story label
✅ Paths absolutos relativos ao repo root em 100% das tasks
✅ Markers `[P]` apenas em tasks de arquivos distintos sem dependência incompleta na mesma phase

---

## Next steps

1. Revisar este `tasks.md` — qualquer ajuste de escopo ou prioridade antes de implementar.
2. (Opcional) `/speckit-analyze` — cross-check spec ↔ plan ↔ tasks para inconsistências.
3. `/speckit-implement` — inicia execução pelo Lote A. Cada task gera commit separado quando possível.
