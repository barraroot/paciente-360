# Implementation Plan: Fase 2 — CRM Core: Cadastro e Gestão de Pacientes

**Branch**: `002-crm-pacientes` | **Date**: 2026-05-11 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/002-crm-pacientes/spec.md` (Status: Clarified)

## Summary

Construir o módulo central de CRM sobre a fundação multi-tenant entregue na Fase 0. Esta fase entrega:

- **5 User Stories (US-3.1 a US-3.5)** com 42 Critérios de Aceitação numerados (AC-3.x.y).
- **Catálogo de Convênios** por tenant (CRUD) + relação `paciente ↔ convênios` (até 2 por paciente).
- **Timeline esqueleto** com 13 tipos de eventos próprios e contrato público aberto para fases futuras (mensagens, consultas, receituários) injetarem novos eventos.
- **Importação em massa CSV/Excel** via Horizon com checkpoint a cada 100 linhas e suporte a retomada.
- **Funil de Leads (Kanban)** com colunas configuráveis por tenant a partir de template padrão.
- **Tags globais** por tenant com prefixo reservado `sys:` para tags sistêmicas e normalização case+accent-insensitive.
- **Mesclagem reversível** de pacientes (30 dias) com snapshot em `MesclagemPaciente`.
- **9 abilities Spatie** (`paciente.view/create/update/delete/import/export/merge/note.write/note.view:{tipo}`) atribuídos por perfil.
- **Stub LGPD**: campo `anonimizado_em` + evento `PacienteAnonimizado` (sem UI de portabilidade nesta fase).
- **Performance**: 50.000 pacientes/tenant suportados; busca p95 < 300ms via PG `pg_trgm` (similaridade).

A abordagem técnica privilegia **reuso máximo da infra da Fase 0** (Auditable interface, listener wildcard, TenantScope global, Spatie team mode, audit trigger PG, AsJsonArray cast, ConfirmModal Vue, AuthHeroPanel) e segue os 5 princípios constitucionais não-negociáveis (LGPD, Multi-Tenant, IA-CRM-only, Spec-Driven Test-First, Observabilidade) + 7 princípios totais.

## Technical Context

**Language/Version**: PHP 8.5 (backend), JavaScript ES2023 (frontend, sem TypeScript)
**Primary Dependencies**:
- **Backend novo**: `league/csv` (parser CSV streaming, sem dependência de ext-zip/ph​pspreadsheet pesada); `phpoffice/phpspreadsheet` (parser Excel quando arquivo é `.xlsx`).
- **Backend reusado** (Fase 0): laravel/framework v13, laravel/sanctum, laravel/horizon, laravel/reverb, laravel/cashier-stripe, spatie/laravel-permission (team mode), filament/filament v5, sentry/sentry-laravel, laravel/pail.
- **Frontend reusado**: vue@^3, pinia, vue-router, vue-i18n, tailwindcss v4. **Frontend novo**: `vuedraggable@^4` (~12KB minified, baseado em SortableJS) para o Kanban — decisão Q1/A2 do `/speckit.analyze` (2026-05-11): nativo HTML5 era viável mas inconsistente em mobile/touch e exigia ~+8h de dev manual; vuedraggable resolve drag+animação+fractional indexing out-of-the-box.
- **Dev/Test**: phpunit ^12, laravel/pint, mockery/mockery, playwright (E2E já configurado).

**Storage**:
- PostgreSQL 18 (existente). **Extensão nova**: `pg_trgm` (similarity search para busca por nome/telefone). Habilitada via migration.
- Redis (cache/queue/session — existente).

**Testing**: PHPUnit 12 (feature/unit/contract); Playwright (1 jornada E2E nova para a feature).

**Target Platform**: Linux server (Sail Docker); SPA navegador moderno.

**Project Type**: Web application multi-tenant (SaaS B2B) — Laravel 13 backend + Vue 3 SPA + Filament 5 painel super admin.

**Performance Goals**:
- Cadastro manual de paciente: < 2 minutos do início ao fim (SC-001).
- Timeline (1.000 eventos): primeiro lote em p95 < 1s (SC-002).
- Importação 1.000 linhas: relatório final em até 5 minutos (SC-003).
- Drag-and-drop Kanban: < 300ms (SC-004).
- Busca por nome/telefone em 50.000 pacientes: p95 < 300ms (SC-011, FR-040).

**Constraints**:
- Multi-tenancy obrigatório em **toda** entidade nova (Princípio II — não-negociável).
- LGPD: payload de audit sem PII em texto livre; CPF mascarado nos logs (Princípio I).
- pt-BR único em UI, e-mails, exports e mensagens de erro.
- Stack fixada — sem novas libs core sem aprovação.
- Cobertura ≥ 75% nesta fase; ≥ 70% global mantida.
- Horizon configurado em produção; nesta fase, fila de importação roda em conexão dedicada `imports` com supervisor próprio (limita concorrência por tenant).

**Scale/Scope**:
- **MVP**: 50.000 pacientes por tenant; 10 médicos/tenant médios; 5 anos de histórico clínico simulado.
- **Volume operacional**: ~50 importações simultâneas globais sem fila bloqueada; 500 eventos/dia/tenant na timeline.
- **Scope de código**: ~15 controllers novos, ~10 services, ~13 migrations, ~15 Vue pages/components, ~6 jobs, ~100 testes novos, **27 endpoints novos** em `routes/api.php` (refinado pós-design via /speckit.analyze).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Princípio I — LGPD (NON-NEGOTIABLE)

- ✅ **Consentimento explícito**: paciente cadastrado por canal manual herda consentimento via aceite de TCLE registrado no cadastro do tenant (cobertos na Fase 0). Cadastro por canal externo (Fase 3+) capturará consentimento próprio.
- ✅ **Direito ao esquecimento (stub)**: coluna `anonimizado_em` + evento `PacienteAnonimizado`; queries de listagem filtram registros anonimizados; fluxo completo de portabilidade na Fase 8.
- ✅ **Criptografia em repouso**: dados de paciente no PG já cobertos pela criptografia de volume da infra (Princípio I cobertura geral). Tokens transitórios (sessão Sanctum) hash argon2id.
- ✅ **Pseudonimização de prompts LLM**: N/A nesta fase (sem IA).
- ✅ **Log de auditoria**: 13 eventos `Auditable` definidos em `spec.md > Eventos de Domínio Emitidos`. Retenção 1 ano via mecanismo de archive da Fase 0.

### Princípio II — Isolamento Multi-Tenant (NON-NEGOTIABLE)

- ✅ **Global scope `BelongsToTenant`** aplicado em todas as 7 entidades novas (Paciente, Anotação, Tag, TagPaciente, PacienteConvenio, Convenio, FunilColuna, EventoTimeline, Importacao, MesclagemPaciente, TarefaReatribuicao).
- ✅ **Endpoints resolvem tenant via subdomínio** (middleware `ResolveTenant` da Fase 0 já cobre `routes/api.php`).
- ✅ **Jobs `TenantAwareJob`**: `ProcessPatientImportJob` e demais usam a classe base que rehidrata `app('tenant')` no worker.
- ✅ **Broadcast**: N/A nesta fase (sem real-time).
- ✅ **Cache prefixado por tenant**: já automático via listener `TenantResolved` da Fase 0.
- ✅ **Teste de isolamento**: `TenantIsolationTest` será **expandido** para incluir os 27 endpoints novos (FR-006).

### Princípio III — Segurança Clínica da IA (NON-NEGOTIABLE)

✅ **N/A nesta fase** (sem IA). Princípio reativado a partir da Fase 4.

### Princípio IV — Spec-Driven Test-First

- ✅ **Spec aprovado**: `spec.md` Status `Clarified` (13 NCs resolvidos).
- ✅ **TDD respeitado**: cada US tem testes-primeiro listados em `tasks.md` (a ser gerado).
- ✅ **Cobertura ≥ 75% local / ≥ 70% global** mantida.
- ✅ **E2E**: 1 jornada nova (cadastro→tag→anotação→funil) integrada à suite Playwright existente.
- ✅ **Migrations imutáveis**: cada migration entra como nova; sem `ALTER` em migrations já aplicadas.
- ✅ **OpenAPI Scribe**: atualizado na mesma PR; `openapi:check` continua exit 0.
- ✅ **Pint + test**: gate de merge.

### Princípio V — Observabilidade

- ✅ **Logs estruturados**: middleware `LogStructuredRequestData` da Fase 0 já injeta `tenant_id, user_id, request_id` em toda request HTTP. Jobs herdam contexto via `TenantAwareJob`.
- ✅ **Eventos auditáveis**: 13 eventos definidos → listener wildcard grava em `audit_logs`.
- ✅ **Métricas**: contagem de pacientes/tenant + tempo de importação serão expostas via endpoint Prometheus em fase posterior (já existe endpoint na Fase 0).
- ✅ **Sentry**: erros não-tratados continuam reportados com contexto de tenant.
- ✅ **SLA**: nada nesta fase compromete os 99,5%.

### Princípio VI — Conformidade Meta nos Disparos (NON-NEGOTIABLE)

✅ **N/A nesta fase** (sem disparo de mensagem WhatsApp/Instagram). Princípio reativado na Fase 3.

### Princípio VII — Segurança Operacional (NON-NEGOTIABLE)

- ✅ **Rate limiting por tenant + endpoint**: limiters `api` (60/min/user+tenant) e novos `import` (5/h/tenant) e `export` (10/h/tenant) aplicados.
- ✅ **Bloqueio temporário login**: já ativo (Fase 0).
- ✅ **TLS 1.3 + argon2id**: já cobertos.

### Resultado do gate

**✅ APROVADO** sem violações. Nenhuma justificativa de complexidade necessária. Prosseguir para Phase 0.

## Project Structure

### Documentation (this feature)

```text
specs/002-crm-pacientes/
├── plan.md              # Este arquivo
├── research.md          # Phase 0 — decisões técnicas
├── data-model.md        # Phase 1 — 11 entidades novas
├── quickstart.md        # Phase 1 — como rodar/testar a feature local
├── contracts/
│   └── openapi.yaml     # Phase 1 — 27 endpoints novos do CRM
├── checklists/
│   └── requirements.md  # Já existe (do /speckit.specify)
└── tasks.md             # Phase 2 — gerado pelo /speckit.tasks
```

### Source Code (repository root) — reuso da estrutura da Fase 0

```text
app/
├── Models/
│   ├── Paciente.php                 # NEW (uses BelongsToTenant)
│   ├── Anotacao.php                 # NEW
│   ├── Tag.php                      # NEW
│   ├── Convenio.php                 # NEW
│   ├── PacienteConvenio.php         # NEW (pivot)
│   ├── EventoTimeline.php           # NEW
│   ├── Importacao.php               # NEW
│   ├── MesclagemPaciente.php        # NEW
│   ├── FunilColuna.php              # NEW
│   ├── TarefaReatribuicao.php       # NEW
│   └── Professional.php             # EXISTS (Fase 0 esqueleto) — estender com listener de desativação
├── Services/
│   ├── Pacientes/
│   │   ├── PacienteService.php
│   │   ├── DedupService.php
│   │   ├── MergeService.php
│   │   ├── AnonimizacaoService.php
│   │   ├── ImportacaoService.php
│   │   ├── ExportacaoService.php
│   │   └── TagService.php
│   ├── Funil/
│   │   ├── FunilService.php
│   │   └── FunilTemplateService.php
│   └── Convenios/
│       └── ConvenioService.php
├── Http/
│   ├── Controllers/Api/V1/Pacientes/
│   │   ├── PacientesController.php
│   │   ├── AnotacoesController.php
│   │   ├── TimelineController.php
│   │   ├── TagsController.php
│   │   ├── ImportacaoController.php
│   │   ├── ExportacaoController.php
│   │   ├── ConveniosController.php
│   │   ├── FunilController.php
│   │   └── MesclagemController.php
│   ├── Requests/Pacientes/
│   │   ├── CreatePacienteRequest.php
│   │   ├── UpdatePacienteRequest.php
│   │   ├── ImportPacientesRequest.php
│   │   └── ... (~12 form requests)
│   ├── Resources/
│   │   ├── PacienteResource.php
│   │   ├── PacienteListResource.php
│   │   ├── AnotacaoResource.php
│   │   ├── EventoTimelineResource.php
│   │   ├── TagResource.php
│   │   ├── ConvenioResource.php
│   │   ├── FunilColunaResource.php
│   │   ├── ImportacaoResource.php
│   │   └── MesclagemResource.php
│   └── Middleware/
│       └── (reuso: ResolveTenant, EnsureTenantNotSuspended, LogStructuredRequestData — todos existentes)
├── Jobs/
│   ├── Pacientes/
│   │   ├── ProcessPatientImportJob.php       # TenantAwareJob; checkpoint a cada 100 linhas
│   │   ├── ProcessPatientExportJob.php       # TenantAwareJob; gera CSV streaming + hash
│   │   ├── ReassignOrphansJob.php            # gerado quando profissional é desativado
│   │   └── RevertMergeJob.php                # rollback de mesclagem dentro da janela
│   └── (reuso: TenantAwareJob base — Fase 0)
├── Events/
│   ├── Paciente/                              # 13 eventos Auditable conforme spec § 6
│   │   ├── PacienteCriado.php
│   │   ├── PacienteAtualizado.php
│   │   ├── PacienteStatusAlterado.php
│   │   ├── PacienteMesclado.php
│   │   ├── PacienteMesclagemRevertida.php
│   │   ├── PacienteAnonimizado.php
│   │   ├── TagAplicada.php
│   │   ├── TagRemovida.php
│   │   ├── LeadMovidoNoFunil.php
│   │   ├── AnotacaoCriada.php
│   │   ├── AnotacaoRetratada.php
│   │   ├── PacientesImportados.php
│   │   └── PacientesExportados.php
│   └── (Auditable interface — Fase 0)
├── Policies/
│   ├── PacientePolicy.php
│   ├── AnotacaoPolicy.php
│   ├── TagPolicy.php
│   ├── ConvenioPolicy.php
│   └── FunilPolicy.php
├── Support/
│   ├── Cpf/CpfValidator.php          # NEW (DV BR)
│   ├── DocumentoEstrangeiro/Validator.php  # NEW (passaporte/RNE — formato livre, comprimento)
│   ├── Telefone/Normalizer.php       # NEW (E.164 canonical + BR-format display)
│   ├── Tags/Normalizer.php           # NEW (case + accent-insensitive)
│   └── Csv/                          # EXISTS (Lote O Fase 0) — extender para import
└── Filament/Resources/               # OPCIONAL — Filament super admin pode ganhar TenantPacientesWidget (métricas agregadas, sem PII)

database/
├── migrations/
│   ├── 2026_05_11_000001_create_pacientes_table.php
│   ├── 2026_05_11_000002_create_convenios_table.php
│   ├── 2026_05_11_000003_create_paciente_convenios_table.php
│   ├── 2026_05_11_000004_create_tags_table.php
│   ├── 2026_05_11_000005_create_paciente_tags_table.php
│   ├── 2026_05_11_000006_create_anotacoes_table.php
│   ├── 2026_05_11_000007_create_eventos_timeline_table.php
│   ├── 2026_05_11_000008_create_importacoes_table.php
│   ├── 2026_05_11_000009_create_mesclagens_pacientes_table.php
│   ├── 2026_05_11_000010_create_funil_colunas_table.php
│   ├── 2026_05_11_000011_create_tarefas_reatribuicao_table.php
│   ├── 2026_05_11_000012_enable_pg_trgm_extension.php
│   └── 2026_05_11_000013_add_pacientes_trigram_indexes.php
├── factories/
│   ├── PacienteFactory.php
│   ├── ConvenioFactory.php
│   ├── TagFactory.php
│   └── AnotacaoFactory.php
└── seeders/
    └── (extender DevSeeder existente com ~30 pacientes em clinica-alfa)

resources/
├── js/
│   ├── pages/pacientes/
│   │   ├── PacientesListPage.vue
│   │   ├── PacienteFormPage.vue          # criar + editar
│   │   ├── PacienteShowPage.vue          # ficha completa
│   │   ├── PacienteTimelinePage.vue      # tab integrado em show
│   │   ├── ImportacaoPage.vue
│   │   ├── ImportacaoStatusPage.vue
│   │   ├── ExportacaoPage.vue
│   │   ├── MesclagemPage.vue
│   │   ├── FunilKanbanPage.vue
│   │   └── FunilConfigPage.vue           # configurar colunas
│   ├── pages/convenios/
│   │   ├── ConveniosListPage.vue
│   │   └── ConvenioFormPage.vue
│   ├── components/
│   │   ├── pacientes/
│   │   │   ├── PacienteCard.vue          # usado no Kanban
│   │   │   ├── TagPicker.vue
│   │   │   ├── AnotacaoForm.vue
│   │   │   ├── TimelineEvent.vue
│   │   │   ├── DedupSuggestionModal.vue
│   │   │   └── PerdidoMotivoModal.vue
│   │   └── funil/
│   │       ├── KanbanBoard.vue
│   │       └── KanbanColumn.vue
│   └── composables/
│       └── usePacienteSearch.js          # debounce + cache de busca por similaridade
└── views/emails/                          # nenhum nesta fase

tests/
├── Feature/Fase2/Pacientes/
│   ├── PacienteCadastroTest.php          # AC-3.1.1 a 3.1.8
│   ├── PacienteCpfValidationTest.php
│   ├── PacienteDedupTest.php             # AC-3.1.3
│   ├── PacienteMergeTest.php             # AC mesclagem
│   ├── PacienteTimelineTest.php          # US-3.2 ACs
│   ├── PacienteAnotacaoTest.php
│   ├── ImportacaoTest.php                # US-3.3 ACs
│   ├── ImportacaoLimitsTest.php
│   ├── FunilKanbanTest.php               # US-3.4 ACs
│   ├── TagSegmentacaoTest.php            # US-3.5 ACs
│   ├── PacienteStatusMachineTest.php
│   ├── PacienteIsolationTest.php         # extensão multi-tenant
│   ├── PacientePermissionsTest.php       # 403 para Financeiro/Super Admin
│   ├── ExportacaoTest.php
│   └── ConvenioCrudTest.php
├── Unit/Support/
│   ├── CpfValidatorTest.php
│   ├── TelefoneNormalizerTest.php
│   └── TagNormalizerTest.php
└── e2e/
    └── crm-paciente-jornada-completa.spec.ts   # E2E: cadastro→tag→anotação→funil
```

**Structure Decision**: **Web application** (camada SPA Vue + camada API Laravel, sem app móvel). Layout reusa convenções já estabelecidas na Fase 0: `app/Services/{Dominio}/`, `app/Http/Controllers/Api/V1/{Dominio}/`, `app/Events/{Dominio}/`. Reescrita ou movimento de Models existentes da Fase 0 é proibido (princípio "migrations imutáveis"). Frontend segue o padrão `resources/js/pages/{dominio}/` + `resources/js/components/{dominio}/`.

## Phase 0 / Phase 1 Reference

- **Phase 0 — Research**: [research.md](./research.md) — todas as decisões técnicas resolvidas (parser CSV, pg_trgm, normalização de tags, granularidade da timeline, idempotência de import, mesclagem reversível, padrão de retomada de jobs).
- **Phase 1 — Data Model**: [data-model.md](./data-model.md) — 11 entidades novas + extensão de `professionals` (listener de desativação).
- **Phase 1 — Contracts**: [contracts/openapi.yaml](./contracts/openapi.yaml) — 27 endpoints novos cobertos por Scribe + `openapi:check`.
- **Phase 1 — Quickstart**: [quickstart.md](./quickstart.md) — passos manuais para validar localmente.

## Convenções de implementação (recap)

Estas regras são herdadas e reforçadas para Fase 2:

1. **Sail obrigatório**: `vendor/bin/sail artisan …`, `vendor/bin/sail npm …`, `vendor/bin/sail composer …`.
2. **Pint clean**: `vendor/bin/sail bin pint --dirty --format agent` antes de fechar PR.
3. **TDD**: cada AC tem teste antes da implementação.
4. **Tenant isolation**: cada endpoint autenticado entra no `PacienteIsolationTest` (extensão do `TenantIsolationTest`).
5. **i18n pt-BR**: nada de string hardcoded em outras línguas — usar `lang/pt_BR/*` e `resources/js/i18n/pt-BR.json`.
6. **OpenAPI**: cada novo endpoint atualiza `openapi.yaml` + anotação Scribe no Controller. `openapi:check` é gate de CI.
7. **Eventos auditáveis**: cada ação sensível dispara evento implementando `Auditable` → log automático.
8. **Reaproveitamento de UI**: `ConfirmModal`, `AuthHeroPanel`, `useI18nFormat`, `formatCurrency`, `formatDate` já existem — não duplicar.
9. **Migrations idempotentes** e imutáveis: novos arquivos para cada mudança; nunca editar migrations já em main.
10. **Jobs em fila dedicada**: import vai em conexão `imports` com supervisor próprio para isolar tenant noisy-neighbor.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Nenhuma | — | — |

**Sem violações de complexidade.** A fase mantém todas as escolhas dentro do que a constituição e a Fase 0 já estabelecem.

## Verificação Constitucional pós-design (após Phase 1)

Reavaliação dos princípios após produção de `data-model.md` e `contracts/openapi.yaml`:

- **I (LGPD)** — ✅ Re-checado: nenhum endpoint expõe PII em log; CSV de export passa por `CsvExporter::escapeFormulaInjection` (já da Fase 0); payload de audit sanitizado em `AuditAttributesBuilder` (idem).
- **II (Multi-tenant)** — ✅ Re-checado: todos os 27 endpoints aplicam `auth:sanctum` + `ResolveTenant`; modelos novos têm `BelongsToTenant`. `pg_trgm` indexes incluem `tenant_id` como primeira coluna composta.
- **III (IA)** — ✅ N/A.
- **IV (Spec-Driven Test-First)** — ✅ Re-checado: contrato OpenAPI gerado e batido contra rotas; coverage gate ≥75% local mantido.
- **V (Observabilidade)** — ✅ Re-checado: jobs de import logam progresso por checkpoint; eventos publicados conforme spec § 6.
- **VI (Meta)** — ✅ N/A.
- **VII (Segurança)** — ✅ Re-checado: rate limit `import` (5/h/tenant) e `export` (10/h/tenant) novos; nenhum endpoint pula `auth:sanctum`.

**Resultado pós-design**: ✅ **APROVADO**. Pronto para `/speckit.tasks`.
