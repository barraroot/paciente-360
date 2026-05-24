# Implementation Plan: Gestão de Profissionais + Onboarding Step 2

**Branch**: `012-professionals-management` | **Date**: 2026-05-23 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/012-professionals-management/spec.md`

## Summary

Entregar CRUD completo de Profissionais pela API tenant + tela Vue dentro do App Shell + integração com o wizard de onboarding (desbloqueio progressivo dos steps 2 e 4). Reusa máximo da infra existente:

- **Modelo `Professional`** já existe (Fase 1) com colunas básicas. Esta spec **estende com 2 novas colunas** (`especialidade`, `council_type_other`) e **adiciona UNIQUE composto** `(tenant_id, council_type, council_number, council_state)` parcial sobre soft-delete.
- **Evento `ProfessionalDeactivated`** + **Job `ReassignOrphansJob`** já existem (Fase 2/5) e fazem reatribuição automática de pacientes — esta spec consome.
- **Fluxo de Invitation** + evento `InvitationAccepted` existem (Fase 4) — esta spec adiciona um listener que ativa Professional pendente quando o convidado aceita.
- **`OnboardingService`** existe (Fase 1) com `STEPS` constante, `completeStep`, `skipStep` — esta spec adiciona método `unlockStep(string)` + trigger automático em `completeStep` (step 1 → desbloqueia step 2; step 2 → desbloqueia step 4).
- **App Shell** (Fase 9) — adicionar item "Profissionais" no grupo "Configurações" da `navigation.js` com ability `professional.manage`.
- **RolesSeeder** — adicionar permission `professional.manage` ao role `admin-clinica`.

## Technical Context

**Language/Version**: PHP 8.5 (Laravel 13), Vue 3.5 (Composition API), JavaScript ES2022+
**Primary Dependencies**: `laravel/framework@^13`, `laravel/sanctum@^4`, `spatie/laravel-permission` (já presente), `pinia@^2`, `vue-router@^4`, `vue-i18n@^10`, `tailwindcss@^4`, `@vueuse/core@^12`. Sem novas deps.
**Storage**: PostgreSQL 18 (1 ALTER TABLE + 1 CREATE UNIQUE INDEX). Redis 7 (cache leve do autocomplete de especialidade — opcional). Sem nova migration de tabela.
**Testing**: PHPUnit Feature (predominante), Unit (para PolicyTest e UnlockStep logic). 8+ feature tests cobrindo CRUD + cross-tenant + permission gates + onboarding unlock + invite flow + email-já-é-user (Q2) + UNIQUE constraint.
**Target Platform**: SPA Vue dentro de Laravel monolith. Mobile responsivo até 360px.
**Project Type**: Web app (backend + frontend Vue SPA).
**Performance Goals**: p95 endpoints CRUD < 300ms; lista paginada com search ILIKE < 500ms para tenants até 100 profissionais; autocomplete de especialidade < 200ms (cache Redis 60s opcional).
**Constraints**: 0 violations sérias/críticas axe/Lighthouse na página (SC-007). UNIQUE constraint parcial (`WHERE deleted_at IS NULL`) para permitir reuso de números após desativação. Soft delete preserva histórico clínico (Princípio I).
**Scale/Scope**: Tenant médio: 5–20 profissionais. Tenant grande: até ~100. Endpoint lista paginada (cursor). Inserções/edições raras (~5/dia/tenant grande).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Privacidade, Consentimento e Conformidade LGPD ✅ PASS

- **Dados de profissional não são PII clínica sensível**: nome civil, conselho profissional (CRM/CRO etc.), UF — informações de identificação pública por natureza da profissão.
- **Email do User vinculado é PII** mas já está visível em `/users` (gerenciado pela Fase 4).
- **Soft delete preservado** (`SoftDeletes` no model + UNIQUE parcial `WHERE deleted_at IS NULL`): profissional desativado nunca perde dados — exigência legal de retenção em histórico clínico.
- **Sem novo PII coletado**: feature não pede CPF, telefone pessoal, endereço residencial.
- **Auditoria**: cada criação/edição/desativação/ativação-por-convite gera audit log (FR-034, FR-036).

### II. Isolamento Multi-Tenant ✅ PASS

- Model `Professional` **JÁ TEM** trait `BelongsToTenant` (Fase 1) — global scope automático.
- UNIQUE composto inclui `tenant_id`: `(tenant_id, council_type, council_number, council_state)` — mesmo conselho pode existir em clínicas diferentes (médico que atende em múltiplas clínicas é comum).
- Gate test obrigatório: `ProfessionalsCrossTenantTest` — admin do tenant A não vê profissionais do tenant B, não pode editar, não pode invitar com email de user de outro tenant (FR-008).
- Cache do autocomplete (se usado) **escopado por tenant**: chave `professionals:especialidades:{tenant_id}` TTL 60s.

### III. Segurança Clínica e Auditabilidade da IA ✅ N/A

- Feature não interage com IA.

### IV. Desenvolvimento Spec-Driven e Test-First ✅ PASS

- Spec aprovada com 31 acceptance scenarios + 3 clarifications resolvidas (Q1 autocomplete, Q2 confirmação email duplicado, Q3 enum de conselhos + "Outro").
- Testes planejados:
  - `ProfessionalsCrudTest.php` — index/store/show/update/destroy + states
  - `ProfessionalsCrossTenantTest.php` — gate G1 (Princípio II)
  - `ProfessionalsPermissionGateTest.php` — admin vs medico vs recepcionista
  - `ProfessionalsCouncilUniquenessTest.php` — UNIQUE composto bloqueia duplicata
  - `ProfessionalInvitationFlowTest.php` — email novo cria Invitation + Professional inativo; aceite ativa via listener
  - `ProfessionalEmailAlreadyUserTest.php` — Q2: confirmação explícita para vincular
  - `ProfessionalDeactivationReassignsTest.php` — desativar dispara `ReassignOrphansJob` (Fase 2)
  - `OnboardingUnlockProgressionTest.php` — step 1 completo → step 2 unlocked; step 2 completo → step 4 unlocked; step 2 skipped → step 4 NÃO unlocked
- Pint passa; 1 nova migration (ALTER) seguindo padrão do projeto.

### V. Observabilidade e Excelência Operacional ✅ PASS

- **Eventos auditáveis novos** (`Auditable`):
  - `ProfessionalCreated` (novo)
  - `ProfessionalUpdated` (novo)
  - `ProfessionalActivatedByInvitation` (novo — diferente de criação direta)
  - `ProfessionalDeactivated` (já existe — Fase 5)
- **Métricas Prometheus**: nenhuma nova necessária — operações de baixa frequência (~5/dia/tenant grande). Pode entrar em spec futura se houver demanda observacional.
- **Logs estruturados**: cada operação loga `tenant_id`, `user_id` (ator), `professional_id`, `action`.

### VI. Conformidade Meta nos Disparos ✅ N/A

- Feature não envia mensagens em canais externos. (Convite por email usa Mailable existente da Fase 4.)

### VII. Segurança Operacional ✅ PASS

- **Auth**: endpoints usam stack existente (`auth:sanctum` + `tenant.slug` + `tenant.not-suspended`).
- **Permission gate**: nova permission `professional.manage` no Spatie — apenas `admin-clinica` por default. Médico/recepcionista recebem 403 (testado em `ProfessionalsPermissionGateTest`).
- **Sem v-html** no frontend; nomes/conselhos renderizados via interpolação Vue (auto-escape).
- **CSRF**: endpoints API são Bearer-only — sem cookie session.
- **Email injection**: validação rigorosa do email no `StoreProfessionalRequest` (regra `email:rfc,dns` se DNS disponível).

**Resultado Constitution Check**: 7/7 ✅. Nenhuma violação. Sem amendment. Sem Complexity Tracking necessário.

## Project Structure

### Documentation (this feature)

```text
specs/012-professionals-management/
├── plan.md                                  # This file
├── research.md                              # Phase 0
├── data-model.md                            # Phase 1 (schema delta + entities)
├── quickstart.md                            # Phase 1
├── contracts/
│   └── api-professionals.md                 # Endpoints + payloads
├── checklists/
│   └── requirements.md                      # 12/12 PASS
└── tasks.md                                 # /speckit-tasks
```

### Source Code (repository root)

```text
database/
└── migrations/
    └── 2026_05_24_000001_alter_professionals_add_especialidade_and_constraints.php  # [NEW]

database/seeders/
└── RolesSeeder.php                          # [MOD] +permission professional.manage em admin-clinica

app/
├── Models/
│   └── Professional.php                     # [MOD] +fillable: especialidade, council_type_other
├── Http/
│   ├── Controllers/Api/V1/Professionals/
│   │   ├── ProfessionalsController.php      # [NEW] CRUD (index/store/show/update/destroy)
│   │   ├── ProfessionalActivateController.php # [NEW] POST /professionals/{id}/activate (reativar)
│   │   ├── EspecialidadesAutocompleteController.php # [NEW] GET /professionals/especialidades
│   │   └── CheckEmailController.php         # [NEW] POST /professionals/check-email (Q2)
│   ├── Requests/Professionals/
│   │   ├── StoreProfessionalRequest.php     # [NEW]
│   │   └── UpdateProfessionalRequest.php    # [NEW]
│   └── Resources/Professionals/
│       └── ProfessionalResource.php         # [NEW]
├── Policies/
│   └── ProfessionalPolicy.php               # [NEW] manage gate; viewAny aberto p/ select dropdown
├── Services/Professionals/
│   ├── ProfessionalService.php              # [NEW] create/update/deactivate/activate orchestration
│   ├── ProfessionalInvitationService.php    # [NEW] cria Invitation + Professional pendente
│   └── EspecialidadesAutocompleteService.php # [NEW] cache 60s + DISTINCT especialidade
├── Events/Professionals/
│   ├── ProfessionalCreated.php              # [NEW] Auditable
│   ├── ProfessionalUpdated.php              # [NEW] Auditable
│   └── ProfessionalActivatedByInvitation.php # [NEW] Auditable
├── Listeners/Professionals/
│   └── ActivatePendingProfessionalOnInvitationAccepted.php # [NEW]
└── Services/Onboarding/
    └── OnboardingService.php                # [MOD] +unlockStep(string); trigger em completeStep

routes/
└── api.php                                  # [MOD] +6 rotas em /professionals (CRUD + activate + autocomplete + check-email)

resources/js/
├── pages/Professionals/
│   ├── ProfessionalsListPage.vue            # [NEW]
│   └── ProfessionalFormPage.vue             # [NEW] OU modal — decisão UX no quickstart
├── components/Professionals/
│   ├── ProfessionalsTable.vue               # [NEW]
│   ├── ProfessionalFormModal.vue            # [NEW] reusado em standalone + onboarding step 2
│   ├── DeactivateConfirmModal.vue           # [NEW] modal a11y com impacto descrito
│   ├── EmailAlreadyUserModal.vue            # [NEW] Q2 — confirmação de email duplicado
│   ├── EspecialidadeAutocomplete.vue        # [NEW] Q1 — input com sugestões
│   └── CouncilTypeSelect.vue                # [NEW] dropdown 5 opções + campo "Outro" condicional
├── composables/
│   └── useProfessionals.js                  # [NEW] fetch + lista + create/update/deactivate
├── stores/
│   └── professionalsStore.js                # [NEW] Pinia store (lista + filters + cache)
├── config/
│   └── navigation.js                        # [MOD] +item settings.professionals
├── i18n/
│   └── pt-BR.json                           # [MOD] bloco professionals.*
├── pages/onboarding/
│   └── OnboardingWizardPage.vue             # [MOD] step 2 embute ProfessionalFormModal
└── router/
    └── index.js                             # [MOD] +rota /panel/profissionais

lang/pt_BR/
└── professionals.php                        # [NEW] strings backend

tests/
├── Feature/Professionals/
│   ├── ProfessionalsCrudTest.php
│   ├── ProfessionalsCrossTenantTest.php
│   ├── ProfessionalsPermissionGateTest.php
│   ├── ProfessionalsCouncilUniquenessTest.php
│   ├── ProfessionalInvitationFlowTest.php
│   ├── ProfessionalEmailAlreadyUserTest.php
│   ├── ProfessionalDeactivationReassignsTest.php
│   └── EspecialidadesAutocompleteTest.php
└── Feature/Onboarding/
    └── OnboardingUnlockProgressionTest.php
```

**Structure Decision**: Backend segue padrão estabelecido pelas fases anteriores: `app/Http/Controllers/Api/V1/{Module}/`, `app/Services/{Module}/`, `app/Http/Resources/{Module}/`, `app/Policies/{ModulePolicy}.php`. Listener em `app/Listeners/{Module}/` consome evento `InvitationAccepted` da Fase 4. Frontend cria nova subpasta `resources/js/pages/Professionals/` + `components/Professionals/`. Onboarding step 2 **reusa** o mesmo `ProfessionalFormModal.vue` — single source of truth do formulário.

## Complexity Tracking

> Nenhuma violação constitucional detectada. Esta seção fica vazia.

Sem desvios: 1 migration (ALTER + UNIQUE), 1 nova permission, 4 novos eventos (3 novos + 1 reuso), 1 listener novo, 6 endpoints novos, ~10 componentes Vue. Tudo dentro de padrões já estabelecidos no projeto.
