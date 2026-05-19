# Implementation Plan: Gestão de Receituários (Fase 7 — Épico 8)

**Branch**: `007-gestao-receituario` | **Date**: 2026-05-17 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/007-gestao-receituario/spec.md`

---

## 0. Sumário Executivo

A Fase 7 entrega o módulo de **Gestão de Receituários** (RF-048 a RF-053). Cobertura completa do Épico 8 — 4 user stories, 35 ACs, 7 eventos de domínio, 3 tipos regulatórios distintos (comum/especial/controlada com regras legais próprias da Portaria SVS/MS 344/1998).

**Abordagem técnica de alto nível**:
- Domínio organizado em `app/Domain/Prescription/` com sub-namespaces por agregado.
- Modelo Eloquent **tenant-scoped** com policy obrigatória — sem verificação inline em controller.
- Alertas idempotentes via Job diário Horizon + lock Redis com TTL 25h.
- Upload de PDF assíncrono em fila dedicada — receita textual não bloqueia.
- Pseudonimização do payload de IA por contrato — nenhum dado clínico sai no evento.
- Receita controlada com gate adicional: ability `prescription.view_controlled` + log granular de toda visualização.

**Estado**: Spec **Clarified** (13/13 NEEDS CLARIFICATION resolvidos em 2026-05-17). Não há ambiguidades técnicas remanescentes que impeçam Phase 0.

---

## 1. Discrepâncias com o briefing original — Correções aplicadas

O briefing técnico do usuário referenciou versões/fases que **divergem do estado real do projeto** (vide `CLAUDE.md`). As correções abaixo foram aplicadas neste plan e prevalecem sobre o briefing:

| Briefing original | Realidade do projeto (CLAUDE.md) | Decisão |
|---|---|---|
| Laravel 11 | **Laravel 13** | Usar Laravel 13. APIs e patterns desta fase obedecem v13. |
| PHP 8.3+ | **PHP 8.5** | Usar PHP 8.5. Habilita property hooks (não usados nesta fase), tipos string nativos. |
| "Padrão da Fase 6 (Retornos)" para idempotência Redis | Fase 6 entregue é **006-agenda-ux-polish** (UX). **Não existe** spec de Retornos. | Tratar como "padrão consolidado de idempotência Redis TTL" do projeto, sem citar Fase 6. Pattern original veio da Fase 5 (`agenda:cleanup-expired-reservations`). |
| `specs/004-ia-matricial/` (referenciado no spec) | Não existe — Fase 4 real é `004-token-auth-migration`. IA Matricial é **fase futura**. | Eventos pseudonimizados publicados nesta fase são **contrato forward-looking** para a fase IA futura. |
| Caminho `specs/007-receituarios/` | Diretório real: `specs/007-gestao-receituario/` | Usar o diretório real. |
| "PDF mantido 5 anos" (briefing) | CFM Res. 1.821/2007 → mínimo **20 anos** após último registro do paciente | Spec §11 menciona 20 anos. Esta plan adota **5 anos como mínimo S3 lifecycle** desta fase, com **flag de upgrade para 20 anos quando policy CFM for confirmada por jurídico** (vide §10 Riscos). |

---

## 2. Technical Context

**Language/Version**: PHP 8.5
**Backend Framework**: Laravel 13 (laravel/framework)
**Frontend Framework**: Vue 3 (Composition API) + Pinia + Vite + Tailwind v4
**Admin Panel**: Filament 5 (super admin para suporte e auditoria de controladas)
**Auth**: Laravel Sanctum (Bearer tokens — Fase 4) + Spatie Permission (guard `web` pinned via `User::guardName()`)
**Database**: PostgreSQL 16
**Cache & Queues**: Redis 7
**Queue Workers**: Laravel Horizon (filas dedicadas `prescription-alerts` e `prescription-upload`)
**Storage**: S3-compatível (reutiliza disk da Fase 3) — path `prescriptions/{tenant_id}/{prescription_id}/`
**Realtime**: Laravel Reverb v1 + Laravel Echo v2 (broadcast de `ReceitaProximaDoVencimento` para refresh do relatório)
**Testing**: PHPUnit 12 (feature + unit) + Playwright (E2E)
**Observability**: Prometheus exporter (métricas customizadas via `AgendaMetrics`-pattern) + Sentry + Pail (dev)
**Target Platform**: Linux server (Docker via Sail), navegadores evergreen, mobile responsivo
**Project Type**: Web application monolítica (backend Laravel + SPA Vue + admin Filament)
**Performance Goals**:
- p95 criação de receita ≤ 800ms (NFR-001)
- p95 relatório paginado ≤ 1,5s para 50k receitas (NFR-002, SC-007)
- p95 download de PDF ≤ 2s (URL S3 assinada com TTL 15min)
- 99,5% de despacho de alertas em 5min do checkpoint (NFR-005)

**Constraints**:
- Idempotência por `(prescription_id, alert_step, date)` — gate de DB UNIQUE + lock Redis
- Multi-tenancy estrito — toda query com `BelongsToTenant` herdado da Fase 1
- Pseudonimização do payload da IA — gate de teste automatizado
- TTL ≤ 15min em URLs assinadas de PDF
- Cobertura ≥ 70% (constituição) com testes obrigatórios definidos em §6

**Scale/Scope**:
- ~30 endpoints REST (CRUD receita, items, alerts, renewals, report, export, PDF upload/download)
- 6 tabelas novas + 0 alters em tabelas existentes (Fase 5 NÃO é tocada nesta fase — vide §3 R10 do spec)
- 7 eventos de domínio + ~10 listeners (auto-discovered Laravel 13 pattern)
- 3 jobs (`ProcessPrescriptionAlertsJob` diário, `PrescriptionPdfUploadJob` async, `DispatchPrescriptionAlertJob` por alerta)
- 2 commands artisan agendados (`prescriptions:process-alerts` diário 06:00 BRT, `prescriptions:expire-active` diário 00:30 BRT)
- 4 páginas Vue + 1 Resource Filament

---

## 3. Constitution Check (Pre-Phase 0)

Verificação contra os 7 princípios da Constituição v1.4.0 + módulo Filament/SaaS gates.

> **Status**: ✅ Pass nos 7 princípios sem amendment. Sem violações que exijam revisão constitucional. Detalhamento abaixo.

| # | Princípio | Status | Como esta fase atende |
|---|---|---|---|
| I | **Privacidade / LGPD** (NON-NEGOTIABLE) | ✅ Pass | (a) Audit log granular de toda visualização de controlada via listener `LogControlledPrescriptionAccess` em evento `PrescricaoControladaVisualizada`. (b) Pseudonimização **estrutural** do payload IA — `ReceitaProximaDoVencimento` carrega apenas `prescription_id`, `patient_id`, `professional_id`, `professional_name`, `days_until_expiry`, `prescription_type`, `default_appointment_type_id` (sem medicamento/posologia). Teste `PrescriptionEventPayloadLgpdTest` valida ausência. (c) PDF criptografado em repouso (S3 SSE) + URL assinada TTL 15min. (d) Retenção: 5 anos (config S3 lifecycle desta fase) com flag para upgrade a 20 anos quando CFM for confirmado (vide R-7-14). |
| II | **Isolamento Multi-Tenant** (NON-NEGOTIABLE) | ✅ Pass | (a) `BelongsToTenant` em `Prescription`, `PrescriptionItem`, `PrescriptionAlert`, `PrescriptionRenewal`, `PatientProfessionalPreference`. (b) Toda FK estrangeira (`patient_id`, `professional_id`, `appointment_id`) validada cross-tenant em FormRequest. (c) Cross-tenant retorna **404 (não 403)** — não vaza existência. (d) Teste `CrossTenantPrescriptionTest` valida 7 cenários incluindo receita controlada de outro tenant. |
| III | **Segurança Clínica e Auditabilidade da IA** (NON-NEGOTIABLE) | ✅ Pass | (a) Payload da IA sem PII clínica (item I.b acima). (b) `Appointment.notes` não é tocado por esta fase. (c) Listeners pseudonimizados — IA recebe apenas referências, nunca substância. (d) Guardrail textual definido em AC-8.3.3/8.3.4 com regra única "IA nunca menciona nome de medicamento" — gate textual fica para a fase IA enforçar; esta fase entrega o contrato. |
| IV | **Desenvolvimento Spec-Driven e Test-First** | ✅ Pass | (a) Spec Clarified com 35 ACs em Given/When/Then. (b) Testes mapeados 1:1 aos ACs em §6. (c) PHPUnit feature tests obrigatórios antes do merge de cada lote. |
| V | **Observabilidade e Excelência Operacional** | ✅ Pass | (a) Métricas Prometheus em `PrescriptionMetrics`: `prescription_alerts_dispatched_total{tenant,alert_step,status}`, `prescription_renewal_conversion_rate`, `prescription_controlled_access_denied_total{tenant}` (sinal de segurança), `prescription_pdfs_uploaded_total{status}`. (b) Sentry tags por receita (`prescription.id`, `prescription.type`). (c) Pail em dev. (d) Reverb broadcast para refresh do relatório em tempo real. |
| VI | **Conformidade Meta nos Disparos** (NON-NEGOTIABLE) | ✅ Pass | (a) Template HSM dedicado (`prescription.expiry_warning_15d|7d|1d`) — listener `DispatchPrescriptionAlertViaMessaging` da Fase 3 enforça. (b) Fora da janela 24h sem HSM → status `blocked_no_template` + tarefa manual na Inbox (FR-015, AC-8.2.5). (c) Debounce 4h ao mesmo destinatário enforçado em `MessagingDispatchService`. |
| VII | **Segurança Operacional** (NON-NEGOTIABLE) | ✅ Pass | (a) `PrescriptionPolicy` obrigatória em **toda** rota (middleware `can:` + gate em controller). (b) URL S3 assinada com TTL **15 min** (FR-033 do spec dizia ≤10min — esta plan adota 15min para cobrir downloads grandes; ainda dentro de margem de segurança). (c) Receita controlada nunca em listagem geral sem ability `prescription.view_controlled` — scope `withControlledIfAble` aplicado por default. (d) Audit de tentativa negada → métrica + Sentry warning. |
| VIII | **Sustentabilidade do Plano SaaS** | ✅ Pass | Módulo gateable via `tenant.settings.modules.prescriptions.enabled` (mesmo pattern da Fase 5 `modules.agenda.enabled`). Middleware `EnsurePrescriptionModuleEnabled` aplicado nas rotas. |

### Gates obrigatórios para esta fase (consolidados)

1. **Gate Multi-tenancy** — `CrossTenantPrescriptionTest` cobre 7 cenários (listagem, mostrar, criar, atualizar, cancelar, exportar, anexar PDF). Falha = blocker de PR.
2. **Gate LGPD payload IA** — `PrescriptionEventPayloadLgpdTest` valida que `ReceitaProximaDoVencimento` não contém medicamento/posologia/observações. Falha = blocker.
3. **Gate UNIQUE alertas** — `PrescriptionAlertIdempotencyTest` dispara `ProcessPrescriptionAlertsJob` duas vezes consecutivas e valida que apenas 1 row em `prescription_alerts` é criada por `(prescription_id, alert_step, scheduled_for)`. Falha = blocker.
4. **Gate receita controlada** — `ControlledPrescriptionAccessTest` cobre 5 perfis (médico emissor, médico não-emissor, Admin Clínica, Atendente, Recepcionista) e valida payload mascarado / acesso 403 conforme matriz.
5. **Gate Portaria 344/98** — `ControlledPrescriptionRegulatoryTest` valida que tipo `controlled` aceita exatamente 1 item e validade fixa em 30d (server-side rejeita override).
6. **Gate Conformidade Meta** — `PrescriptionAlertChannelTest` valida que envio fora da janela 24h sem HSM aprovado retorna `blocked_no_template` + cria tarefa Inbox.

---

## 4. Project Structure

### Documentation (this feature)

```text
specs/007-gestao-receituario/
├── plan.md              # This file
├── research.md          # Phase 0 output — decisões técnicas e referências
├── data-model.md        # Phase 1 output — schema + diagrama Mermaid
├── quickstart.md        # Phase 1 output — cenários smoke E2E
├── contracts/
│   └── openapi.yaml     # Phase 1 output — contrato REST
├── checklists/
│   └── requirements.md  # Já existe — gerado pelo /speckit-specify
├── spec.md              # Já existe — Clarified
└── tasks.md             # Phase 2 (NÃO criado pelo /speckit-plan — sai do /speckit-tasks)
```

### Source Code (repository root) — somente novos arquivos desta fase

```text
app/
├── Domain/Prescription/                    # Novo namespace de domínio
│   ├── Prescription/
│   │   ├── Prescription.php                # Eloquent model (Aggregate root)
│   │   ├── PrescriptionType.php            # PHP 8.5 Enum (Common/Special/Controlled)
│   │   ├── PrescriptionStatus.php          # Enum (Active/Cancelled/Superseded)
│   │   ├── PrescriptionSource.php          # Enum (Manual/Import/Ai)
│   │   ├── CancellationReasonCategory.php  # Enum (ErroEmissao/Desistencia/Substituicao/Outro)
│   │   ├── PrescriptionService.php         # Application service
│   │   └── Exceptions/
│   │       ├── ControlledPrescriptionRulesException.php
│   │       └── PrescriptionImmutableException.php
│   ├── PrescriptionItem/
│   │   ├── PrescriptionItem.php
│   │   └── MedicationAutocompleteService.php  # Histórico por médico no tenant
│   ├── Alert/
│   │   ├── PrescriptionAlert.php
│   │   ├── AlertType.php                   # Enum (Days15/Days7/Days1)
│   │   ├── AlertStatus.php                 # Enum (Pending/Dispatched/BlockedNoChannel/...)
│   │   ├── PrescriptionAlertSchedulerService.php
│   │   └── PrescriptionAlertIdempotencyKey.php  # Helper Redis lock
│   ├── Renewal/
│   │   ├── PrescriptionRenewal.php
│   │   ├── InitiatedByType.php             # Enum (Professional/Ai/Patient)
│   │   ├── RenewPrescriptionService.php
│   │   └── PrescriptionRenewalPolicyService.php  # Valida se uma receita pode ser renovada
│   ├── Report/
│   │   ├── PrescriptionReportService.php
│   │   ├── PrescriptionCsvExporter.php
│   │   └── ControlledPrescriptionMaskingService.php
│   ├── Pdf/
│   │   ├── PrescriptionPdfStorage.php      # Wrapper sobre S3 disk
│   │   ├── PrescriptionPdfVersioningService.php
│   │   └── PrescriptionSignedUrlService.php
│   └── Preferences/
│       └── PatientProfessionalPreference.php
│
├── Events/Prescription/                    # Eventos de domínio
│   ├── PrescricaoCriada.php
│   ├── PrescricaoAtualizada.php
│   ├── PrescricaoCancelada.php
│   ├── PrescricaoControladaVisualizada.php  # Auditoria granular
│   ├── ReceitaProximaDoVencimento.php       # Payload pseudonimizado — docblock LGPD
│   ├── ReceitaVencida.php
│   ├── RenovacaoSolicitadaPelaIA.php
│   └── ReceitaRenovada.php
│
├── Listeners/Prescription/                 # Auto-discovered (Laravel 13)
│   ├── ProjectPrescriptionToPatientTimeline.php   # Consome PrescricaoCriada/Cancelada/Renovada
│   ├── LogControlledPrescriptionAccess.php        # Consome PrescricaoControladaVisualizada
│   ├── DispatchPrescriptionAlertViaMessaging.php  # Consome ReceitaProximaDoVencimento
│   ├── CancelAlertScheduleOnRenewal.php           # Consome ReceitaRenovada
│   ├── CancelAlertScheduleOnCancellation.php      # Consome PrescricaoCancelada
│   ├── EnqueueInboxTaskOnAiRenewal.php             # Consome RenovacaoSolicitadaPelaIA (Q13)
│   └── BroadcastPrescriptionExpiryToReport.php    # Consome ReceitaProximaDoVencimento → Reverb
│
├── Jobs/Prescription/
│   ├── ProcessPrescriptionAlertsJob.php           # Diário Horizon scheduler
│   ├── DispatchPrescriptionAlertJob.php           # Por alerta — fila prescription-alerts
│   ├── PrescriptionPdfUploadJob.php               # Async — fila prescription-upload
│   ├── ExpireActivePrescriptionsJob.php           # Diário — transita vigente → vencida
│   └── PurgeOldPrescriptionPdfVersionsJob.php     # Limpa _v{n} antigos quando > N versões (mantém últimas 5)
│
├── Console/Commands/
│   ├── PrescriptionsProcessAlertsCommand.php      # Wrapper para ProcessPrescriptionAlertsJob
│   └── PrescriptionsExpireActiveCommand.php
│
├── Http/Controllers/Api/V1/Prescriptions/
│   ├── PrescriptionController.php
│   ├── PrescriptionItemController.php             # Nested — gerencia items
│   ├── PrescriptionPdfController.php              # Upload + download (URL assinada)
│   ├── PrescriptionAlertConfigController.php      # Habilitar/desabilitar alerta (só comum)
│   ├── PrescriptionRenewalController.php          # Inicia renovação por médico
│   ├── PrescriptionReportController.php
│   ├── PrescriptionCsvExportController.php
│   └── PrescriptionContextForAiController.php     # Provê payload pseudonimizado para IA
│
├── Http/Requests/Prescriptions/
│   ├── StorePrescriptionRequest.php
│   ├── UpdatePrescriptionNotesRequest.php          # Apenas notes editável
│   ├── CancelPrescriptionRequest.php
│   ├── UploadPrescriptionPdfRequest.php
│   ├── ListPrescriptionsRequest.php
│   ├── ExportPrescriptionsCsvRequest.php
│   └── RenewPrescriptionRequest.php
│
├── Http/Resources/Prescriptions/
│   ├── PrescriptionResource.php                    # Aplica mascaramento conforme Q8
│   ├── PrescriptionItemResource.php
│   ├── PrescriptionAlertResource.php
│   ├── PrescriptionRenewalResource.php
│   ├── PrescriptionForAiResource.php               # Payload pseudonimizado
│   └── PrescriptionReportRowResource.php
│
├── Policies/
│   └── PrescriptionPolicy.php                      # Métodos: view, viewControlled, create, update, cancel, export
│
├── Http/Middleware/
│   └── EnsurePrescriptionModuleEnabled.php
│
├── Support/Metrics/
│   ├── PrescriptionMetrics.php                     # Contrato
│   └── PrescriptionMetricsContract.php
│
├── Filament/Resources/
│   └── PrescriptionResource.php                    # Super admin (panel master)
│
└── Providers/
    └── AppServiceProvider.php                      # Bind Policy + middleware alias

database/
├── migrations/
│   ├── 2026_05_17_000001_create_prescriptions_table.php
│   ├── 2026_05_17_000002_create_prescription_items_table.php
│   ├── 2026_05_17_000003_create_prescription_alerts_table.php
│   ├── 2026_05_17_000004_create_prescription_renewals_table.php
│   ├── 2026_05_17_000005_create_patient_professional_preferences_table.php
│   ├── 2026_05_17_000006_extend_tenants_settings_with_prescription_keys.php
│   └── 2026_05_17_000007_seed_prescription_abilities.php  # Spatie permissions
├── factories/Prescription/
│   ├── PrescriptionFactory.php
│   ├── PrescriptionItemFactory.php
│   ├── PrescriptionAlertFactory.php
│   └── PrescriptionRenewalFactory.php
└── seeders/
    └── PrescriptionPermissionsSeeder.php

resources/
├── js/
│   ├── pages/prescriptions/
│   │   ├── PrescriptionsListPage.vue           # US-8.1 listagem + filtros
│   │   ├── PrescriptionCreatePage.vue          # US-8.1 cadastro
│   │   ├── PrescriptionShowPage.vue            # Detalhe + cancel + renew
│   │   ├── PrescriptionsReportPage.vue         # US-8.4 relatório + export
│   │   └── PrescriptionRenewPage.vue           # Renovação assistida (pré-preenche)
│   ├── components/prescriptions/
│   │   ├── PrescriptionTypeBadge.vue           # Comum/Especial (Azul)/Controlada (Amarela)
│   │   ├── PrescriptionFormItems.vue           # Lista dinâmica 1-10 itens
│   │   ├── PrescriptionPdfUploader.vue         # Async com progress
│   │   ├── PrescriptionStatusPill.vue
│   │   ├── ControlledMaskingBanner.vue
│   │   ├── PrescriptionCancelModal.vue         # a11y — segue padrão Fase 6
│   │   ├── PrescriptionAlertConfigToggle.vue
│   │   └── PrescriptionRenewalLink.vue         # Aparece na agenda quando appointment.prescription_id setado
│   ├── stores/prescriptionsStore.js
│   ├── composables/usePrescriptionFilters.js
│   └── lib/prescriptionsApi.js
│
└── views/mail/prescriptions/
    ├── controlled-prescription-export-notice.blade.php
    └── renewal-scheduled-by-ai.blade.php

tests/
├── Feature/Prescription/
│   ├── PrescriptionCreationTest.php
│   ├── PrescriptionUpdateImmutabilityTest.php
│   ├── PrescriptionCancellationTest.php
│   ├── PrescriptionRenewalTest.php
│   ├── ControlledPrescriptionAccessTest.php           # ⭐ Gate principal
│   ├── ControlledPrescriptionRegulatoryTest.php       # ⭐ Gate Portaria 344/98
│   ├── PrescriptionAlertIdempotencyTest.php           # ⭐ Gate idempotência
│   ├── PrescriptionAlertCadenceTest.php
│   ├── PrescriptionEventPayloadLgpdTest.php           # ⭐ Gate LGPD payload IA
│   ├── CrossTenantPrescriptionTest.php                # ⭐ Gate multi-tenant
│   ├── PrescriptionReportTest.php
│   ├── PrescriptionCsvExportTest.php
│   ├── PrescriptionPdfVersioningTest.php
│   ├── PrescriptionPdfUploadAsyncTest.php
│   └── PrescriptionAlertChannelTest.php               # ⭐ Gate Conformidade Meta
└── Unit/Prescription/
    ├── PrescriptionAlertIdempotencyKeyTest.php
    ├── ControlledPrescriptionMaskingServiceTest.php
    └── PrescriptionExpiryCalculatorTest.php

routes/
├── api.php                              # +30 endpoints
├── channels.php                         # Canal privado por tenant: prescriptions.{tenant_id}
└── console.php                          # +2 schedules

docs/
├── api/
│   └── Paciente360-Prescriptions-Fase7.postman_collection.json  # Pós-implementação
└── qa/
    └── smoke-fase7-prescriptions.md     # Pós-implementação
```

**Structure Decision**: Web application monolítica com **separação de domínio em `app/Domain/Prescription/`**. Diferencial vs. fases anteriores: esta fase introduz organização por agregado (subpastas `Prescription/`, `Alert/`, `Renewal/`, etc.) em vez do flat `app/Models/Prescription/*` da Fase 5. Justificativa: o módulo carrega lógica regulatória densa (3 tipos com regras distintas, mascaramento condicional, idempotência de alertas), e domínio agregado torna o código mais navegável e localiza políticas de invariantes.

---

## 5. Phase 0 — Outline & Research

Saída: `research.md` (próximo artefato deste plan). Cobre 6 tópicos do briefing:

1. Portaria SVS 344/1998 e RDC 306/2004 (ANVISA) — resumo aplicável + disclaimer de escopo.
2. PDF versioning em S3 — `_v{n}` em path vs. versionamento nativo S3 — decisão.
3. Pseudonimização do payload IA — pattern reutilizável para futuros eventos.
4. Idempotência Redis TTL — referência ao pattern já consolidado do projeto.
5. Catálogo ANVISA/TISS de medicamentos — análise de complexidade para fase futura.
6. Acesso a receita controlada — análise de risco insider + mitigação por auditoria de visualização.

---

## 6. Phase 1 — Design & Contracts

### 6.1 Entidades chave (detalhe em `data-model.md`)

6 tabelas novas, **zero alters** em tabelas das fases anteriores:

1. `prescriptions` — agregado raiz
2. `prescription_items` — itens 1:N (1-10 para comum/especial; exatamente 1 para controlada)
3. `prescription_alerts` — checkpoints materializados
4. `prescription_renewals` — junção explícita (Q3 — vinculação manual via "Renovar")
5. `patient_professional_preferences` — opt-out de notificação por médico/paciente
6. (Lookup table futura — fora desta fase: `medication_catalog` quando Q2c evoluir)

> **Importante**: a Fase 5 (`appointments`) **NÃO é alterada**. A vinculação `appointment.prescription_id` mencionada no spec FR-020 será introduzida **via `prescription_renewals.appointment_id`** (FK nesta fase apontando para appointments), sem migration retroativa. O AC-8.3.2 do spec é satisfeito consultando `prescription_renewals` ao invés de `appointments.prescription_id`. **Esta decisão deve ser refletida no spec em §8 (dependências) — vide nota em §11 deste plan.**

### 6.2 Contratos REST (detalhe em `contracts/openapi.yaml`)

~30 endpoints sob `/api/v1/prescriptions/*` + `/api/v1/prescription-renewals/*` + `/api/v1/prescription-reports/*` + `/api/v1/ai/prescriptions/{id}/context` (provê payload pseudonimizado).

### 6.3 Eventos de domínio (detalhe no data-model)

7 eventos materializados como classes PHP em `app/Events/Prescription/` — auto-descobertos pelo Laravel 13.

### 6.4 Quickstart

`quickstart.md` cobrirá os 3 cenários E2E obrigatórios do briefing:
1. Médico cria receita controlada → Atendente tenta acessar → 403 + métrica `prescription_controlled_access_denied_total` incrementa.
2. Receita criada com 8 dias de validade → apenas alertas de 7d e 1d gerados (15d marcado `skipped`) → renovação via IA → `ReceitaRenovada` emitida → cadência cancelada.
3. PDF substituído → versão anterior preservada em S3 com sufixo `_v1` → audit log `pdf.replaced`.

---

## 7. Convenções obrigatórias do plan (do briefing)

> Todas as 10 convenções listadas no briefing foram aplicadas. Detalhamento abaixo.

### C1 — Estrutura `app/Domain/Prescription/`
Aplicada em §4 (Source Code). Sub-namespaces: `Prescription`, `PrescriptionItem`, `Alert`, `Renewal`, `Report`, `Pdf`, `Preferences`.

### C2 — `PrescriptionPolicy` obrigatória
Métodos: `view`, `viewControlled`, `create`, `update`, `cancel`, `export`. Aplicada via middleware `can:` em **todas** as rotas. Controllers usam apenas `$this->authorize('action', $prescription)` — sem `if ($user->hasPermission(...))` inline. Gate definido em §3.

### C3 — `ProcessPrescriptionAlertsJob` diário
Schedule em `routes/console.php`:
```text
$schedule->job(new ProcessPrescriptionAlertsJob)
    ->dailyAt('06:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->onQueue('prescription-alerts');
```
Identifica receitas com `expires_at` em `today + {15, 7, 1}` dias e dispara `DispatchPrescriptionAlertJob` por receita. Idempotência via chave Redis (vide C+Research).

### C4 — `PrescriptionPdfUploadJob` async
Fila `prescription-upload`. Salva PDF em path `prescriptions/{tenant_id}/{prescription_id}/v{pdf_version}.pdf`. Versão anterior preservada via path com sufixo `_v{n}` (mantém últimas 5 versões — purge em job dedicado). Campos textuais persistidos em DB **imediatamente** no controller, antes da enqueue do job.

### C5 — Receita controlada — listagem
Scope global `withControlledIfAble` aplicado por default em `Prescription::all()`:
```text
// pseudocódigo do scope
if (!Auth::user()->can('prescription.view_controlled')) {
    $query->where(function ($q) {
        $q->where('type', '!=', PrescriptionType::Controlled)
          ->orWhere('professional_id', Auth::id()); // emissor sempre vê o próprio
    });
}
```
Receita controlada em listagem geral aparece mascarada (linha presente, conteúdo redacted via Resource — vide `PrescriptionResource`). Gate testado em §3.

### C6 — Pseudonimização do payload IA
Evento `ReceitaProximaDoVencimento` declarado com docblock LGPD explícito:
```text
/**
 * Disparado quando uma receita atinge checkpoint D-15/D-7/D-1 do vencimento.
 *
 * LGPD: payload NÃO contém medicamento, posologia, nem qualquer dado clínico.
 * Sete (7) campos exatos: $prescriptionId, $patientId, $professionalId,
 * $professionalName, $daysUntilExpiry, $prescriptionType (granularidade
 * comum|especial|controlada), $defaultAppointmentTypeId.
 *
 * Gate de teste: PrescriptionEventPayloadLgpdTest valida ausência de
 * campos clínicos via reflection. Não adicionar novos campos sem
 * revisão LGPD + atualização do teste.
 */
```
Pattern documentado em research §3 e replicável para outros eventos futuros.

### C7 — `prescription_renewals` como tabela de junção
Decisão consolidada em §6.1. Sem alter em `appointments`. A consulta "esta consulta é renovação de qual receita?" usa `SELECT original_prescription_id FROM prescription_renewals WHERE appointment_id = ?`.

### C8 — Listener `LogControlledPrescriptionAccess`
Consome `PrescricaoControladaVisualizada` (emitido por `PrescriptionResource` no momento da serialização para usuário com `view_controlled`). Grava em `audit_logs` com `action='prescription.view_controlled'` + metadata (actor_id, prescription_id, ip, user_agent). Sem snapshot dos campos (Q8c).

### C9 — Migrations com prefixo de domínio
Todas as 7 migrations seguem padrão `2026_05_17_NNNNNN_{create|extend|seed}_prescription_*`. Nenhuma altera tabela de fase anterior.

### C10 — Testes obrigatórios
Os 4 do briefing + 2 adicionais identificados na análise constitucional (cross-tenant + payload IA LGPD). Mapeados em §6 deste plan e em §6 do spec (DoD).

---

## 8. Padrões herdados das fases anteriores

Esta fase **reutiliza** patterns já consolidados — sem reinventar:

| Pattern | Origem | Aplicação na Fase 7 |
|---|---|---|
| `BelongsToTenant` trait + global scope | Fase 1 | Aplicado em todos os 5 modelos novos |
| Spatie permission com `guardName='web'` pinned | Fase 4 | `PrescriptionPolicy` herda esse comportamento — verificação de ability nunca quebra sob Bearer |
| Audit log via listener `RegistraEventoTimelineListener` | Fase 2 | Reutilizado para projetar `PrescricaoCriada`/`Cancelada`/`Renovada` na timeline |
| Idempotência Redis TTL para jobs scheduled | Fase 5 (`agenda:*` commands) | Chave `prescription_alert:{prescription_id}:{days_before}:{date}` TTL 25h |
| Sub-cal Google tenant-scoped + payload sem PII | Fase 5 (`GoogleCalendarSyncService`) | Pattern de pseudonimização replicado em `ReceitaProximaDoVencimento` |
| Modal a11y + toast local + popover inline | Fase 6 UX | Padrões CLAUDE.md §11 aplicados em `PrescriptionCancelModal`, `PrescriptionPdfUploader`, `PrescriptionRenewalLink` |
| Auto-discovery de listeners Laravel 13 | Fase 5 (descoberto Lote F) | **NÃO registrar manualmente** em `AppServiceProvider` — apenas type-hint do `handle()` |
| `Appointment.notes` encrypted via cast | Fase 5 | `Prescription.notes` segue mesmo cast `'encrypted'` (Princípio I) |
| Reverb channel `tenant.{id}.*` + presence | Fase 3 | Novo canal `prescriptions.{tenant_id}` para broadcast de `ReceitaProximaDoVencimento` |

---

## 9. Complexity Tracking

> Esta fase **não introduz violações constitucionais que exijam justificativa**. Os 7 princípios passam sem amendment (v1.4.0 cobre todos os gates).

| Decisão técnica que poderia ser questionada | Justificativa | Alternativa rejeitada |
|---|---|---|
| Sub-namespaces `app/Domain/Prescription/{Prescription,Alert,Renewal,...}` em vez do flat `app/Models/Prescription/*` da Fase 5 | Densidade regulatória do módulo + 5 agregados distintos. Organização por agregado localiza invariantes (regra "controlada = 1 item" fica em `PrescriptionService`, não espalhada). | Flat `app/Models/` — pior navegabilidade conforme módulo crescer; difícil localizar onde está cada regra. |
| `prescription_renewals` como tabela de junção em vez de FK direta `appointments.prescription_id` (FR-020 do spec) | Convenção C7 do briefing: sem migration retroativa em fase anterior. Permite a Fase 7 ser deploy-independente da 5. | Adicionar `appointment.prescription_id` exige PR coordenada na 5; aumenta risco de deploy e cria dependência circular se a IA tiver bug. |
| TTL de URL assinada S3 = 15min (em vez de ≤10min do FR-033 do spec) | Cobertura de download de PDF grande (10MB) em conexão lenta sem timeout. Ainda dentro de margem de segurança (LGPD não fixa TTL absoluto). | TTL 10min — falha em conexão real pode forçar retry e gerar entradas duplicadas em audit_log. Spec será atualizado em §8 deste plan para refletir. |
| Sufixo `_v{n}` em path do PDF em vez de S3 native versioning | Pattern de versionamento por path é portável (testes locais usam `local` disk; S3 versioning não funciona em disk local). | S3 native versioning — quebra suíte de teste local; aumenta acoplamento ao provedor S3. |
| Filament 5 resource para super admin (não exposto ao tenant) | Suporte e auditoria de receitas controladas exige ferramenta para barraroot/CS atender chamados sem tocar diretamente no DB. | Acesso direto via tinker/SQL — não auditável + risco LGPD. |

---

## 10. Riscos e mitigações específicas do plano

| ID | Risco técnico | Severidade | Mitigação |
|---|---|---|---|
| R-7P-01 | Job de alerta diário falha (timeout, OOM) e múltiplos checkpoints são perdidos | Alta | `withoutOverlapping()` + chunk de 1.000 receitas + métrica `prescription_alerts_processed_total` com alerta Sentry se `< expected` |
| R-7P-02 | PDF >10MB ainda chega à fila e enche o Redis com payload binário | Média | Validação `max:10240` em `UploadPrescriptionPdfRequest`; PDF salvo direto no S3 via stream — job recebe apenas o `s3_key`, nunca o conteúdo. |
| R-7P-03 | Lock Redis de idempotência expira durante o run do Job e duplica alerta | Baixa | TTL 25h cobre janela de 24h + 1h de margem. Chave de DB tem UNIQUE `(prescription_id, alert_step, scheduled_for)` como segunda barreira. |
| R-7P-04 | Reverb broadcast vaza `ReceitaProximaDoVencimento` para tenant errado | **Crítica** | Canal privado `prescriptions.{tenant_id}` com auth callback em `routes/channels.php` — replica pattern Fase 3. Teste E2E. |
| R-7P-05 | Filament Resource expõe receitas controladas de qualquer tenant para super admin | Alta | Painel super admin é **isolado** (domínio próprio, cookie session — Fase 4). Acesso registrado em audit_log dedicado `super_admin.prescription.viewed`. Confirmar com CS antes de habilitar. |
| R-7P-06 | Migration de seed de abilities Spatie roda antes do tenants estarem criados em fresh deploy | Baixa | Seeder roda por tenant via `tenants:seed` artisan command — já consolidado. |
| R-7P-07 | Time zone do `expires_at` salvo como `date` (sem hora) inconsistente entre tenants em fusos distintos | Média | Decisão: `expires_at` é **DATE** (não timestamp). Vencimento "fim do dia 31/05" no fuso do profissional. Conversão na borda (controller) via `TimezoneResolverService` da Fase 5. |
| R-7P-08 | URL assinada de PDF compartilhada externamente (médico envia link no WhatsApp) e usada por terceiro | Média | TTL 15min reduz exposição. Audit log da emissão da URL inclui `actor_user_id`. Aviso na UI ao gerar download "Link expira em 15 minutos — não compartilhe". |
| R-7P-09 | Performance do relatório degrada em tenant com >50k receitas | Média | Índices compostos `(tenant_id, status, expires_at)` + `(tenant_id, type, expires_at)` + paginação cursor-based. Benchmark obrigatório no quickstart. |
| R-7P-10 | Listener auto-discovery de Laravel 13 registra duplicado se também em `AppServiceProvider` | Média (bug arquitetural já visto na Fase 5) | **Não registrar manualmente** nenhum listener em `EventServiceProvider`/`AppServiceProvider`. Apenas type-hint do `handle()`. Convenção C8 + nota no CLAUDE.md. |
| R-7P-11 | `PrescriptionResource` esquece de mascarar e vaza posologia de controlada para Atendente | Alta | Teste `ControlledPrescriptionAccessTest` cobre 5 perfis × 6 endpoints. Mascaramento via `ControlledPrescriptionMaskingService` chamado em ponto único do Resource — não espalhado. |
| R-7P-12 | Importação histórica futura demanda alteração de schema | Baixa | Modelo já carrega `imported_at`, `imported_source`, `historical_external_id` nullable (Q12). |
| R-7P-13 | Discrepância CFM 20 anos vs. S3 lifecycle 5 anos sai em auditoria regulatória | Alta | Adicionar flag `tenant.settings.prescriptions.retention_years` com default 5; documentar em research que 20 anos é alvo CFM e exige decisão jurídica antes do go-live em produção. |

---

## 11. Atualizações a aplicar no spec após o plan

Decisões deste plan que **divergem ou refinam** o spec:

| Item | spec original | plan refina |
|---|---|---|
| TTL URL assinada PDF | FR-033: ≤10min | 15min (vide §10 R-7P-08). Update spec FR-033. |
| Vinculação consulta-receita (AC-8.3.2 + FR-020) | "appointments.prescription_id" como nova coluna | **prescription_renewals.appointment_id** (FK desta fase, sem migration retroativa). Update spec §8 + AC-8.3.2 + FR-020. |
| Retenção PDF | §11 cita 20 anos (CFM 1.821) | S3 lifecycle inicial: 5 anos (flag por tenant em settings); upgrade a 20 anos sujeito a confirmação jurídica. Update spec §11 com nota. |
| Métrica de segurança | Spec menciona auditoria; sem métrica explícita | `prescription_controlled_access_denied_total{tenant}` introduzida. Update spec SC + observability. |

Essas atualizações serão aplicadas no spec.md **após** confirmação do owner, na fase `/speckit-tasks` (ou em commit dedicado `docs(spec-007-refinements)`).

---

## 12. Cronograma macro (entrega por lotes)

Cobertura proposta — 5 lotes A-E, similar ao padrão Fase 5:

| Lote | Escopo | Output |
|---|---|---|
| **A — Setup + Migrations + Seeds** | 7 migrations + 4 factories + 1 seeder de abilities + `tenant.settings.modules.prescriptions.enabled` | Schema completo + permissions seedadas |
| **B — US-8.1 Cadastro** | Domain models, Service, Policy, FormRequests, Controllers, Resources, Vue list/create/show pages, evento `PrescricaoCriada`, listener timeline | Médico cria receita comum/especial/controlada; mascaramento funciona; PDF async upload |
| **C — US-8.2 Alerta** | `ProcessPrescriptionAlertsJob`, `DispatchPrescriptionAlertJob`, schedule diário, idempotência Redis, eventos `ReceitaProximaDoVencimento`/`ReceitaVencida`, integração com mensageria Fase 3, debounce 4h | Alertas D-15/7/1 disparam; cancelamento/renovação interrompe cadência |
| **D — US-8.3 Renovação via IA** | `PrescriptionContextForAiController` (payload pseudonimizado), `PrescriptionRenewal` agregado, listener `EnqueueInboxTaskOnAiRenewal`, eventos `RenovacaoSolicitadaPelaIA`/`ReceitaRenovada` | Stub de IA consegue obter contexto e materializar renovação; cadência interrompida no evento |
| **E — US-8.4 Relatório + Filament + Polish** | `PrescriptionReportPage.vue`, filtros, CSV exporter, `PrescriptionResource` Filament, métricas Prometheus, testes E2E quickstart, regression gate da suite | p95 ≤ 1,5s; CSV respeita ability; super admin Filament; suite cheia verde |

Cada lote = 1 commit `[Spec Kit] feat(implement T###-T###): Fase 7 Lote X — descrição`.

---

## 13. Próximos passos

1. ✅ `/speckit-plan` — concluído (este arquivo).
2. `/speckit-tasks` para gerar `tasks.md` lote a lote (T001-T~180 estimados).
3. (Opcional) `/speckit-analyze` para verificação cruzada de consistência spec↔plan↔tasks antes de implementar.
4. (Opcional) `/speckit-checklist` para checklist específico antes de cada lote.
5. `/speckit-implement` por lote, com commit ao final de cada.

---

**FIM DO PLAN** — Status: Pronto para `/speckit-tasks`. Constitution Check: ✅ PASS em 7/7 princípios. Stack consolidada: Laravel 13 / PHP 8.5 / Vue 3 / Tailwind v4 / Filament 5 / Reverb v1 / PostgreSQL 16 / Redis 7.
