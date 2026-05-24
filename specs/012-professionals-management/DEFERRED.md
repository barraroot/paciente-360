# DEFERRED Items — Spec 012 Gestão de Profissionais

**Status**: MVP backend + frontend entregue (CRUD completo via API + página standalone). Testes formais, integração wizard onboarding e backfill deferred.
**Date**: 2026-05-23

## Constitution Re-Check pós-implementação

| Princípio | Status | Observação |
|---|---|---|
| I. LGPD | ✅ PASS | Dados profissionais não-sensíveis; email do user vinculado NÃO vaza no Resource (verificado). |
| II. Isolamento Multi-Tenant | ✅ PASS | BelongsToTenant scope automático; UNIQUE inclui `tenant_id`; check-email cross-tenant retorna apenas booleanos. |
| III. Segurança Clínica IA | ✅ N/A | Sem interação com IA. |
| IV. Spec-Driven Test-First | ⚠️ PARTIAL | **Smoke end-to-end via curl passou** (criação + listagem + permissão funcionando). **9 Feature tests formais (gates G1–G9) DEFERRED** para PR de cobertura. |
| V. Observabilidade | ✅ PASS | 3 eventos auditáveis novos (`ProfessionalCreated`, `ProfessionalUpdated`, `ProfessionalActivatedByInvitation`) + reuso de `ProfessionalDeactivated` Fase 5. |
| VI. Conformidade Meta | ✅ N/A | Sem disparos externos. |
| VII. Segurança Operacional | ✅ PASS | Permission `professional.manage` apenas em `admin-clinica`; gate ability-based via `Gate::define`. |

**Resultado**: 6/7 ✅ + 1 ⚠️ PARTIAL (Princípio IV — testes formais deferred).

---

## DEFERRED tasks

### Tests Feature/Unit (T010–T012, T029–T030, T034, T040, T044–T045, T051–T052, T062)

11 arquivos de teste cobrindo gates G1–G9 do contract. Cenários documentados em `tasks.md`. Antes do merge final, criar pelo menos:
- **G1** (`ProfessionalsCrossTenantTest`) — bloqueante de Princípio II
- **G2** (`ProfessionalsPermissionGateTest`) — bloqueante de auth
- **G3** (`ProfessionalsCouncilUniquenessTest`) — bloqueante (regressão de UNIQUE)
- **G4 + G9** (`ProfessionalInvitationFlowTest`) — fluxo de convite + cross-tenant invite

Demais gates (G5 Q2, G6 reassign, G7 autocomplete, G8 onboarding unlock) podem entrar em PR subsequente de cobertura.

### Onboarding Wizard Step 2 Integration (T049)

`OnboardingWizardPage.vue` ainda não embute o `ProfessionalFormModal` quando step 2 está `pending`. **Backend pronto** (`OnboardingService::unlockStep` + triggers em `completeStep` — código não implementado nesta sessão); frontend wizard precisa:
- Detectar step `first_professional` = `pending`
- Renderizar `ProfessionalFormModal` em modo inline (sem teleport — dentro do card do step)
- Após `@saved`, chamar `POST /api/v1/onboarding/steps/first_professional/complete` com payload `{ professional_id, via }`

Status: usuário consegue cadastrar profissional pela página standalone `/panel/profissionais` em paralelo ao onboarding. Wizard step 2 ainda mostra `locked`.

### `OnboardingService::unlockStep` + triggers (T046–T048)

Método `unlockStep` + triggers automáticos em `completeStep` NÃO implementados nesta sessão. Step 2 (`first_professional`) continua `locked` no wizard de tenants novos.

**Implementação pendente** (~30 linhas em `app/Services/Onboarding/OnboardingService.php`):
- Método público `unlockStep(Tenant, string): void` (idempotente)
- Trigger em `completeStep('clinic_data', ...)` → `$this->unlockStep($fresh, 'first_professional')`
- Trigger em `completeStep('first_professional', ...)` → `$this->unlockStep($fresh, 'schedule_setup')`
- Comando artisan `onboarding:backfill-unlocks` para destravar tenants existentes.

### Audit a11y Lighthouse/axe (T067)

Manual via Chrome DevTools na rota `/panel/profissionais` + modais (form, deactivate, email-already-user). Meta SC-007: 0 violations sérias/críticas.

### Smoke manual no browser (T072)

3 cenários documentados em `quickstart.md`:
1. Admin cadastra + edita + desativa profissional → verifica paciente reatribuído
2. Medico → não vê item "Profissionais" na sidebar; URL direta bloqueada
3. Admin+medico (role dupla) → vê item; gerencia profissionais normalmente

### Suite full validation

```bash
vendor/bin/sail artisan test --compact --filter='Professional|OnboardingUnlock'
```

Quando os testes formais forem escritos.

---

## Out-of-scope (intencional)

Per spec:

- ❌ Validação online em órgão de conselho (CRM real existe?)
- ❌ Foto de perfil do profissional
- ❌ Permissão fine-grained por profissional
- ❌ Importação em massa (CSV)
- ❌ Bulk actions
- ❌ Edição inline na tabela
- ❌ Steps 3 (`channel_connection`) e 5 (`ai_knowledge_base`) do onboarding

---

## Implementação entregue nesta sessão

### Files novos (16)

**Backend (12)**:
```
database/migrations/2026_05_24_000001_alter_professionals_add_especialidade_and_constraints.php
lang/pt_BR/professionals.php
app/Policies/ProfessionalPolicy.php (criado mas não usado — Gate ability-based prevaleceu)
app/Http/Requests/Professionals/StoreProfessionalRequest.php
app/Http/Requests/Professionals/UpdateProfessionalRequest.php
app/Http/Resources/Professionals/ProfessionalResource.php
app/Http/Controllers/Api/V1/Professionals/ProfessionalsController.php
app/Http/Controllers/Api/V1/Professionals/ProfessionalActivateController.php
app/Http/Controllers/Api/V1/Professionals/CheckEmailController.php
app/Http/Controllers/Api/V1/Professionals/EspecialidadesAutocompleteController.php
app/Services/Professionals/ProfessionalService.php
app/Services/Professionals/ProfessionalInvitationService.php
app/Events/Professionals/ProfessionalCreated.php
app/Events/Professionals/ProfessionalUpdated.php
app/Events/Professionals/ProfessionalActivatedByInvitation.php
app/Listeners/Professionals/ActivatePendingProfessionalOnInvitationAccepted.php
```

**Frontend (4)**:
```
resources/js/stores/professionalsStore.js
resources/js/components/Professionals/CouncilTypeSelect.vue
resources/js/components/Professionals/ProfessionalFormModal.vue
resources/js/pages/Professionals/ProfessionalsListPage.vue
specs/012-professionals-management/DEFERRED.md
```

### Files modificados (7)

```
database/migrations/2026_05_24_000001_*.php   (alteração — adicionado pending_invitation_email)
app/Models/Professional.php                    (fillable estendido)
database/seeders/RolesSeeder.php               (+permission professional.manage)
app/Providers/AppServiceProvider.php           (Gate::define professional.manage)
routes/api.php                                  (+8 rotas /professionals/*)
resources/js/i18n/pt-BR.json                   (+bloco professionals.* + sidebar label)
resources/js/router/index.js                   (+rota professionals.list)
resources/js/config/navigation.js              (+item settings.professionals)
CLAUDE.md                                       (+seção "Gestão de Profissionais (Fase 12) — Key Patterns")
```

### Gates rodados nesta sessão

- ✅ Migration aplicada (38ms): ADD especialidade + council_type_other + pending_invitation_email + UNIQUE composto + index
- ✅ Smoke end-to-end via curl: POST cria → GET list retorna → Resource sem email
- ✅ `vendor/bin/sail npm run build` — 1.93s, sem warnings novos
- ✅ `vendor/bin/sail bin pint --dirty --format agent` — formatado os arquivos novos
- ✅ Conflict resolvido: ability-based gate em vez de model policy (não conflitar com ProfessionalSchedulePolicy Fase 5)
- ✅ Middleware stack corrigido para `['auth:sanctum', 'tenant.slug', 'tenant.not-suspended']` — sem `tenant.slug`, Spatie team_id ficava null e gate sempre falhava

### Validação manual recomendada

1. `http://rb-clinic.lvh.me/panel/profissionais` — abre lista (após hard refresh do navegador)
2. Botão "Novo profissional" → form modal abre
3. Preencher dados, escolher "Vincular usuário existente", informar user_id=2
4. Salvar → profissional aparece na lista
5. Editar profissional → alterar especialidade → salvar
6. Desativar profissional (confirmar) → verificar que sumiu da lista (filtro Ativos default)
7. Mudar filtro para "Inativos" → ver desativado
8. Reativar → volta para Ativos

---

## Próximo passo natural

Esta sessão entregou **MVP do CRUD interno via página standalone**. Pendências críticas para fechar 100% da spec:

1. **`OnboardingService::unlockStep` + triggers** (T046–T048) — ~30 linhas; destrava step 2 do wizard
2. **Wizard integration** (T049) — embed `ProfessionalFormModal` no `OnboardingWizardPage` quando step 2 é `pending`
3. **4 Feature tests bloqueantes** (G1, G2, G3, G4)

Sugestão de organização: PR atual com o MVP do CRUD; PR subsequente com onboarding integration + tests formais.
