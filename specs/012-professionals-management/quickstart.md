# Quickstart — Gestão de Profissionais (012)

**Status**: Complete | **Date**: 2026-05-23

Guia operacional para implementar a feature.

---

## Pré-requisitos

- ✅ Branch `012-professionals-management` checked out
- ✅ Spec aprovada (37 FRs, 31 acceptance scenarios, 3 clarifications, 12/12 checklist PASS)
- ✅ Plan aprovado (Constitution Check 7/7)
- ✅ Research consolidado (13 decisões)
- ✅ Data model + contracts definidos
- ✅ Fase 1 (multitenant) + Fase 2 (CRM Pacientes) + Fase 4 (Bearer auth + Invitations) + Fase 5 (Agenda) + Spec 009 (App Shell) + Spec 010 (Home) entregues
- ✅ Sail rodando + Vite dev
- ✅ Tenant com pelo menos 1 user `admin-clinica` e 1 user `medico` para testes de permissão

---

## Ordem sugerida (Lotes)

### Lote A — Backend Foundation (migração + permission + service)

1. Migration `2026_05_24_000001_alter_professionals_add_especialidade_and_constraints.php` (R1):
   - `ALTER TABLE professionals ADD especialidade VARCHAR(100) NULL, ADD council_type_other VARCHAR(50) NULL`
   - `CREATE UNIQUE INDEX professionals_council_unique_per_tenant ... WHERE deleted_at IS NULL`
2. Atualizar `app/Models/Professional.php` fillable: `+'especialidade', +'council_type_other'`
3. `database/seeders/RolesSeeder.php` — adicionar `'professional.manage'` ao role `admin-clinica`
4. `app/Policies/ProfessionalPolicy.php` — gate `manage(User $u): bool { return $u->can('professional.manage'); }`
5. Eventos (3 novos): `app/Events/Professionals/ProfessionalCreated.php`, `ProfessionalUpdated.php`, `ProfessionalActivatedByInvitation.php` — todos `implements Auditable`
6. Listener: `app/Listeners/Professionals/ActivatePendingProfessionalOnInvitationAccepted.php` (escuta `InvitationAccepted`)
7. `app/Services/Professionals/ProfessionalService.php` (4 métodos: create, update, deactivate, activate)
8. `app/Services/Professionals/ProfessionalInvitationService.php` (cria Invitation com payload.professional_id)
9. `app/Services/Professionals/EspecialidadesAutocompleteService.php` (DISTINCT + cache Redis 60s opcional)

### Lote B — Backend Endpoints (CRUD + auxiliares)

10. `app/Http/Requests/Professionals/StoreProfessionalRequest.php`
11. `app/Http/Requests/Professionals/UpdateProfessionalRequest.php`
12. `app/Http/Resources/Professionals/ProfessionalResource.php`
13. `app/Http/Controllers/Api/V1/Professionals/ProfessionalsController.php` (5 actions: index, store, show, update, destroy)
14. `app/Http/Controllers/Api/V1/Professionals/ProfessionalActivateController.php` (POST /activate)
15. `app/Http/Controllers/Api/V1/Professionals/EspecialidadesAutocompleteController.php`
16. `app/Http/Controllers/Api/V1/Professionals/CheckEmailController.php`
17. Registrar 8 rotas em `routes/api.php` dentro do middleware stack `['auth:sanctum', 'tenant.slug', 'tenant.not-suspended']`

### Lote C — Backend Tests (9 Feature + 1 Unit)

18. `tests/Feature/Professionals/ProfessionalsCrudTest.php`
19. `tests/Feature/Professionals/ProfessionalsCrossTenantTest.php` (G1)
20. `tests/Feature/Professionals/ProfessionalsPermissionGateTest.php` (G2)
21. `tests/Feature/Professionals/ProfessionalsCouncilUniquenessTest.php` (G3)
22. `tests/Feature/Professionals/ProfessionalInvitationFlowTest.php` (G4 + G9)
23. `tests/Feature/Professionals/ProfessionalEmailAlreadyUserTest.php` (G5)
24. `tests/Feature/Professionals/ProfessionalDeactivationReassignsTest.php` (G6)
25. `tests/Feature/Professionals/EspecialidadesAutocompleteTest.php` (G7)
26. `tests/Feature/Onboarding/OnboardingUnlockProgressionTest.php` (G8)
27. `tests/Unit/Onboarding/OnboardingServiceUnlockStepTest.php` (idempotência)

### Lote D — Onboarding (unlockStep + triggers)

28. `app/Services/Onboarding/OnboardingService.php` — adicionar método `unlockStep(Tenant $t, string $stepKey): void` + triggers automáticos em `completeStep()` (clinic_data→first_professional; first_professional→schedule_setup)
29. Rodar `OnboardingUnlockProgressionTest` — todos verdes
30. Validar manualmente via tinker: completar step 1 via API, verificar que step 2 vira `pending`

### Lote E — Frontend (página standalone)

31. i18n: bloco `professionals.*` em `resources/js/i18n/pt-BR.json` (labels, validações, mensagens)
32. `resources/js/stores/professionalsStore.js` (Pinia: list + filters + actions)
33. `resources/js/composables/useProfessionals.js` (wrapper + debounce search)
34. Componentes:
    - `resources/js/components/Professionals/EspecialidadeAutocomplete.vue`
    - `resources/js/components/Professionals/CouncilTypeSelect.vue` (5 opções + "Outro" condicional)
    - `resources/js/components/Professionals/ProfessionalFormModal.vue` (shared standalone + onboarding)
    - `resources/js/components/Professionals/EmailAlreadyUserModal.vue` (Q2 confirmação)
    - `resources/js/components/Professionals/DeactivateConfirmModal.vue` (modal a11y)
    - `resources/js/components/Professionals/ProfessionalsTable.vue`
35. `resources/js/pages/Professionals/ProfessionalsListPage.vue` (orquestrador)
36. `resources/js/router/index.js` — rota `/panel/profissionais` (name `professionals.list`, meta `{ requiresAuth: true, ability: 'professional.manage', title: 'layout.sidebar.settings.professionals' }`)
37. `resources/js/config/navigation.js` — adicionar item em `settings` group

### Lote F — Onboarding Wizard Integration

38. `resources/js/pages/onboarding/OnboardingWizardPage.vue` — step 2 (`first_professional`) embute `ProfessionalFormModal` em modo inline (sem teleport); ao submeter com sucesso, chama `POST /onboarding/steps/first_professional/complete` com payload `{ professional_id, via }`
39. Adicionar botão "Pular" no step 2 (mesma UX dos outros steps opcionais)
40. Validar manualmente: registrar nova clínica → completar step 1 → ver step 2 desbloqueado → cadastrar profissional → ver step 2 marcado completo + step 4 desbloqueado

### Lote G — Polish + Gates

41. Audit a11y axe/Lighthouse na página + modais — SC-007 (0 violations sérias)
42. `vendor/bin/sail npm run build` — verde
43. `vendor/bin/sail bin pint --dirty --format agent` — passed
44. `vendor/bin/sail artisan test --compact tests/Feature/Professionals tests/Feature/Onboarding tests/Unit/Onboarding` — todos verdes
45. Smoke manual no browser: 3 cenários (admin cadastra; admin edita; admin desativa + verifica reatribuição)
46. Constitution Re-Check pós-implementação
47. `CLAUDE.md` — adicionar seção "Gestão de Profissionais (Fase 12) — Key Patterns"
48. `specs/012-professionals-management/DEFERRED.md` se houver itens não cobertos
49. Atualizar `.specify/feature.json` para DELIVERED

---

## Comandos úteis

```bash
# Subir tudo
vendor/bin/sail up -d
vendor/bin/sail npm run dev

# Rodar migration
vendor/bin/sail artisan migrate

# Smoke do endpoint
curl -s -H "Authorization: Bearer <token>" -H "X-Tenant-Slug: rb-clinic" \
  -H "Accept: application/json" \
  "http://crm.lvh.me/api/v1/professionals?is_active=all" | jq

# Tests do módulo
vendor/bin/sail artisan test --compact tests/Feature/Professionals

# Tests específicos
vendor/bin/sail artisan test --filter=ProfessionalsCrossTenantTest

# Lint
vendor/bin/sail bin pint --dirty --format agent

# Inspect UNIQUE constraint
vendor/bin/sail artisan tinker --execute '
  DB::table("information_schema.table_constraints")
    ->where("table_name", "professionals")
    ->where("constraint_type", "UNIQUE")
    ->get()->dump();
'

# Verificar permission seedada
vendor/bin/sail artisan tinker --execute '
  echo Spatie\Permission\Models\Permission::where("name", "professional.manage")->exists() ? "ok" : "missing";
'
```

---

## Critérios de pronto (DoD)

### Por user story

- [ ] **US-1** Onboarding step 2 (5 cenários — cadastro, vincular, convidar, pular, ativação automática)
- [ ] **US-2** CRUD standalone (7 cenários — sidebar, tabela, busca, filtros, novo, salvar)
- [ ] **US-3** Edição (3 cenários — pré-populado, salva, UNIQUE bloqueia duplicata)
- [ ] **US-4** Desativação/reativação (4 cenários — modal, reatribui, reativa, badge)
- [ ] **US-5** Permissões (4 cenários — admin vê, medico/recepcionista não, 403 na API)
- [ ] **US-6** Lista com filtros (4 cenários — default ativos, todos, busca, paginação)

### Gates de validação (contract)

- [ ] **G1** Cross-tenant isolado
- [ ] **G2** Permission gate (3 perfis)
- [ ] **G3** UNIQUE composto
- [ ] **G4** Invite flow + ativação automática
- [ ] **G5** Q2 confirmação email duplicado
- [ ] **G6** Reatribuição em desativação
- [ ] **G7** Autocomplete escopado por tenant
- [ ] **G8** Onboarding unlock progression
- [ ] **G9** Cross-tenant invite bloqueado

### Suite

- [ ] `vendor/bin/sail artisan test --compact tests/Feature/Professionals tests/Feature/Onboarding` — verde
- [ ] `vendor/bin/sail artisan test --compact` (full) — 0 regressão
- [ ] `vendor/bin/sail npm run build` — verde
- [ ] axe a11y na lista + modal — 0 violations críticas/sérias

---

## Rollback strategy

- Backend: revert do PR + `vendor/bin/sail artisan migrate:rollback` (1 step)
- Frontend: revert do PR (rotas e nav adicionadas somem)
- Permission: `professional.manage` permanece no banco; sem dano (pode ser limpa em seed futura)

---

## DEFERRED / Out-of-scope

Per spec:

- ❌ Onboarding steps 3 (`channel_connection`) e 5 (`ai_knowledge_base`) permanecem locked
- ❌ Validação online em órgão de conselho (CRM real existe?)
- ❌ Foto de perfil do profissional
- ❌ Permissão fine-grained por profissional
- ❌ Importação em massa (CSV)
- ❌ Bulk actions
- ❌ Edição inline na tabela

---

## Próximo comando

```
/speckit-tasks
```
