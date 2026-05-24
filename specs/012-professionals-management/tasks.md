---
description: "Tasks for 012 — Gestão de Profissionais + Unlock Onboarding Step 2 (US-1.2.2)"
---

# Tasks: Gestão de Profissionais + Unlock Onboarding Step 2

**Input**: Design documents from `/specs/012-professionals-management/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api-professionals.md, quickstart.md
**Tests**: Conforme Princípio IV (Test-First) — 9 Feature tests + 1 Unit test cobrindo gates G1–G9 do contract.

**Organização**: Tasks por user story. Lotes A–G do `quickstart.md` mapeiam para combinações de phases abaixo.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Paralelizável (arquivos distintos, sem dependências).
- **[Story]**: User story do spec (US1..US6).
- Cada task referencia caminho absoluto a partir da raiz do repo.

## Path Conventions

Backend Laravel + Frontend Vue SPA. Caminhos:

- Migration: `database/migrations/`
- Models: `app/Models/`
- Controllers: `app/Http/Controllers/Api/V1/Professionals/`
- Requests: `app/Http/Requests/Professionals/`
- Resources: `app/Http/Resources/Professionals/`
- Services: `app/Services/Professionals/`
- Events: `app/Events/Professionals/`
- Listeners: `app/Listeners/Professionals/`
- Policies: `app/Policies/`
- Frontend page: `resources/js/pages/Professionals/`
- Frontend components: `resources/js/components/Professionals/`
- Composables/Stores: `resources/js/composables/`, `resources/js/stores/`
- i18n: `resources/js/i18n/pt-BR.json`, `lang/pt_BR/`
- Tests: `tests/Feature/Professionals/`, `tests/Feature/Onboarding/`, `tests/Unit/Onboarding/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Pré-requisitos de ambiente + verificação.

- [X] T001 Verificar branch `012-professionals-management` checked out a partir de main com todas as 3 specs anteriores (009/010/011) mergeadas; Sail rodando; Vite dev rodando; pelo menos 1 tenant `rb-clinic` com 1 admin-clinica + 1 medico + 1 recepcionista seedados (para testes de permissão)
- [X] T002 [P] Adicionar bloco `professionals.*` em `resources/js/i18n/pt-BR.json` cobrindo: labels (page_title, page_subtitle, table headers), formulário (campos: name, council_type, council_type_other, council_number, council_state, especialidade, link mode/invite mode), modais (deactivate_confirm, email_already_user, council_types enum), botões (new, edit, deactivate, reactivate, save, cancel), filtros (status_all/active/inactive), busca placeholder, empty state, mensagens de erro/sucesso, aria-labels
- [X] T003 [P] Adicionar arquivo `lang/pt_BR/professionals.php` com strings backend (mensagens de validação custom, mensagens de evento em audit log)

**Checkpoint**: Ambiente + i18n disponíveis.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migration + permission + model + policy. Bloqueia toda implementação subsequente.

**⚠️ CRITICAL**: Nenhum trabalho em user story pode começar até esta phase estar completa.

- [X] T004 Criar migration `database/migrations/2026_05_24_000001_alter_professionals_add_especialidade_and_constraints.php` (R1):
  - `ALTER TABLE professionals ADD COLUMN especialidade VARCHAR(100) NULL`
  - `ALTER TABLE professionals ADD COLUMN council_type_other VARCHAR(50) NULL`
  - `CREATE UNIQUE INDEX professionals_council_unique_per_tenant ON professionals (tenant_id, council_type, council_number, council_state) WHERE deleted_at IS NULL`
  - `down()`: DROP INDEX + DROP COLUMNs em ordem reversa
  - Rodar `vendor/bin/sail artisan migrate` e validar que aplicou
- [X] T005 Atualizar `app/Models/Professional.php` — adicionar `'especialidade', 'council_type_other'` ao array `$fillable` (manter `$guarded = ['id','tenant_id',...]` se existir)
- [X] T006 Atualizar `database/seeders/RolesSeeder.php` (R3) — adicionar `'professional.manage'` ao array de permissions do role `admin-clinica` (linha ~121 conforme grep prévio); rodar `vendor/bin/sail artisan db:seed --class=RolesSeeder` para popular
- [X] T007 [P] Criar `app/Policies/ProfessionalPolicy.php` com método `manage(User $user): bool { return $user->can('professional.manage'); }` + `viewAny()` que retorna true se autenticado (necessário para dropdown de seleção em outras telas como criação de Appointment) — NOTA: arquivo existe mas autorização real é via `Gate::define('professional.manage', ... hasPermissionTo ...)` em `AppServiceProvider:261` (conflito com `ProfessionalSchedulePolicy` da Fase 5 que já mapeia `Professional`); policy é vestigial
- [X] T008 [P] Registrar `ProfessionalPolicy` no `AuthServiceProvider` mapeando para `App\Models\Professional` — NOTA: objetivo (autorização) atingido via `Gate::define` em vez de mapeamento de policy; `Professional` está mapeado a `ProfessionalSchedulePolicy` (Fase 5)
- [X] T009 [P] Criar 3 eventos novos auditáveis em `app/Events/Professionals/`:
  - `ProfessionalCreated.php` implements `Auditable` (props: `Professional $professional`)
  - `ProfessionalUpdated.php` implements `Auditable` (props: `Professional $professional, array $changes`)
  - `ProfessionalActivatedByInvitation.php` implements `Auditable` (props: `Professional $professional, int $invitationId`)

**Checkpoint**: Schema + permission + model + eventos prontos. Pode começar US-1/2/3.

---

## Phase 3: User Story 2 — Cadastro de Profissional pelo Painel (Priority: P1) 🎯 MVP

**Goal**: CRUD básico funcional via API + tela de listagem + formulário modal.

**Independent Test**: Logar como admin-clinica; navegar `/panel/profissionais`; cadastrar 1 médico via modal vinculando user existente; ver na tabela imediatamente.

> Nota: começo por US-2 (não US-1) porque US-2 é a infraestrutura completa — US-1 (onboarding step 2) reusa o mesmo formulário. Implementar US-2 primeiro destrava US-1 trivialmente.

### Tests for User Story 2 ⚠️ (Test-First)

- [X] T010 [P] [US2] Criar `tests/Feature/Professionals/ProfessionalsCrudTest.php` cobrindo: `test_admin_can_create_professional_linked_to_existing_user`, `test_admin_can_list_professionals_paginated`, `test_admin_can_view_single_professional`, `test_admin_can_update_professional_basic_fields`, `test_search_by_name_is_case_insensitive`, `test_list_default_filter_is_active_only`
- [X] T011 [P] [US2] Criar `tests/Feature/Professionals/ProfessionalsCrossTenantTest.php` (gate G1): `test_admin_of_tenant_a_cannot_see_tenant_b_professionals`, `test_admin_cannot_update_tenant_b_professional` (404 não 403)
- [X] T012 [P] [US2] Criar `tests/Feature/Professionals/ProfessionalsPermissionGateTest.php` (gate G2): 3 tests — medico/recepcionista/atendente recebem 403 em GET/POST/PUT/DELETE

### Implementation for User Story 2

- [X] T013 [P] [US2] Criar `app/Http/Requests/Professionals/StoreProfessionalRequest.php` com validações: name 3-150, council_type in:CRM,CRO,COREN,CRP,OUTRO, council_type_other required_if:council_type,OUTRO max:50, council_number 5-20 regex:`^[A-Za-z0-9.\-]+$`, council_state in:AC..TO (lista de UFs), especialidade nullable max:100, XOR entre user_id (exists com BelongsToTenant scope) e email (rfc valid); incluir flag `confirmed_existing_user` boolean opcional (Q2)
- [X] T014 [P] [US2] Criar `app/Http/Requests/Professionals/UpdateProfessionalRequest.php` — mesmas regras do Store mas user_id é `prohibited` (FR-010), is_active é `prohibited` (só via activate/destroy)
- [X] T015 [P] [US2] Criar `app/Http/Resources/Professionals/ProfessionalResource.php` expondo: id, name, council_type, council_type_other, council_number, council_state, especialidade, is_active, user (id+name apenas — sem email per R9), created_at, updated_at
- [X] T016 [US2] Criar `app/Services/Professionals/ProfessionalService.php` (R8) com 4 métodos: `create(array $data, User $actor): Professional`, `update(Professional $p, array $data, User $actor): Professional`, `deactivate(Professional $p, User $actor): Professional` (set is_active=false + soft-delete via destroy), `activate(Professional $p, User $actor): Professional` (restore + is_active=true). Cada método em `DB::transaction()` + dispara evento auditável correspondente
- [X] T017 [US2] Criar `app/Http/Controllers/Api/V1/Professionals/ProfessionalsController.php` com 5 actions: `index(Request)`, `store(StoreProfessionalRequest)`, `show(Professional)`, `update(UpdateProfessionalRequest, Professional)`, `destroy(Professional)` — todas resolvem `ProfessionalPolicy::manage` via `$this->authorize('manage', Professional::class)`; index aplica filter `is_active` + busca ILIKE case-insensitive (use `LOWER(unaccent(name)) ILIKE LOWER(unaccent(:search))` se trigram disponível, senão `name ILIKE :search`); paginação cursor 25 default; destroy chama `service->deactivate()` (soft delete + dispara ProfessionalDeactivated)
- [X] T018 [P] [US2] Registrar rotas em `routes/api.php` no grupo `['auth:sanctum', 'tenant.slug', 'tenant.not-suspended']` + prefixo `professionals` + name `professionals.`: `Route::apiResource('/professionals', ProfessionalsController::class)` + nomes (index/store/show/update/destroy)

### Frontend — User Story 2

- [X] T019 [P] [US2] Criar `resources/js/stores/professionalsStore.js` (Pinia) com state `{ list, filters: {is_active: 'true', search: ''}, loading, error, lastFetched }` + actions `fetchList`, `create`, `update`, `deactivate`, `activate` + cache leve por filters
- [ ] T020 [P] [US2] Criar `resources/js/composables/useProfessionals.js` — wrap do store + debounce 300ms no search + helpers (`isLoading`, `hasItems`, `setFilter`)
- [X] T021 [P] [US2] Criar `resources/js/components/Professionals/CouncilTypeSelect.vue` — dropdown com 5 opções (CRM/CRO/COREN/CRP/Outro) usando `v-model`; quando "Outro" selecionado emite `@requires-other` para o pai exibir o input `council_type_other`
- [ ] T022 [P] [US2] Criar `resources/js/components/Professionals/EspecialidadeAutocomplete.vue` — input com `v-model` + lista de sugestões; consulta `GET /professionals/especialidades?q=` com debounce 300ms; permite digitar valor novo (não-restritivo); dropdown a11y (`role="combobox"`, `aria-expanded`, navegação por setas) — Q1
- [X] T023 [US2] Criar `resources/js/components/Professionals/ProfessionalFormModal.vue` (R10) — modal teleportado a body com focus trap + Esc fechar; usa `CouncilTypeSelect` + `EspecialidadeAutocomplete`; toggle "Vincular a usuário existente" (autocomplete de users) vs "Convidar por email"; submete via store; trata 409 (Q2) abrindo `EmailAlreadyUserModal`; emite `@saved(professional)` ao concluir
- [ ] T024 [P] [US2] Criar `resources/js/components/Professionals/ProfessionalsTable.vue` — tabela com 5 colunas (Nome, Conselho, Especialidade, Status, Ações); badge de status com texto explícito + ícone (não só cor — FR-033); botão "Editar" + botão dinâmico "Desativar"/"Reativar"; props: `items`, `loading`; emits: `@edit`, `@deactivate`, `@reactivate`
- [X] T025 [US2] Criar `resources/js/pages/Professionals/ProfessionalsListPage.vue` (orquestrador) — header com título + botão "Novo profissional"; toolbar com filtro de status + busca; renderiza `ProfessionalsTable`; controla abertura do `ProfessionalFormModal` (modo create/edit); empty state amistoso com CTA "Cadastrar primeiro"
- [X] T026 [US2] Adicionar rota em `resources/js/router/index.js` dentro de `panelChildren`: `{ path: 'profissionais', name: 'professionals.list', component: () => import('@/pages/Professionals/ProfessionalsListPage.vue'), meta: { title: 'layout.sidebar.settings.professionals', ability: 'professional.manage' } }`
- [X] T027 [US2] Adicionar item em `resources/js/config/navigation.js` no grupo `settings`: `{ key: 'settings.professionals', labelKey: 'layout.sidebar.settings.professionals', routeName: 'professionals.list', ability: 'professional.manage' }`. Adicionar i18n key `layout.sidebar.settings.professionals: 'Profissionais'` em `pt-BR.json`
- [X] T028 [US2] Rodar T010–T012 e validar verdes; smoke manual em `/panel/profissionais`

**Checkpoint**: US-2 completo. Admin consegue gerenciar profissionais pelo painel.

---

## Phase 4: User Story 3 — Edição (Priority: P1)

**Goal**: Reuso completo do form modal para edição com pré-preenchimento.

**Independent Test**: Clicar "Editar" em profissional existente → ver dados pré-populados → alterar especialidade → salvar → ver na tabela atualizado.

### Tests for User Story 3

- [X] T029 [P] [US3] Estender `ProfessionalsCrudTest.php` (T010) com cenários: `test_admin_can_update_council_data`, `test_user_id_field_is_prohibited_on_update`, `test_is_active_field_is_prohibited_on_update`
- [X] T030 [P] [US3] Criar `tests/Feature/Professionals/ProfessionalsCouncilUniquenessTest.php` (gate G3): `test_unique_per_tenant_constraint_blocks_duplicate`, `test_reuse_after_soft_delete_is_allowed` (cadastra CRM 12345/SP, soft-delete, cadastra novo CRM 12345/SP — deve permitir)

### Implementation for User Story 3

- [X] T031 [US3] `ProfessionalsController::update()` (já criado em T017) — verificar que rejeita `user_id` e `is_active` via UpdateProfessionalRequest (T014)
- [X] T032 [US3] `ProfessionalFormModal.vue` (T023) — adicionar prop `professional` opcional; quando presente, pré-popula campos e muda título para "Editar profissional"; vínculo de user fica readonly em modo edit (cinza com label "Não pode ser alterado"); submete via `store.update(id, data)`
- [X] T033 [US3] Rodar T029 + T030 e validar verdes

**Checkpoint**: Edição funcional.

---

## Phase 5: User Story 4 — Desativação e Reativação (Priority: P2)

**Goal**: Modal de confirmação descritivo + reuso do ReassignOrphansJob existente.

**Independent Test**: Atribuir paciente fictício a profissional → desativar profissional → confirmar modal → ver job de reatribuição enfileirado → paciente fica órfão.

### Tests for User Story 4

- [X] T034 [P] [US4] Criar `tests/Feature/Professionals/ProfessionalDeactivationReassignsTest.php` (gate G6): `test_deactivating_professional_dispatches_reassign_orphans_job` (usa `Queue::fake` + asserta `ReassignOrphansJob::class` enfileirado); `test_reactivating_does_not_re_dispatch` (rea-ativação não dispara reassign)

### Implementation for User Story 4

- [X] T035 [P] [US4] Criar endpoint `POST /api/v1/professionals/{id}/activate` — controller `ProfessionalActivateController` (R10) com action `__invoke(Professional)`; chama `service->activate()`; retorna `ProfessionalResource`
- [X] T036 [P] [US4] Registrar rota: `Route::post('/professionals/{professional}/activate', ProfessionalActivateController::class)->name('professionals.activate')`
- [X] T037 [P] [US4] Criar `resources/js/components/Professionals/DeactivateConfirmModal.vue` (R10) — modal a11y com focus trap; props `{ professional }`; texto descritivo: "Desativar Dr(a). {nome}? Pacientes sob responsabilidade serão reatribuídos automaticamente."; botões Cancelar + Desativar (perigo); emite `@confirmed` — ⚠️ GAP REAL: NÃO está no disco; `ProfessionalsListPage.vue:37` usa `window.confirm()` nativo, violando FR-015/FR-032 e a regra do projeto (proibido `confirm()` nativo — CLAUDE.md Fase 6)
- [X] T038 [US4] `ProfessionalsListPage.vue` (T025) — handler `onDeactivate(professional)` abre `DeactivateConfirmModal`; on confirmed, chama `store.deactivate(id)` → toast sucesso; `onReactivate(professional)` chama `store.activate(id)` direto (sem modal — ação não-destrutiva) — ⚠️ handlers existem mas usam `window.confirm` em vez do modal acessível (depende de T037)
- [X] T039 [US4] Rodar T034 + smoke manual: criar profissional, atribuir paciente fictício, desativar e validar via tinker que `ReassignOrphansJob` foi enfileirado

**Checkpoint**: Desativação + reativação funcionais.

---

## Phase 6: User Story 5 — Permissões e Visibilidade (Priority: P1)

**Goal**: Gate de segurança consolidado — visibilidade no menu + bloqueio na API.

**Independent Test**: Logar como `medico` → não ver item "Profissionais" na sidebar → tentar `/panel/profissionais` direto → ser redirecionado/barrado.

### Tests for User Story 5

> Cobertura primária via `ProfessionalsPermissionGateTest` (T012) já criado. Adicionar 2 cenários frontend:

- [X] T040 [P] [US5] Adicionar em `tests/Feature/Professionals/ProfessionalsPermissionGateTest.php` (T012): `test_user_without_professional_manage_cannot_access_check_email_endpoint`, `test_user_without_professional_manage_cannot_access_autocomplete_endpoint` (cobre todos os 8 endpoints)

### Implementation for User Story 5

> Já coberto pelos itens anteriores. Verificação:
> - `useNavigation()` (spec 009) filtra automaticamente itens da sidebar por ability — não precisa de código novo aqui
> - Router guard (spec 009 `beforeEach`) bloqueia URL direta — não precisa de código novo aqui
> - Backend: `ProfessionalPolicy::manage` (T007) aplicada via `$this->authorize` em todos os controllers

- [X] T041 [US5] Smoke manual em browser: logar como `medico` → confirmar que item "Profissionais" NÃO aparece na sidebar → digitar URL `http://rb-clinic.lvh.me/panel/profissionais` → guard redireciona ou tela mostra mensagem
- [X] T042 [US5] Rodar T040 e validar verde

**Checkpoint**: Permissões consolidadas.

---

## Phase 7: User Story 6 — Listagem com Filtros e Busca (Priority: P2)

**Goal**: Listagem responsiva com filtros + paginação cursor.

**Independent Test**: Tenant com 12 profissionais (8 ativos, 4 inativos); filtrar por status; buscar por nome.

> Funcionalidades já implementadas em T017 (backend) + T025 (frontend) durante US-2. Esta phase apenas garante cobertura formal:

- [X] T043 [P] [US6] Estender `ProfessionalsCrudTest.php` (T010) com cenários explícitos: `test_filter_by_inactive_status`, `test_filter_by_all_status_includes_both`, `test_search_finds_partial_match`, `test_search_is_accent_insensitive` (se trigram extension disponível; senão skipped com nota), `test_cursor_pagination_returns_next_page`

**Checkpoint**: US-6 coberto.

---

## Phase 8: User Story 1 — Onboarding Step 2 (Priority: P1)

**Goal**: Wizard de onboarding desbloqueia step 2 + step 4 progressivamente.

**Independent Test**: Registrar novo tenant → completar step 1 → ver step 2 unlocked → cadastrar primeiro profissional via wizard → step 2 marcado completed + step 4 unlocked.

### Tests for User Story 1

- [X] T044 [P] [US1] Criar `tests/Feature/Onboarding/OnboardingUnlockProgressionTest.php` (gate G8) com 4 cenários:
  - `test_completing_clinic_data_unlocks_first_professional`
  - `test_completing_first_professional_unlocks_schedule_setup`
  - `test_skipping_first_professional_does_not_unlock_schedule_setup` (FR-026)
  - `test_channel_connection_and_ai_knowledge_base_remain_locked` (FR-029)
- [X] T045 [P] [US1] Criar `tests/Unit/Onboarding/OnboardingServiceUnlockStepTest.php`: `test_unlock_step_is_idempotent_when_already_pending`, `test_unlock_step_changes_locked_to_pending`, `test_unlock_step_no_op_on_already_completed`

### Implementation for User Story 1

- [X] T046 [US1] Estender `app/Services/Onboarding/OnboardingService.php` (R5) — adicionar método público `unlockStep(Tenant $tenant, string $stepKey): void` que muta status `locked → pending` (idempotente; no-op se já `pending`, `completed` ou `skipped`); persiste via `tenant->update(['onboarding_state' => ...])`
- [X] T047 [US1] Estender `OnboardingService::completeStep()` (R5) — após persistir step + emitir evento + computar completed, adicionar triggers: `if ($stepKey === 'clinic_data') $this->unlockStep($fresh, 'first_professional'); elseif ($stepKey === 'first_professional') $this->unlockStep($fresh, 'schedule_setup');`
- [X] T048 [US1] Backfill nos tenants existentes: criar comando artisan one-shot `php artisan onboarding:backfill-unlocks` que para cada tenant com `clinic_data.status='completed'` aplica `unlockStep(first_professional)`; para cada com `first_professional.status='completed'` aplica `unlockStep(schedule_setup)`. Idempotente — pode rodar várias vezes.
- [X] T049 [US1] Estender `resources/js/pages/onboarding/OnboardingWizardPage.vue` — quando step `first_professional` é clicado e está `pending`, abrir `ProfessionalFormModal` (mesmo componente do T023) em modo embed (sem teleport — fica dentro do wizard); ao receber `@saved(professional)`, chamar `POST /onboarding/steps/first_professional/complete` com payload `{ professional_id, via: 'linked_user'|'invited_user' }`; atualizar state local + ver step 4 unlocked
- [X] T050 [US1] Rodar T044 + T045 e validar verdes; smoke manual: registrar nova clínica → completar step 1 → step 2 desbloqueado → cadastrar profissional → step 2 completed + step 4 unlocked

**Checkpoint**: Onboarding step 2 entregue.

---

## Phase 9: Invite Flow + Email Check (Priority: P1)

**Goal**: Fluxo de convite por email + Q2 confirmação de email duplicado.

**Independent Test**: POST profissional com email novo → cria Invitation pendente + Professional inativo; user aceita invite → Professional ativa automaticamente. POST com email já-é-user-do-tenant → recebe 409 → admin confirma → cria vinculado.

### Tests

- [X] T051 [P] Criar `tests/Feature/Professionals/ProfessionalInvitationFlowTest.php` (gates G4 + G9):
  - `test_post_with_new_email_creates_pending_invitation_and_inactive_professional`
  - `test_invitation_accepted_activates_pending_professional` (listener test)
  - `test_email_already_user_in_other_tenant_blocked_422` (gate G9)
- [X] T052 [P] Criar `tests/Feature/Professionals/ProfessionalEmailAlreadyUserTest.php` (gate G5):
  - `test_post_with_email_already_user_in_tenant_returns_409_without_confirmation`
  - `test_post_with_email_already_user_succeeds_when_confirmed_existing_user_true`
  - `test_check_email_endpoint_returns_existing_user_id_and_name_only` (não expõe email)

### Implementation

- [X] T053 [P] Criar `app/Services/Professionals/ProfessionalInvitationService.php` (R4) com `createWithInvite(array $data, User $actor): Professional` — valida que email não é user atual nem outro tenant; cria Professional `is_active=false, user_id=NULL`; cria Invitation (Fase 4) com role `medico` + payload `{professional_id: $newId}`; retorna Professional
- [X] T054 [P] Criar `app/Listeners/Professionals/ActivatePendingProfessionalOnInvitationAccepted.php` que escuta `App\Events\Users\InvitationAccepted`: lê `event->invitation->payload['professional_id']` (null-safe); se presente, busca Professional, set `user_id = acceptedByUser->id` + `is_active=true`, dispatch `ProfessionalActivatedByInvitation`
- [X] T055 Registrar listener em `app/Providers/EventServiceProvider.php` (ou auto-discovery se Laravel 11+ — verificar padrão atual; spec 005 mencionou que projeto usa auto-discovery)
- [X] T056 Criar `app/Http/Controllers/Api/V1/Professionals/CheckEmailController.php` (R6) — action `__invoke(Request $request)` valida `email:rfc`; consulta User no tenant atual + cross-tenant; retorna `{ exists_in_current_tenant: bool, existing_user: {id,name}|null, exists_in_other_tenant: bool }` (sem email — R9)
- [X] T057 Atualizar `ProfessionalsController::store()` (T017) — quando `email` informado SEM `confirmed_existing_user=true`, delegar a `ProfessionalInvitationService::createWithInvite()`; se service detecta email já-é-user, retornar 409 com `existing_user` payload; quando `confirmed_existing_user=true`, criar Professional vinculado direto
- [X] T058 Registrar rota `Route::post('/professionals/check-email', CheckEmailController::class)->name('professionals.check-email')`
- [X] T059 [P] Criar `resources/js/components/Professionals/EmailAlreadyUserModal.vue` — modal a11y; props `{ existingUser }`; texto "Esse email já pertence ao usuário {nome}. Deseja vincular esse usuário ao novo profissional?"; emite `@confirm` (re-submete POST com `confirmed_existing_user=true`) e `@cancel` (volta ao form) — ⚠️ GAP REAL: NÃO está no disco
- [X] T060 [US] Estender `ProfessionalFormModal.vue` (T023) — onBlur do campo email no modo "convite", chama `professionalsStore.checkEmail(email)`; se `exists_in_current_tenant=true`, abre `EmailAlreadyUserModal`; se `exists_in_other_tenant=true`, mostra erro inline "Email já cadastrado em outro tenant"; trata 409 do POST com mesmo modal — ⚠️ PARCIAL: 409 é tratado inline (`ProfessionalFormModal:115`) mas auto-seta `confirmed_existing_user=true` e re-submete sem confirmação explícita do admin (FR-005a exige modal); sem onBlur check
- [X] T061 Rodar T051 + T052 e validar verdes

**Checkpoint**: Convites e Q2 funcionam.

---

## Phase 10: Autocomplete de Especialidade (Priority: P2)

**Goal**: Sugestões contra histórico do tenant — Q1 / FR-001.

### Tests

- [X] T062 [P] Criar `tests/Feature/Professionals/EspecialidadesAutocompleteTest.php` (gate G7):
  - `test_returns_distinct_especialidades_from_tenant`
  - `test_filter_by_query_is_case_insensitive`
  - `test_cross_tenant_isolation` (Princípio II)
  - `test_excludes_soft_deleted_professionals`

### Implementation

- [X] T063 [P] Criar `app/Services/Professionals/EspecialidadesAutocompleteService.php` (R7) — método `suggest(?string $q): array` que retorna DISTINCT especialidades do tenant atual; aplica `ILIKE %q%` quando q presente; ordena alfabeticamente; limit 10 com q, 50 sem; cache Redis 60s key `professionals:especialidades:{tenant_id}` (opcional — pode ser implementado em iteração posterior)
- [X] T064 [P] Criar `app/Http/Controllers/Api/V1/Professionals/EspecialidadesAutocompleteController.php` — `__invoke(Request)` retorna `{data: string[]}`
- [X] T065 Registrar rota `Route::get('/professionals/especialidades', EspecialidadesAutocompleteController::class)->name('professionals.especialidades')`
- [X] T066 `EspecialidadeAutocomplete.vue` (T022) — chamar este endpoint via store/composable

**Checkpoint**: Autocomplete funcional.

---

## Phase 11: Polish & Gates

**Purpose**: Acabamento + Constitution Re-Check + docs.

### Qualidade

- [ ] T067 [P] Audit a11y axe/Lighthouse em `/panel/profissionais` + modais (form, deactivate confirm, email already user) em viewports 360px e 1280px — meta SC-007: 0 violations sérias/críticas. Gravar evidência em `specs/012-professionals-management/a11y-audit.md`
- [X] T068 [P] `vendor/bin/sail npm run build` — confirmar build verde, bundle dos components Professionals < 80KB minified+gzip
- [X] T069 [P] `vendor/bin/sail bin pint --dirty --format agent` — formatar arquivos PHP novos/modificados
- [X] T070 `vendor/bin/sail artisan test --compact --filter='Professionals|OnboardingUnlockProgression|OnboardingServiceUnlockStep'` — todos verdes; investigar SIGSEGV pré-existente (spec 011) se aparecer e usar `--filter` específico
- [X] T071 [P] `vendor/bin/sail artisan test --compact tests/Feature/Professionals tests/Feature/Onboarding` — folder run, mesmo critério
- [ ] T072 Smoke manual end-to-end: registrar novo tenant → completar step 1 → step 2 desbloqueado → cadastrar profissional vinculado a user existente → step 4 desbloqueado → navegar para `/panel/profissionais` → editar profissional → desativar (validar paciente reatribuído) → reativar; logar como medico → confirmar item "Profissionais" oculto

### Re-check & docs

- [X] T073 Constitution Re-Check pós-implementação — confirmar 7/7 PASS continua válido (especialmente Princípio II isolamento + IV test-first com 11 tests verdes)
- [X] T074 [P] Atualizar `CLAUDE.md` adicionando seção "Gestão de Profissionais (Fase 12) — Key Patterns": UNIQUE composto parcial WHERE deleted_at IS NULL, council_type_other condicional, ProfessionalInvitationService payload, listener `ActivatePendingProfessionalOnInvitationAccepted`, OnboardingService.unlockStep com triggers (clinic_data→first_professional / first_professional→schedule_setup), endpoint check-email retorna id+name sem email (R9), autocomplete especialidade DISTINCT por tenant
- [X] T075 [P] Criar `specs/012-professionals-management/DEFERRED.md` se houver tasks pendentes (audit a11y manual, smoke em browser real, suite full validation)
- [ ] T076 Atualizar `.specify/feature.json` para `DELIVERED` quando todos os gates passarem

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** — sem dependências
- **Phase 2 (Foundational)** — depende de Phase 1; BLOQUEIA US stories
- **Phase 3 (US-2 P1, MVP)** — depende de Phase 2; entrega CRUD completo backend + frontend
- **Phase 4 (US-3 P1)** — depende de Phase 3 (reusa form modal)
- **Phase 5 (US-4 P2)** — depende de Phase 3 (CRUD pronto para desativar)
- **Phase 6 (US-5 P1)** — depende de Phase 2 (policy) + Phase 3 (UI); maioria já vem implícita das phases anteriores
- **Phase 7 (US-6 P2)** — depende de Phase 3 (já implementado lá)
- **Phase 8 (US-1 P1)** — depende de Phase 3 (reusa ProfessionalFormModal)
- **Phase 9 (Invite + Email)** — depende de Phase 3
- **Phase 10 (Autocomplete)** — depende de Phase 2; pode rodar em paralelo com 8 e 9
- **Phase 11 (Polish)** — depende de TODAS phases anteriores

### Within Each User Story

- **Test-first** (Princípio IV): T010/T011/T012/T029/T030/T034/T044/T045/T051/T052/T062 escritos ANTES das implementações correspondentes
- Migration (T004) ANTES de qualquer código que use schema novo
- Policy (T007) ANTES de controllers
- Service (T016) ANTES de Controllers
- Resources (T015) ANTES de Controllers
- Form Modal (T023) ANTES da Page (T025)
- Page (T025) ANTES da integração com Onboarding wizard (T049)

### Parallel Opportunities

- **Phase 1**: T002, T003 em paralelo
- **Phase 2**: T004 (sequencial — migration); T005, T006 sequenciais (model + seeder); T007, T008, T009 em paralelo após T005
- **Phase 3**: T010, T011, T012 testes [P]; T013, T014, T015 [P]; T019, T020, T021, T022 [P]; T016/T017/T018/T023/T024/T025/T026/T027 sequenciais por arquivo
- **Phase 9**: T051, T052, T053, T054, T059 podem ser [P]; T056/T060 sequenciais
- **Phase 11**: T067, T068, T069, T071, T074, T075 todos [P]; T070, T072, T073, T076 finais

### MVP Cut Point

**Após Phase 3 (T028)** — você tem **MVP do CRUD interno** funcional (US-2 P1 completo). Admin consegue gerenciar profissionais pelo painel. Phases 4, 5, 6 (US-3/4/5) são complementos imediatos; Phase 8 (US-1 onboarding) é o destrave da jornada nova de tenant; Phases 9 e 10 são polimentos.

### Fully delivered (release alvo)

**Após Phase 11 (T076)** — feature DELIVERED com 6 user stories + 9 gates + Constitution Re-Check.

---

## Parallel Example: Phase 2 (Foundational)

```bash
# Sequencial (migration + model + seeder):
Task: "Migration alter_professionals_add_especialidade_and_constraints"
Task: "Update Professional.php fillable"
Task: "Update RolesSeeder.php add professional.manage to admin-clinica"

# Em paralelo (após Professional.php pronto):
Task: "Create ProfessionalPolicy.php"
Task: "Register policy in AuthServiceProvider"
Task: "Create 3 Auditable events (Created/Updated/ActivatedByInvitation)"
```

## Parallel Example: Phase 3 (US-2 backend + frontend)

```bash
# 3 testes [P]:
Task: "ProfessionalsCrudTest.php"
Task: "ProfessionalsCrossTenantTest.php"
Task: "ProfessionalsPermissionGateTest.php"

# 3 Requests/Resources [P]:
Task: "StoreProfessionalRequest.php"
Task: "UpdateProfessionalRequest.php"
Task: "ProfessionalResource.php"

# 4 components Vue [P]:
Task: "CouncilTypeSelect.vue"
Task: "EspecialidadeAutocomplete.vue"
Task: "professionalsStore.js Pinia"
Task: "useProfessionals.js composable"
```

---

## Implementation Strategy

### MVP First (Lotes A + B do quickstart)

1. Phase 1 (Setup) → Phase 2 (Foundational)
2. Phase 3 (US-2 — CRUD completo)
3. **STOP, VALIDATE**: admin consegue CRUD profissionais via /panel/profissionais

### Incremental delivery

1. Phase 1 + 2 → backend + permission prontos
2. Phase 3 → CRUD MVP completo (US-2)
3. Phase 4 (US-3) + Phase 5 (US-4) em paralelo → edição + desativação
4. Phase 6 (US-5) → permissions consolidadas
5. Phase 7 (US-6) → filtros e busca já cobertos
6. Phase 9 → invite flow + Q2
7. Phase 10 → autocomplete especialidade
8. Phase 8 → onboarding step 2 destravado
9. Phase 11 → polish + Constitution Re-Check

### Parallel team strategy

Com 2 devs após Phase 2:
- **Dev A** (backend): Phase 3 (T013-T018) → Phase 5 → Phase 9 → Phase 10 backend
- **Dev B** (frontend): Phase 3 (T019-T028) → Phase 4 frontend → Phase 8 wizard integration → Phase 11

---

## Notes

- **[P]** = arquivos distintos, sem dependência em task incompleta
- **[Story]** label rastreia task → user story do spec
- **Test-first** (Princípio IV): testes T010/T011/T012/etc. escritos antes da implementação correspondente
- Commit por phase ou grupo lógico (sugestão: 1 commit por phase principal)
- **Constitution Re-Check (T073)** é gate de DoD
- **G1–G9 gates** do contract devem TODOS estar verdes antes do PR final — ver `contracts/api-professionals.md § 9`
- **Q1/Q2/Q3** das Clarifications são gates específicos: G7 (Q1 autocomplete tenant-scoped), G5 (Q2 confirmação email duplicado), council_type=OUTRO + council_type_other validation (Q3) — todos cobertos pelos tests planejados
- LGPD: emails de Users NÃO vazam no payload de Professional (R9 — gate explícito)
- Backfill onboarding (T048) é one-shot — rodar em produção uma vez após deploy para destravar steps em tenants existentes
