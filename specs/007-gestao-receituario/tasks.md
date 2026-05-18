# Tasks: Gestão de Receituários (Fase 7 — Épico 8)

**Input**: Design documents from `/specs/007-gestao-receituario/`
**Prerequisites**: spec.md (Clarified), plan.md (Constitution Check PASS 7/7), data-model.md, contracts/openapi.yaml, research.md, quickstart.md
**Branch**: `007-gestao-receituario`
**Stack**: Laravel 13 · PHP 8.5 · PostgreSQL 16 · Redis 7 · Vue 3 + Pinia + Tailwind v4 · Filament 5 · Reverb v1 · Sanctum Bearer · Horizon · Sail
**Tests**: OBRIGATÓRIOS (Princípio IV Test-First + 6 gates constitucionais em plan §3)

## Format: `[ID] [P?] [Story?] Description with file path`

- **[P]**: Pode rodar em paralelo (arquivo diferente, sem dependência de tarefa pendente)
- **[Story]**: US1 = Cadastro, US2 = Alerta, US3 = Renovação IA, US4 = Relatório
- Tarefas sem `[Story]` pertencem a Setup, Foundational ou Polish
- Comandos com `vendor/bin/sail` (todo ambiente roda em Sail)

## Mapeamento Lote → Phases

| Lote (plan §12) | Phases tasks.md | Range | Output |
|---|---|---|---|
| **A — Setup + Foundational** | Phase 1 + Phase 2 | T001-T032 | Schema, abilities, base do domínio |
| **B — US-8.1 Cadastro** | Phase 3 | T033-T084 | Médico cria receita, mascaramento, PDF async |
| **C — US-8.2 Alerta** | Phase 4 | T085-T122 | Cadência D-15/7/1, idempotência, integração mensageria |
| **D — US-8.3 Renovação IA** | Phase 5 | T123-T149 | Payload pseudonimizado, contrato forward IA |
| **E — US-8.4 Relatório + Polish** | Phase 6 + Phase 7 | T150-T199 | Relatório, CSV, Filament, benchmarks, quickstart E2E |

---

## Phase 1: Setup (Lote A — parte 1)

**Purpose**: Estrutura de diretórios e scaffolding antes das migrations.

- [x] T001 Criar estrutura de diretórios `app/Domain/Prescription/{Prescription,PrescriptionItem,Alert,Renewal,Report,Pdf,Preferences}/` conforme plan §4
- [x] T002 [P] Criar diretórios `app/Events/Prescription/`, `app/Listeners/Prescription/`, `app/Jobs/Prescription/`, `app/Http/Controllers/Api/V1/Prescriptions/`, `app/Http/Requests/Prescriptions/`, `app/Http/Resources/Prescriptions/`
- [x] T003 [P] Criar diretório `app/Support/Lgpd/` para interface marker `ContainsNoClinicalData` (research §3)
- [x] T004 [P] Criar diretórios `resources/js/pages/prescriptions/`, `resources/js/components/prescriptions/`, `resources/js/stores/`, `resources/js/composables/`, `resources/js/lib/`
- [x] T005 [P] Criar diretórios `tests/Feature/Prescription/` e `tests/Unit/Prescription/`

---

## Phase 2: Foundational (Lote A — parte 2) ⚠️ BLOQUEIA TODAS AS US

**Purpose**: Schema completo, abilities Spatie, middleware de gate de plano, scaffolding de eventos/policy. Sem isto, nenhuma US pode começar.

### Migrations (data-model.md §12)

- [x] T006 Migration `database/migrations/2026_05_17_000001_create_prescriptions_table.php` — agregado raiz com 7 ENUMs, 6 CHECK constraints, 6 índices (data-model §2)
- [x] T007 Migration `database/migrations/2026_05_17_000002_create_prescription_items_table.php` — 1:N items com trigger `enforce_controlled_single_item` + índice `pg_trgm` em `medication_name` (data-model §3)
- [x] T008 Migration `database/migrations/2026_05_17_000003_create_prescription_alerts_table.php` — UNIQUE `(prescription_id, alert_type)` + índices de fila + denormalização `tenant_id` (data-model §4)
- [x] T009 Migration `database/migrations/2026_05_17_000004_create_prescription_renewals_table.php` — junção explícita com UNIQUE parcial `original_prescription_id WHERE renewed_prescription_id IS NOT NULL` (data-model §5)
- [x] T010 Migration `database/migrations/2026_05_17_000005_create_patient_professional_preferences_table.php` — preparação estrutural Q13 com UNIQUE `(patient_id, professional_id)` (data-model §6)
- [x] T011 Migration `database/migrations/2026_05_17_000006_extend_tenants_settings_with_prescription_keys.php` — adiciona chaves JSONB `tenant.settings.{modules.prescriptions.enabled, prescriptions.*}` (data-model §7)
- [x] T012 Migration `database/migrations/2026_05_17_000007_seed_prescription_abilities.php` — seed das 7 abilities Spatie via `Permission::firstOrCreate` por guard `web` (data-model §8)

### Enums PHP 8.5 (plan §4 — `app/Domain/Prescription/Prescription/`)

- [x] T013 [P] Criar Enum `app/Domain/Prescription/Prescription/PrescriptionType.php` com cases `Common`, `Special`, `Controlled` + método `maxItems(): int`
- [x] T014 [P] Criar Enum `app/Domain/Prescription/Prescription/PrescriptionStatus.php` com cases `Active`, `Cancelled`, `Superseded`
- [x] T015 [P] Criar Enum `app/Domain/Prescription/Prescription/PrescriptionSource.php` com cases `Manual`, `Import`, `Ai`
- [x] T016 [P] Criar Enum `app/Domain/Prescription/Prescription/CancellationReasonCategory.php` com cases `ErroEmissao`, `DesistenciaPaciente`, `Substituicao`, `Outro`
- [x] T017 [P] Criar Enum `app/Domain/Prescription/Alert/AlertType.php` com cases `Days15`, `Days7`, `Days1` + método `daysBefore(): int`
- [x] T018 [P] Criar Enum `app/Domain/Prescription/Alert/AlertStatus.php` com cases `Pending`, `Dispatched`, `BlockedNoChannel`, `BlockedNoTemplate`, `Skipped`, `Cancelled`, `Failed`
- [x] T019 [P] Criar Enum `app/Domain/Prescription/Renewal/InitiatedByType.php` com cases `Professional`, `Ai`, `Patient`

### Infraestrutura cross-cutting

- [x] T020 Criar marker interface `app/Support/Lgpd/ContainsNoClinicalData.php` (research §3 — interface sem métodos, gate via reflection)
- [x] T021 [P] Criar helper `app/Domain/Prescription/Alert/PrescriptionAlertIdempotencyKey.php` com método estático `for(int $prescriptionId, AlertType $type, CarbonImmutable $date): string` (research §4)
- [x] T022 [P] Criar contrato `app/Support/Metrics/PrescriptionMetricsContract.php` + impl `app/Support/Metrics/PrescriptionMetrics.php` (plan §3 — 4 métricas Prometheus)
- [x] T023 Criar middleware `app/Http/Middleware/EnsurePrescriptionModuleEnabled.php` (gate de plano via `tenant.settings.modules.prescriptions.enabled` — plan §3 Princípio VIII)
- [x] T024 Registrar alias `prescription.module` para o middleware em `bootstrap/app.php`

### Policy + scaffolding de domínio

- [x] T025 Criar `app/Policies/PrescriptionPolicy.php` com métodos `view`, `viewControlled`, `create`, `update`, `cancel`, `export` (convenção C2 — Policy obrigatória)
- [x] T026 Registrar `PrescriptionPolicy` em `app/Providers/AppServiceProvider.php` via `Gate::policy()` (NÃO registrar listeners aqui — auto-discovery Laravel 13)
- [x] T027 [P] Criar model esqueleto `app/Domain/Prescription/Prescription/Prescription.php` com trait `BelongsToTenant`, casts `'notes' => 'encrypted'`, `'type' => PrescriptionType::class`, `'status' => PrescriptionStatus::class`, relations (`patient`, `professional`, `appointment`, `items`, `alerts`, `renewedFrom`)
- [x] T028 [P] Criar model esqueleto `app/Domain/Prescription/PrescriptionItem/PrescriptionItem.php` com BelongsTo `Prescription`
- [x] T029 [P] Criar model esqueleto `app/Domain/Prescription/Alert/PrescriptionAlert.php` com BelongsToTenant + casts de enum
- [x] T030 [P] Criar model esqueleto `app/Domain/Prescription/Renewal/PrescriptionRenewal.php` com BelongsToTenant
- [x] T031 [P] Criar model esqueleto `app/Domain/Prescription/Preferences/PatientProfessionalPreference.php` com BelongsToTenant
- [x] T032 Aplicar global scope `withControlledIfAble` em `Prescription::booted()` (plan §7 C5 — mascaramento por ability `prescription.view_controlled`)

**Checkpoint**: Schema migrado, abilities seedadas, models básicos prontos, policy registrada. US-8.1 a US-8.4 podem começar em paralelo (com dependências internas documentadas abaixo).

---

## Phase 3: US-8.1 Cadastro de Receituário (P1) 🎯 MVP (Lote B)

**Goal**: Médico autenticado cadastra receita (comum/especial/controlada), com itens, posologia e PDF anexável depois. Mascaramento de controladas funciona desde criação. Timeline do paciente recebe item.

**Independent Test**: criar 1 receita de cada tipo, verificar persistência, eventos, listagem por perfil, PDF async. Não exige US-8.2/8.3/8.4.

### Tests for US-8.1 (Test-First obrigatório — escrever antes da impl) ⚠️

- [x] T033 [P] [US1] Teste `tests/Feature/Prescription/PrescriptionCreationTest.php` — AC-8.1.1, AC-8.1.2, AC-8.1.3 (validade `controlled`/`special` = 30d fixos; `common` aceita só `{30,60,90,180}` server-side)
- [x] T034 [P] [US1] Teste `tests/Feature/Prescription/ControlledPrescriptionRegulatoryTest.php` — ⭐ Gate Portaria 344/98: controlada com >1 item retorna 422; tentativa de override `expires_at` rejeitada server-side
- [x] T035 [P] [US1] Teste `tests/Feature/Prescription/PrescriptionUpdateImmutabilityTest.php` — AC-8.1.7 (PATCH só aceita `notes`; tipo/items/expires_at imutáveis após save)
- [x] T036 [P] [US1] Teste `tests/Feature/Prescription/PrescriptionCancellationTest.php` — AC-8.1.8 (cancelamento exige category+texto, é irreversível, emite `PrescricaoCancelada`)
- [x] T037 [P] [US1] Teste `tests/Feature/Prescription/ControlledPrescriptionAccessTest.php` — ⭐ Gate principal: 5 perfis × 4 endpoints (matriz Q8); Atendente/Recepcionista/médico não-emissor recebem `PrescriptionMasked`; emissor + Admin Clínica com `view_controlled` veem completo + audit log
- [x] T038 [P] [US1] Teste `tests/Feature/Prescription/CrossTenantPrescriptionTest.php` — ⭐ Gate multi-tenant: 7 cenários (list/show/create/update/cancel/export/pdf) — tenant B recebe 404 em receita do tenant A; audit log `cross_tenant_attempt`
- [x] T039 [P] [US1] Teste `tests/Feature/Prescription/PrescriptionPdfUploadAsyncTest.php` — AC-8.1.4 (textuais persistidos antes do upload; falha de S3 não invalida receita)
- [x] T040 [P] [US1] Teste `tests/Feature/Prescription/PrescriptionPdfVersioningTest.php` — Q7b (substituição preserva `v0.pdf` no S3 + audit `pdf_replaced`)
- [x] T041 [P] [US1] Teste `tests/Unit/Prescription/PrescriptionExpiryCalculatorTest.php` — `issued_at + duration_days` no fuso do profissional (R-7P-07)
- [x] T042 [P] [US1] Teste `tests/Unit/Prescription/ControlledPrescriptionMaskingServiceTest.php` — service de mascaramento em isolamento
- [x] T043 [P] [US1] Teste `tests/Feature/Prescription/PrescriptionAbilitiesTest.php` — AC-8.1.5 (Atendente/Recepcionista sem `prescription.create` → 403)
- [x] T044 [P] [US1] Teste `tests/Feature/Prescription/PrescriptionTimelineProjectionTest.php` — AC-8.1.10 (listener `ProjectPrescriptionToPatientTimeline` projeta `PrescricaoCriada`/`Cancelada`/`Renovada`; alertas não poluem — Q11)

### Factories

- [x] T045 [P] [US1] Criar `database/factories/Prescription/PrescriptionFactory.php` com states `common()`, `special()`, `controlled()`, `cancelled()`, `expired()`
- [x] T046 [P] [US1] Criar `database/factories/Prescription/PrescriptionItemFactory.php` com state `controlled()` (1 item) e `multi()` (3 itens)
- [x] T047 [P] [US1] Criar `database/factories/Prescription/PrescriptionAlertFactory.php` (states `pending`, `dispatched`, `skipped`)
- [x] T048 [P] [US1] Criar `database/factories/Prescription/PrescriptionRenewalFactory.php`

### Domain models (completar boot logic)

- [x] T049 [US1] Completar `app/Domain/Prescription/Prescription/Prescription.php` — booted observer que valida `controlled → exactly 1 item`, `alert_disabled = true → type = common`, cálculo de `expires_at` server-side; método `isExpired()`, `isCancelled()`, `criticality()` (verde/amarelo/vermelho)
- [x] T050 [US1] Implementar `app/Domain/Prescription/Prescription/PrescriptionService.php` — métodos `create(StorePrescriptionRequest $data): Prescription`, `updateNotes(Prescription $p, string $notes): void`, `cancel(Prescription $p, CancellationReasonCategory $cat, string $reason, User $by): void`
- [x] T051 [US1] Criar `app/Domain/Prescription/Prescription/Exceptions/ControlledPrescriptionRulesException.php` e `PrescriptionImmutableException.php`
- [x] T052 [P] [US1] Implementar `app/Domain/Prescription/PrescriptionItem/MedicationAutocompleteService.php` — Q2 (autocomplete via histórico do médico no tenant, GIN trgm)
- [x] T053 [P] [US1] Implementar `app/Domain/Prescription/Report/ControlledPrescriptionMaskingService.php` — recebe Prescription + User, retorna array com campos clínicos omitidos quando não há ability

### Eventos de domínio (auto-discovered)

- [x] T054 [P] [US1] Criar evento `app/Events/Prescription/PrescricaoCriada.php` com 6 readonly props (plan §6.3 / data-model §10)
- [x] T055 [P] [US1] Criar evento `app/Events/Prescription/PrescricaoAtualizada.php` com `prescriptionId, changedFields[]`
- [x] T056 [P] [US1] Criar evento `app/Events/Prescription/PrescricaoCancelada.php` com `prescriptionId, cancelledByUserId, categoryReason, cancelledAt`
- [x] T057 [P] [US1] Criar evento `app/Events/Prescription/PrescricaoControladaVisualizada.php` com `actorUserId, prescriptionId, viewedAt, ip, userAgent` (sem snapshot — Q8c)

### Listeners (auto-discovered Laravel 13 — NÃO registrar manualmente — A6/CLAUDE.md §5)

- [x] T058 [P] [US1] Criar listener `app/Listeners/Prescription/ProjectPrescriptionToPatientTimeline.php` — consome `PrescricaoCriada`/`Cancelada`/`ReceitaRenovada` e grava em `eventos_timeline` (Fase 2)
- [x] T059 [P] [US1] Criar listener `app/Listeners/Prescription/LogControlledPrescriptionAccess.php` — consome `PrescricaoControladaVisualizada`, grava em `audit_logs` com `action='prescription.view_controlled'`

### FormRequests + Policy completa

- [x] T060 [US1] Implementar `app/Http/Requests/Prescriptions/StorePrescriptionRequest.php` — validações: `patient_id` exists tenant; `type` ∈ enum; `controlled` → `count(items)==1`; `common` → `duration_days ∈ {30,60,90,180}`; `special`/`controlled` ignoram `duration_days`; `alert_disabled` só com `type=common`; `items.*.medication_name` required string max:255; `items.*.posology` required text
- [x] T061 [P] [US1] Implementar `app/Http/Requests/Prescriptions/UpdatePrescriptionNotesRequest.php` — só `notes` editável
- [x] T062 [P] [US1] Implementar `app/Http/Requests/Prescriptions/CancelPrescriptionRequest.php` — `cancellation_reason_category` + `cancellation_reason` (≤500 chars) required
- [x] T063 [P] [US1] Implementar `app/Http/Requests/Prescriptions/ListPrescriptionsRequest.php` — filtros `status`, `type`, `patient_id`, `professional_id`, `expires_after`, `expires_before`, `cursor`
- [x] T064 [P] [US1] Implementar `app/Http/Requests/Prescriptions/UploadPrescriptionPdfRequest.php` — `pdf: required|file|mimes:pdf|max:10240`
- [x] T065 [US1] Completar `app/Policies/PrescriptionPolicy.php` — `view` (qualquer com `prescription.view`), `viewControlled` (emissor OR admin com `prescription.view_controlled`), `create`, `update` (só emissor), `cancel` (emissor OR admin), `export`

### Resources (mascaramento aplicado no resource)

- [x] T066 [US1] Implementar `app/Http/Resources/Prescriptions/PrescriptionResource.php` — chama `ControlledPrescriptionMaskingService` quando user não pode `viewControlled`; emite `PrescricaoControladaVisualizada` ao serializar com conteúdo completo (ponto único — R-7P-11)
- [x] T067 [P] [US1] Implementar `app/Http/Resources/Prescriptions/PrescriptionItemResource.php`

### Controllers + rotas

- [x] T068 [US1] Implementar `app/Http/Controllers/Api/V1/Prescriptions/PrescriptionController.php` — `index`, `store`, `show`, `update`, `cancel` (POST /prescriptions/{id}/cancel)
- [x] T069 [US1] Implementar `app/Http/Controllers/Api/V1/Prescriptions/PrescriptionPdfController.php` — `upload` (POST), `download` (GET com URL assinada TTL 15min — R-7P-08)
- [x] T070 [US1] Adicionar rotas em `routes/api.php` sob `prefix=api/v1` + middleware `auth:sanctum`, `tenant.slug`, `prescription.module` (5 rotas de CRUD + 2 de PDF)

### PDF async + S3

- [x] T071 [US1] Implementar `app/Jobs/Prescription/PrescriptionPdfUploadJob.php` — recebe `prescription_id` + `s3_key` temporário; copia para `prescriptions/{tenant_id}/{prescription_id}/v{version}.pdf`; atualiza `pdf_path` + `pdf_version` + emite `PrescricaoAtualizada{changed_fields=[pdf_path]}`; em caso de retry, mantém versão anterior intacta
- [x] T072 [US1] Implementar `app/Domain/Prescription/Pdf/PrescriptionPdfStorage.php` — wrapper sobre S3 disk com path por tenant (`prescriptions/{tid}/{pid}/`)
- [x] T073 [P] [US1] Implementar `app/Domain/Prescription/Pdf/PrescriptionPdfVersioningService.php` — gera próximo número de versão; arquiva versão atual antes de upload nova
- [x] T074 [P] [US1] Implementar `app/Domain/Prescription/Pdf/PrescriptionSignedUrlService.php` — `Storage::disk('prescriptions')->temporaryUrl($path, now()->addMinutes(15))` + audit log de emissão (FR-033)

### Frontend Vue — listagem + cadastro + show

- [x] T075 [P] [US1] Criar `resources/js/lib/prescriptionsApi.js` com chamadas REST (list/get/create/update/cancel/uploadPdf/downloadPdf)
- [x] T076 [P] [US1] Criar `resources/js/stores/prescriptionsStore.js` (Pinia) com state, getters e actions
- [x] T077 [P] [US1] Criar `resources/js/composables/usePrescriptionFilters.js` para filtros reutilizáveis
- [x] T078 [P] [US1] Criar componentes `resources/js/components/prescriptions/PrescriptionTypeBadge.vue`, `PrescriptionStatusPill.vue`, `ControlledMaskingBanner.vue`
- [x] T079 [US1] Criar `resources/js/components/prescriptions/PrescriptionFormItems.vue` — lista dinâmica 1-10 (1 fixo para `controlled`), com autocomplete via `MedicationAutocompleteService`
- [x] T080 [US1] Criar `resources/js/components/prescriptions/PrescriptionPdfUploader.vue` — async com progress (segue padrão Fase 6 — toast local + a11y)
- [x] T081 [P] [US1] Criar `resources/js/components/prescriptions/PrescriptionCancelModal.vue` — modal a11y (Teleport + focus trap + Esc — padrão Fase 6) com `cancellation_reason_category` + textarea
- [x] T082 [US1] Criar `resources/js/pages/prescriptions/PrescriptionsListPage.vue` — listagem paginada com filtros, badge de tipo/status, indicador de criticidade
- [x] T083 [US1] Criar `resources/js/pages/prescriptions/PrescriptionCreatePage.vue` — seleção paciente, tipo, presets de duração (radio `{30,60,90,180}` para `common`, fixo 30d para `special`/`controlled`), formulário de items
- [x] T084 [US1] Criar `resources/js/pages/prescriptions/PrescriptionShowPage.vue` — detalhe + botão cancelar + botão renovar (placeholder até US-8.3) + uploader PDF

**Checkpoint US-8.1**: Médico cadastra/lista/cancela receita; mascaramento funciona em 5 perfis; PDF async não bloqueia; timeline do paciente atualiza. 13 testes de feature + 2 unit verdes.

---

## Phase 4: US-8.2 Alerta de Vencimento (P1) (Lote C)

**Goal**: Sistema dispara alertas D-15/D-7/D-1 antes do vencimento via serviço de mensageria Fase 3, com idempotência por `(prescription_id, alert_type)`, debounce 4h e cancelamento de cadência em cancel/renovação.

**Independent Test**: criar receita com 8d de validade → apenas D-7 e D-1 (D-15 `skipped`); avançar relógio → alerta dispara; cancelar receita → alerta D-1 não dispara.

### Tests for US-8.2 ⚠️

- [ ] T085 [P] [US2] Teste `tests/Feature/Prescription/PrescriptionAlertCadenceTest.php` — AC-8.2.1 (3 checkpoints materializados na criação; cada um dispara 1 evento)
- [ ] T086 [P] [US2] Teste `tests/Feature/Prescription/PrescriptionAlertIdempotencyTest.php` — ⭐ Gate idempotência: dispara `ProcessPrescriptionAlertsJob` 2x consecutivamente → apenas 1 row em `prescription_alerts` é atualizada para `dispatched` (lock Redis + UNIQUE DB)
- [ ] T087 [P] [US2] Teste `tests/Feature/Prescription/PrescriptionEventPayloadLgpdTest.php` — ⭐ Gate LGPD: reflection valida que `ReceitaProximaDoVencimento` tem exatamente 7 props (research §3); detecta novos campos como falha
- [ ] T088 [P] [US2] Teste `tests/Feature/Prescription/PrescriptionAlertChannelTest.php` — ⭐ Gate Conformidade Meta: sem template HSM → status `blocked_no_template` + tarefa Inbox; com template + fora janela 24h → usa HSM correto
- [ ] T089 [P] [US2] Teste `tests/Feature/Prescription/PrescriptionAlertSkipTest.php` — AC-8.2.4 (receita criada com 5d restantes → D-15 e D-7 `skipped` com `skip_reason='checkpoint_past_at_creation'`)
- [ ] T090 [P] [US2] Teste `tests/Feature/Prescription/PrescriptionAlertCancellationOnCancelTest.php` — AC-8.2.3 (cancelar receita → alertas `pending` transitam para `cancelled`)
- [ ] T091 [P] [US2] Teste `tests/Feature/Prescription/PrescriptionAlertDisableTest.php` — AC-8.2.2 (PATCH `alert_disabled=true` em `special`/`controlled` → 422; em `common` → OK)
- [ ] T092 [P] [US2] Teste `tests/Feature/Prescription/PrescriptionAlertOptOutTest.php` — AC-8.2.8 (paciente com opt-out → evento emitido, envio externo suprimido, `skip_reason='recipient_opted_out'`)
- [ ] T093 [P] [US2] Teste `tests/Feature/Prescription/PrescriptionAlertDebounceTest.php` — AC-8.2.6 (3 receitas vencendo → 3 mensagens separadas com 4h entre disparos)
- [ ] T094 [P] [US2] Teste `tests/Feature/Prescription/PrescriptionExpirationTest.php` — AC-8.2.7 (cron `expire-active` emite `ReceitaVencida` exatamente 1x quando `expires_at < today`)
- [ ] T095 [P] [US2] Teste `tests/Unit/Prescription/PrescriptionAlertIdempotencyKeyTest.php` — helper de chave Redis

### Domain services

- [ ] T096 [US2] Implementar `app/Domain/Prescription/Alert/PrescriptionAlertSchedulerService.php` — `scheduleFor(Prescription $p): Collection<PrescriptionAlert>` materializa 3 alerts; aplica `skipped` para checkpoints passados (Q4a); pula completamente para `alert_disabled=true` em `common`
- [ ] T097 [US2] Implementar evento `app/Events/Prescription/ReceitaProximaDoVencimento.php` — readonly props da allowlist (7 campos exatos — Q5/research §3); implementa `ContainsNoClinicalData`
- [ ] T098 [P] [US2] Implementar evento `app/Events/Prescription/ReceitaVencida.php` com `prescriptionId, patientId, expiredAt`

### Listeners (auto-discovered)

- [ ] T099 [P] [US2] Criar listener `app/Listeners/Prescription/CancelAlertScheduleOnCancellation.php` — consome `PrescricaoCancelada`, transita alerts `pending`/`dispatched` futuros para `cancelled`
- [ ] T100 [P] [US2] Criar listener `app/Listeners/Prescription/DispatchPrescriptionAlertViaMessaging.php` — consome `ReceitaProximaDoVencimento`, invoca serviço de mensageria Fase 3 com template HSM `prescription.expiry_warning_{15d|7d|1d}`; aplica debounce 4h por destinatário; fallback Inbox em `blocked_no_*`
- [ ] T101 [P] [US2] Criar listener `app/Listeners/Prescription/BroadcastPrescriptionExpiryToReport.php` — consome `ReceitaProximaDoVencimento` e faz broadcast no canal Reverb `prescriptions.{tenant_id}` (research §7.3)

### Jobs + commands

- [ ] T102 [US2] Implementar `app/Jobs/Prescription/ProcessPrescriptionAlertsJob.php` — chunk de 1.000 receitas com `expires_at` em today + {15,7,1}; aplica lock Redis `prescription_alert:{pid}:{type}:{date}` TTL 25h (research §4); dispara `DispatchPrescriptionAlertJob` por alerta elegível; métrica `prescription_alerts_processed_total`
- [ ] T103 [US2] Implementar `app/Jobs/Prescription/DispatchPrescriptionAlertJob.php` — fila `prescription-alerts`; emite `ReceitaProximaDoVencimento`; atualiza alert para `dispatched`/`blocked_*`/`failed`
- [ ] T104 [US2] Implementar `app/Jobs/Prescription/ExpireActivePrescriptionsJob.php` — escaneia receitas `active` com `expires_at < today` e emite `ReceitaVencida` (idempotência via update condicional)
- [ ] T105 [P] [US2] Implementar `app/Console/Commands/PrescriptionsProcessAlertsCommand.php` — wrapper que dispara `ProcessPrescriptionAlertsJob` + opção `--retry-blocked`
- [ ] T106 [P] [US2] Implementar `app/Console/Commands/PrescriptionsExpireActiveCommand.php` — wrapper para `ExpireActivePrescriptionsJob`

### Schedule + canal Reverb

- [ ] T107 [US2] Adicionar schedule em `routes/console.php`: `prescriptions:process-alerts` daily 06:00 BRT + `prescriptions:expire-active` daily 00:30 BRT + `withoutOverlapping()` (plan §C3)
- [ ] T108 [US2] Adicionar canal privado `prescriptions.{tenantId}` em `routes/channels.php` com auth callback `User->tenant_id === $tenantId` (research §7.3 — R-7P-04)

### FormRequest + Controller alertas

- [ ] T109 [P] [US2] Implementar `app/Http/Requests/Prescriptions/UpdateAlertConfigRequest.php` — `alert_disabled: boolean`; rejeita em `special`/`controlled`
- [ ] T110 [P] [US2] Implementar `app/Http/Controllers/Api/V1/Prescriptions/PrescriptionAlertConfigController.php` — `index` (lista alertas de uma receita), `update` (PATCH /prescriptions/{id}/alert-config)
- [ ] T111 [P] [US2] Implementar `app/Http/Resources/Prescriptions/PrescriptionAlertResource.php`
- [ ] T112 [US2] Adicionar rotas `/api/v1/prescription-alerts` e `/api/v1/prescriptions/{id}/alert-config` em `routes/api.php`

### Métricas + Sentry tags

- [ ] T113 [US2] Adicionar em `PrescriptionMetrics`: `prescription_alerts_dispatched_total{tenant,alert_step,status}`, `prescription_alerts_blocked_total{reason,tenant}` (incrementar no listener `DispatchPrescriptionAlertViaMessaging`)
- [ ] T114 [P] [US2] Adicionar Sentry tags `prescription.id`, `prescription.type`, `alert.type` no boot de jobs e listeners

### Frontend — toggle de alerta + visualização

- [ ] T115 [P] [US2] Criar `resources/js/components/prescriptions/PrescriptionAlertConfigToggle.vue` — switch habilitado só para `type=common`; desabilitado com tooltip "Alerta obrigatório por Portaria 344/98" para outros tipos
- [ ] T116 [US2] Integrar `PrescriptionAlertConfigToggle` em `PrescriptionShowPage.vue` (refresh em tempo real via canal Reverb)
- [ ] T117 [P] [US2] Adicionar listener Echo no Pinia store para `ReceitaProximaDoVencimento` → re-fetch da receita afetada na lista

### Integração mensageria Fase 3

- [ ] T118 [US2] Em `DispatchPrescriptionAlertViaMessaging` — invocar `MessagingDispatchService` (Fase 3) com identificador HSM `prescription.expiry_warning_{Nd}` + `recipient.communication_preferences` lookup; respeitar opt-out (FR-017)
- [ ] T119 [US2] Implementar fallback de Inbox manual em `DispatchPrescriptionAlertViaMessaging` quando `blocked_no_template`/`blocked_no_channel` — criar `InboxTask` (Fase 3) com motivo "Template HSM ausente — alerta de vencimento de receita não enviado a {paciente}"
- [ ] T120 [US2] Implementar debounce 4h em `MessagingDispatchService::dispatchPrescriptionAlert()` — cache Redis `messaging_debounce:{recipient_id}:{type}` TTL 4h (FR-016/Q4d)

### Listener para preferências do paciente

- [ ] T121 [P] [US2] Adicionar suporte a opt-out: em `DispatchPrescriptionAlertViaMessaging`, ler `patient.communication_preferences.opt_out_renewal_reminders` e marcar `skip_reason='recipient_opted_out'`; ainda emitir evento interno
- [ ] T122 [US2] Verificar via teste de integração com `RegistraEventoTimelineListener` (Fase 2) que alertas **não** poluem a timeline (Q11) — apenas `PrescricaoCriada`/`Cancelada`/`Renovada` viram itens

**Checkpoint US-8.2**: Cadência completa funciona; idempotência verde; opt-out e debounce respeitados; canal Reverb broadcasta refresh do relatório.

---

## Phase 5: US-8.3 Renovação via IA (P2) (Lote D)

**Goal**: Publicar contrato pseudonimizado para a IA Matricial (fase futura) + endpoint de contexto + tabela de junção `prescription_renewals` + listener que notifica médico via Inbox interna (Q13).

**Independent Test**: stub de IA chama `GET /ai/prescriptions/{id}/context` → recebe 7 campos sem PII clínica; POST `/prescriptions/{id}/renew` com `initiated_by=ai, appointment_id=X` → emite `RenovacaoSolicitadaPelaIA` + tarefa Inbox para médico emissor.

### Tests for US-8.3 ⚠️

- [ ] T123 [P] [US3] Teste `tests/Feature/Prescription/PrescriptionAiContextEndpointTest.php` — AC-8.3.1 (payload tem exatamente 7 campos da allowlist; sem `medication_name`/`posology`/`notes` — mesmo para `common`/`Q5`)
- [ ] T124 [P] [US3] Teste `tests/Feature/Prescription/PrescriptionRenewalTest.php` — AC-8.3.2 (POST /renew cria row em `prescription_renewals`; vincula `appointment_id`; emite `RenovacaoSolicitadaPelaIA`)
- [ ] T125 [P] [US3] Teste `tests/Feature/Prescription/PrescriptionAiRenewalNotifiesDoctorTest.php` — AC-8.3.7 / Q13 (listener `EnqueueInboxTaskOnAiRenewal` cria item de tarefa na Inbox Fase 3 para o médico emissor)
- [ ] T126 [P] [US3] Teste `tests/Feature/Prescription/PrescriptionRenewalStateGuardTest.php` — AC-8.3.6 (tentativa de IA renovar receita já cancelada/renovada retorna 422 `prescription_not_eligible_for_renewal`)
- [ ] T127 [P] [US3] Teste `tests/Feature/Prescription/PrescriptionRenewedChainTest.php` — emissão de `ReceitaRenovada` quando nova receita é criada com `renewed_from_id`; receita antiga transita para `status='superseded'`; cadência de alerta da antiga é cancelada (`CancelAlertScheduleOnRenewal`)
- [ ] T128 [P] [US3] Teste `tests/Feature/Prescription/PrescriptionAiContextAuthorizationTest.php` — Cenário 1 quickstart passo 3: Atendente acessa `/ai/prescriptions/{id}/context` → 403 + métrica `prescription_controlled_access_denied_total` incrementa

### Domain — renewal aggregate

- [ ] T129 [US3] Implementar `app/Domain/Prescription/Renewal/PrescriptionRenewalPolicyService.php` — `canRenew(Prescription $p): bool` (receita `active`, dentro de janela D-30, não já renovada)
- [ ] T130 [US3] Implementar `app/Domain/Prescription/Renewal/RenewPrescriptionService.php` — `initiate(Prescription $original, InitiatedByType $by, ?int $appointmentId, ?int $userId): PrescriptionRenewal` cria row + emite evento; `complete(PrescriptionRenewal $r, Prescription $newPrescription): void` popula `renewed_prescription_id` + emite `ReceitaRenovada` + transita original para `superseded`

### Eventos + Listeners

- [ ] T131 [P] [US3] Criar evento `app/Events/Prescription/RenovacaoSolicitadaPelaIA.php` implements `ContainsNoClinicalData` — `prescriptionId, patientId, professionalId, appointmentId`
- [ ] T132 [P] [US3] Criar evento `app/Events/Prescription/ReceitaRenovada.php` — `oldPrescriptionId, newPrescriptionId, renewedAt`
- [ ] T133 [P] [US3] Criar listener `app/Listeners/Prescription/EnqueueInboxTaskOnAiRenewal.php` — consome `RenovacaoSolicitadaPelaIA`, cria `InboxTask` (Fase 3) "Renovação agendada pela IA — paciente {nome}" para `prescription.professional_id`
- [ ] T134 [P] [US3] Criar listener `app/Listeners/Prescription/CancelAlertScheduleOnRenewal.php` — consome `ReceitaRenovada`, transita alerts `pending` da receita original para `cancelled`

### Controllers + endpoints

- [ ] T135 [US3] Implementar `app/Http/Controllers/Api/V1/Prescriptions/PrescriptionRenewalController.php` — `store` (POST /prescriptions/{id}/renew)
- [ ] T136 [US3] Implementar `app/Http/Controllers/Api/V1/Prescriptions/PrescriptionContextForAiController.php` — `show` (GET /ai/prescriptions/{id}/context); restrito a token de sistema (ability `prescription.ai_context`); valida que a receita está em janela D-15/7/1; retorna `PrescriptionForAiResource`
- [ ] T137 [P] [US3] Implementar `app/Http/Requests/Prescriptions/RenewPrescriptionRequest.php` — `initiated_by ∈ {professional, ai, patient}`, `appointment_id` opcional, validação cross-tenant
- [ ] T138 [P] [US3] Implementar `app/Http/Resources/Prescriptions/PrescriptionRenewalResource.php`
- [ ] T139 [P] [US3] Implementar `app/Http/Resources/Prescriptions/PrescriptionForAiResource.php` — projeção dos 7 campos exatos; chamado APENAS pelo endpoint AI (não reutilizado em outros lugares)
- [ ] T140 [US3] Adicionar rotas em `routes/api.php`: `POST /api/v1/prescriptions/{id}/renew` + `GET /api/v1/ai/prescriptions/{id}/context` (middleware extra `ability:prescription.ai_context` para o endpoint AI)

### Seed da ability `prescription.ai_context`

- [ ] T141 [US3] Adicionar ability `prescription.ai_context` em `PrescriptionPermissionsSeeder` (token de sistema/IA somente) + atualização da seed migration T012

### `StorePrescriptionRequest` reconhece `renewed_from_id`

- [ ] T142 [US3] Atualizar `StorePrescriptionRequest` (T060) para aceitar `renewed_from_id` opcional; service `PrescriptionService::create()` chama `RenewPrescriptionService::complete()` quando preenchido

### Frontend — botão renovar + página dedicada

- [ ] T143 [P] [US3] Criar `resources/js/pages/prescriptions/PrescriptionRenewPage.vue` — pré-preenche formulário com dados da original + permite editar; submete `POST /prescriptions` com `renewed_from_id` setado
- [ ] T144 [US3] Habilitar botão "Renovar esta receita" em `PrescriptionShowPage.vue` quando `policy.canRenew && status==='active'`
- [ ] T145 [P] [US3] Criar componente `resources/js/components/prescriptions/PrescriptionRenewalLink.vue` — exibido na agenda (Fase 5) quando `appointment.prescription_id` setado: "Renovação de receita — ref. Receita #N de DD/MM/AAAA"

### Integração com Fase 5 (agenda) — sem alter retroativo

- [ ] T146 [US3] Em `PrescriptionRenewalController::store`, validar que `appointment_id` (se passado) é do mesmo tenant + pertence à agenda; armazenar somente em `prescription_renewals.appointment_id` (sem tocar em `appointments`) — convenção C7 do plan
- [ ] T147 [US3] Criar query helper `app/Domain/Prescription/Renewal/AppointmentRenewalLookupService.php` — método `findRenewalByAppointment(int $appointmentId): ?PrescriptionRenewal` para a Fase 5 (e UI da agenda) descobrir a receita original a partir do `appointment_id`

### Métricas

- [ ] T148 [P] [US3] Adicionar em `PrescriptionMetrics`: `prescription_renewals_initiated_total{initiated_by,tenant}`, `prescription_renewal_conversion_rate` (gauge atualizada por cron diário)
- [ ] T149 [P] [US3] Adicionar log estruturado em `RenewPrescriptionService` (Pail-compatible) para investigação de funil "alerta → renovação"

**Checkpoint US-8.3**: stub de IA consegue obter contexto pseudonimizado; renovação materializa em `prescription_renewals`; médico emissor recebe tarefa Inbox; cadência da receita original cancelada.

---

## Phase 6: US-8.4 Relatório de Receitas (P2) (Lote E — parte 1)

**Goal**: Relatório paginado com 5 filtros (status, tipo, profissional, paciente, faixa de vencimento) + export CSV respeitando abilities + cross-tenant 404 + p95 ≤ 1,5s.

**Independent Test**: Admin Clínica consulta com filtros AND, CSV gerado respeita mascaramento de controladas para Atendente.

### Tests for US-8.4 ⚠️

- [ ] T150 [P] [US4] Teste `tests/Feature/Prescription/PrescriptionReportTest.php` — AC-8.4.1, AC-8.4.3, AC-8.4.4, AC-8.4.8 (paginação, filtros combinados AND, definição de "renovada", janela 30d default)
- [ ] T151 [P] [US4] Teste `tests/Feature/Prescription/PrescriptionReportCriticalityTest.php` — indicador verde/amarelo/vermelho baseado em proximidade do vencimento
- [ ] T152 [P] [US4] Teste `tests/Feature/Prescription/PrescriptionReportMaskingTest.php` — AC-8.4.2 (controladas mascaradas para Atendente/Recepcionista/médico não-emissor)
- [ ] T153 [P] [US4] Teste `tests/Feature/Prescription/PrescriptionCsvExportTest.php` — AC-8.4.5, AC-8.4.7 (CSV respeita ability; prefixo `CONFIDENCIAL_` quando contém ≥1 controlada; audit log com lista de IDs exportados)
- [ ] T154 [P] [US4] Teste `tests/Feature/Prescription/PrescriptionReportPerformanceTest.php` — NFR-002/SC-007 (50k receitas → p95 ≤ 1,5s na primeira página; benchmark com `microtime(true)` × 10 iterations)
- [ ] T155 [P] [US4] Teste `tests/Feature/Prescription/PrescriptionReportCrossTenantTest.php` — AC-8.4.9 (cross-tenant retorna 404, audit `cross_tenant_attempt`)

### Domain — report service

- [ ] T156 [US4] Implementar `app/Domain/Prescription/Report/PrescriptionReportService.php` — `paginate(ListPrescriptionsRequest, User): CursorPaginator` com filtros AND, ordenação default por `expires_at ASC`, índices compostos da data-model
- [ ] T157 [US4] Implementar `app/Domain/Prescription/Report/PrescriptionCsvExporter.php` — gera CSV via stream; chama `ControlledPrescriptionMaskingService` por linha; cabeçalho com `CONFIDENCIAL_` quando ≥1 controlada; grava audit log com lista de IDs

### Controllers + endpoints

- [ ] T158 [US4] Implementar `app/Http/Controllers/Api/V1/Prescriptions/PrescriptionReportController.php` — `index` (GET /prescription-reports)
- [ ] T159 [US4] Implementar `app/Http/Controllers/Api/V1/Prescriptions/PrescriptionCsvExportController.php` — `export` (GET /prescription-reports/export) com streamed response + audit
- [ ] T160 [P] [US4] Implementar `app/Http/Requests/Prescriptions/ExportPrescriptionsCsvRequest.php` — filtros + valida ability `prescription.export`
- [ ] T161 [P] [US4] Implementar `app/Http/Resources/Prescriptions/PrescriptionReportRowResource.php` — adiciona `criticality` enum verde/amarelo/vermelho
- [ ] T162 [US4] Adicionar rotas `GET /api/v1/prescription-reports` + `GET /api/v1/prescription-reports/export` em `routes/api.php`

### Frontend — relatório

- [ ] T163 [P] [US4] Criar componentes filtros: `resources/js/components/prescriptions/PrescriptionReportFilters.vue` (status, tipo, profissional, paciente, janela)
- [ ] T164 [US4] Criar `resources/js/pages/prescriptions/PrescriptionsReportPage.vue` — tabela paginada cursor-based; botão "Exportar CSV" (download streamed); indicador de criticidade colorido; refresh em tempo real via canal Reverb
- [ ] T165 [P] [US4] Adicionar listener Echo em `PrescriptionsReportPage` para `ReceitaProximaDoVencimento` → atualiza linha sem recarregar
- [ ] T166 [P] [US4] Estender `prescriptionsApi.js` com métodos `getReport(filters)`, `exportCsv(filters)`

### Performance — índices + benchmark

- [ ] T167 [US4] Confirmar que os 6 índices da data-model `idx_prescriptions_*` foram criados (T006); rodar `EXPLAIN ANALYZE` sobre query típica do relatório com 50k rows seedados via factory (`PrescriptionFactory::factory()->count(50000)`)
- [ ] T168 [US4] Implementar cursor pagination usando `latest('expires_at')->cursorPaginate(50)` em `PrescriptionReportService` (não offset — performance em tenants grandes)

---

## Phase 7: Polish & Cross-Cutting (Lote E — parte 2)

**Purpose**: Filament super admin, métricas finais, jobs de manutenção, quickstart E2E, regression gate da suite completa.

### Filament super admin (suporte e auditoria)

- [ ] T169 Implementar `app/Filament/Resources/PrescriptionResource.php` — Resource no painel super admin (cookie session em `crm.com.br`); ação "Ver detalhes" registra audit `super_admin.prescription.viewed`; sem ação de edição (somente leitura) — research §7.4 / R-7P-05
- [ ] T170 [P] Implementar `app/Filament/Resources/PrescriptionResource/Pages/ListPrescriptions.php` com filtros por tenant
- [ ] T171 [P] Implementar `app/Filament/Resources/PrescriptionResource/Pages/ViewPrescription.php` (read-only)

### Jobs de manutenção

- [ ] T172 Implementar `app/Jobs/Prescription/PurgeOldPrescriptionPdfVersionsJob.php` — mantém últimas 5 versões por receita; preserva todas as versões para `type=controlled` (research §2)
- [ ] T173 [P] Criar `app/Console/Commands/PrescriptionsPurgeOldPdfVersionsCommand.php` + schedule em `routes/console.php` semanal `weeklyOn(1, '02:00')` BRT

### Métricas finais + observabilidade

- [ ] T174 [P] Adicionar em `PrescriptionMetrics`: `prescription_pdfs_uploaded_total{status}`, `prescription_signed_urls_emitted_total{tenant}`
- [ ] T175 [P] Adicionar dashboard Grafana / docs Prometheus em `docs/observability/prescriptions-metrics.md` (DEFERRED se grafana ainda não está em pipeline)
- [ ] T176 [P] Adicionar Sentry transaction tracing para `PrescriptionReportService::paginate` e `ProcessPrescriptionAlertsJob` (sample rate 100% em staging, 10% em produção)

### Documentação API

- [ ] T177 [P] Gerar Postman collection `docs/api/Paciente360-Prescriptions-Fase7.postman_collection.json` com 1 request por endpoint do OpenAPI
- [ ] T178 [P] Atualizar `CLAUDE.md` na seção "Active feature" → mover 007 para "Previous features delivered" com sumário (5 lotes A-E, X tarefas, Y tests verdes)

### Quickstart E2E + smoke

- [ ] T179 Executar quickstart Cenário 1 (acesso indevido a controlada) em staging — registrar evidência em `docs/qa/smoke-fase7-prescriptions.md`
- [ ] T180 Executar quickstart Cenário 2 (8 dias → D-7+D-1, renovação IA, cadência cancelada) em staging
- [ ] T181 Executar quickstart Cenário 3 (substituição de PDF preserva v0) em staging
- [ ] T182 Executar quickstart Cenário 4 (cross-tenant 404) em staging
- [ ] T183 Executar quickstart Cenário 5 (HSM ausente bloqueia + cai Inbox) em staging
- [ ] T184 Executar benchmark de performance do relatório (50k rows, 10 iterações, mediana) — anexar em `docs/qa/smoke-fase7-prescriptions.md`

### Limpeza + regression gate

- [ ] T185 [P] Rodar `vendor/bin/sail bin pint --dirty --format agent` em todos os arquivos modificados
- [ ] T186 [P] Rodar suite completa: `vendor/bin/sail artisan test --compact` — garantir **zero regressão** vs Fase 6 (baseline 1167 tests / 1164 passed)
- [ ] T187 Validar que **6 gates obrigatórios** (plan §3) estão verdes: `ControlledPrescriptionAccessTest`, `ControlledPrescriptionRegulatoryTest`, `PrescriptionAlertIdempotencyTest`, `PrescriptionEventPayloadLgpdTest`, `CrossTenantPrescriptionTest`, `PrescriptionAlertChannelTest`
- [ ] T188 [P] Rodar `vendor/bin/sail npm run build` e verificar que não há erros de build/types em componentes Vue
- [ ] T189 [P] Aplicar atualizações do spec listadas em plan §11 (TTL URL assinada 15min, `prescription_renewals.appointment_id` em vez de `appointments.prescription_id`, retenção 5 anos com flag) — commit dedicado `docs(spec-007-refinements)`

### Métricas de segurança + alerting

- [ ] T190 Configurar alerta Sentry: `prescription_controlled_access_denied_total > 10` em 5 min para o mesmo tenant → notificação CS (research §6)
- [ ] T191 [P] Configurar alerta Sentry: `prescription_alerts_blocked_total{reason=no_template} > 5` em 1h → notificação product (template HSM precisa ser submetido)

### Cobertura + DoD checklist

- [ ] T192 [P] Verificar cobertura PHPUnit ≥ 70% sobre `app/Domain/Prescription/**` (alvo constitucional)
- [ ] T193 Validar DoD do spec §12 — marcar caixas no spec.md (commit dedicado `docs(spec-007-dod-checked)`)

### Frontend polish

- [ ] T194 [P] Auditar a11y dos modais (`PrescriptionCancelModal`, `PrescriptionPdfUploader`) com axe-core via Playwright
- [ ] T195 [P] Aplicar pattern de toast local (CLAUDE.md §11) em todas as ações de mutação (create/cancel/upload/renew)
- [ ] T196 [P] Aplicar pattern de popover inline (não `confirm()`/`prompt()`) em ações destrutivas
- [ ] T197 [P] Validar formatação pt-BR em datas (`Intl.DateTimeFormat('pt-BR')`) e valores monetários nas páginas de receita

### Constitution Check final

- [ ] T198 Verificar que zero violações constitucionais foram introduzidas — rodar `tests/Feature/Prescription/PrescriptionEventPayloadLgpdTest` + `CrossTenantPrescriptionTest` + `ControlledPrescriptionAccessTest` em CI verde
- [ ] T199 Atualizar `MEMORY.md` com entrada `Fase 7 entregue` (espelho do pattern Fase 5/6)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 Setup**: pode iniciar imediatamente
- **Phase 2 Foundational**: depende de Phase 1; **BLOQUEIA** todas as US (migrations + abilities + policy precisam existir)
- **Phase 3 (US-8.1)**: depende de Phase 2; é o MVP — pode rodar isolada
- **Phase 4 (US-8.2)**: depende de Phase 3 (precisa de receitas para alertar)
- **Phase 5 (US-8.3)**: depende de Phase 4 (consome `ReceitaProximaDoVencimento`; reusa `CancelAlertScheduleOnRenewal`)
- **Phase 6 (US-8.4)**: depende de Phase 3 (alimenta-se do estado da receita; independente de Phase 4/5)
- **Phase 7 Polish**: depende de todas as anteriores

### Paralelização dentro de cada Phase

- **Phase 2**: T013-T019 (enums) são todos `[P]`; T020-T022 são `[P]`; T027-T031 (model esqueletos) são `[P]`. Restantes têm dependência sequencial (migrations devem rodar em ordem).
- **Phase 3 testes**: T033-T044 são todos `[P]` (arquivos distintos)
- **Phase 3 factories**: T045-T048 são `[P]`
- **Phase 3 frontend**: T075-T081 podem rodar em paralelo após API estar pronta (T068-T070)
- **Phase 4 testes**: T085-T095 são todos `[P]`
- **Phase 5 testes**: T123-T128 são todos `[P]`
- **Phase 6 testes**: T150-T155 são todos `[P]`

### Cross-story Dependencies (críticas)

- **US-8.2 ← US-8.1**: `Prescription` model precisa existir antes de alertas. T085+ requer T049+.
- **US-8.3 ← US-8.1, US-8.2**: `RenewPrescriptionService` chama `CancelAlertScheduleOnRenewal` (T134) que precisa de `PrescriptionAlert`.
- **US-8.4 ← US-8.1**: relatório precisa de receitas reais — usa factories da Phase 3.

---

## Parallel Example: US-8.1 Tests (Lote B kickoff)

```bash
# Após Phase 2 (Foundational) concluída, lançar todos os testes em paralelo (escrever os specs):
Task: "tests/Feature/Prescription/PrescriptionCreationTest.php"
Task: "tests/Feature/Prescription/ControlledPrescriptionRegulatoryTest.php"
Task: "tests/Feature/Prescription/PrescriptionUpdateImmutabilityTest.php"
Task: "tests/Feature/Prescription/PrescriptionCancellationTest.php"
Task: "tests/Feature/Prescription/ControlledPrescriptionAccessTest.php"
Task: "tests/Feature/Prescription/CrossTenantPrescriptionTest.php"
Task: "tests/Feature/Prescription/PrescriptionPdfUploadAsyncTest.php"
Task: "tests/Feature/Prescription/PrescriptionPdfVersioningTest.php"

# Verificar que todos FALHAM (Princípio IV — Test-First) antes de iniciar implementação.
```

---

## Implementation Strategy

### MVP First (Lote A + Lote B)

1. Phase 1: Setup (T001-T005) — ~1h
2. Phase 2: Foundational (T006-T032) — ~1 dia
3. Phase 3: US-8.1 Cadastro (T033-T084) — ~2-3 dias
4. **STOP and VALIDATE**: médico cadastra/lista/cancela receita; controladas mascaradas; PDF async; timeline atualiza
5. Deploy/demo (módulo desabilitado por padrão via gate `tenant.settings.modules.prescriptions.enabled`)

### Incremental Delivery (lotes B → E)

1. ✅ Lote A+B (MVP) → demo P1 cadastro
2. Lote C (US-8.2) → demo P1 alertas; agenda crons; integração Fase 3
3. Lote D (US-8.3) → publica contrato IA; renovação manual + IA stub funcional
4. Lote E (US-8.4) → relatório + CSV + Filament; benchmark de performance
5. Polish → regression gate verde; quickstart E2E em staging; merge em `main`

### Parallel Team Strategy

Após Phase 2 concluída:
- Dev 1: US-8.1 (Lote B)
- Dev 2: US-8.2 (Lote C — após Dev 1 finalizar `Prescription` model)
- Dev 3: US-8.4 (Lote E — independente de US-8.2/8.3)
- Dev 4: US-8.3 (Lote D — após Dev 2 finalizar `ReceitaProximaDoVencimento`)

### Commit Strategy

Um commit ao fim de cada lote, seguindo padrão Fase 5:
- `feat(prescription-lote-a): T001-T032 — setup + foundational + 7 migrations`
- `feat(prescription-lote-b): T033-T084 — US-8.1 cadastro + mascaramento controlada + PDF async`
- `feat(prescription-lote-c): T085-T122 — US-8.2 cadência alertas + idempotência + mensageria`
- `feat(prescription-lote-d): T123-T149 — US-8.3 contrato IA + renovação`
- `feat(prescription-lote-e): T150-T199 — US-8.4 relatório + Filament + polish`

---

## Notes

- **Test-First obrigatório** (Princípio IV): todos os testes de uma phase devem ser escritos e FALHAR antes da implementação começar.
- **Auto-discovery de listeners** (Laravel 13 — Fase 5 §5): apenas type-hint do `handle()` — NÃO registrar em `AppServiceProvider`/`EventServiceProvider`. Duplica execução se manual.
- **`Sanctum::actingAs($user, ['*'])`** em testes (Fase 4 §5): preserva instância com cache Spatie carregado.
- **`expires_at` é `DATE`** (research §7.1): conversão de fuso na borda via `TimezoneResolverService` (Fase 5).
- **6 gates constitucionais** (plan §3): se algum falhar = blocker de PR. Não bypassar.
- **Spec refinements** (plan §11) devem ser aplicados em commit dedicado `docs(spec-007-refinements)` antes do merge — TTL 15min, `prescription_renewals.appointment_id`, retenção 5a com flag, métrica `controlled_access_denied`.
- **Filament super admin** (T169-T171): confirmar com CS antes de habilitar em produção (R-7P-05).
- **20 anos vs 5 anos** retenção CFM (R-7P-13): decisão jurídica antes do go-live — config `tenant.settings.prescriptions.retention_years`.

---

**FIM TASKS** — 199 tarefas em 7 phases / 5 lotes. Constitution Check: PASS 7/7. Pronto para `/speckit-analyze` (cross-artifact) ou `/speckit-implement` (executar lote a lote).
