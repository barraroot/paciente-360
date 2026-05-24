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

---

## Atualização 2026-05-24 — gaps fechados (commits `d2a3d99`, `ff9e21e`)

Os "próximos passos" acima foram entregues:

- ✅ `OnboardingService::unlockStep` + triggers (T046/T047) — já existiam; cobertos agora por testes.
- ✅ Wizard integration (T049) — `ProfessionalFormModal` embarcado no `OnboardingWizardPage`.
- ✅ Testes G1–G9 + CRUD + unit (T010/T029/T034/T040/T043/T044/T045/T052/T062): 7 arquivos novos.
- ✅ Fixes a11y: `DeactivateConfirmModal` (FR-015/032) + `EmailAlreadyUserModal` (FR-005a) — substituem `window.confirm()`.
- ✅ Backfill command `onboarding:backfill-unlocks` (T048) — idempotente, `--dry-run`, com 4 testes.

**Suíte full**: 1577 tests / 1572 passed / 0 failures (5 skipped, 1 incomplete, 5 risky — pré-existentes). Build verde. Pint clean.

### Constitution Re-Check (T073) — 7/7 PASS mantido

| Princípio | Status | Nota |
|---|---|---|
| I. Privacidade/LGPD | ✅ PASS | `ProfessionalResource` não vaza email do user vinculado (teste `test_resource_does_not_expose_linked_user_email`); `check-email` retorna só id+name. |
| II. Isolamento Multi-Tenant | ✅ PASS | Gates G1/G7/G9 verdes; backfill itera tenants e usa `unlockStep` escopado por tenant. |
| III. Segurança Clínica/IA | ✅ N/A | Feature não interage com IA. |
| IV. Spec-Driven/Test-First | ✅ PASS | 8 arquivos de teste novos; suíte full sem regressão. |
| V. Observabilidade | ✅ PASS | Eventos auditáveis inalterados; backfill loga cada unlock. |
| VI. Conformidade Meta | ✅ N/A | Sem disparo em canal externo. |
| VII. Segurança Operacional | ✅ PASS | Gate `professional.manage` (G2) cobre check-email/autocomplete/activate; modais a11y substituem `window.confirm()`. |

### Ainda pendente (manual / fora de escopo desta sessão)

- ⏳ T067 — Audit a11y axe/Lighthouse (manual, requer browser)
- ⏳ T072 — Smoke browser real nas 3 personas (manual)
- ⏳ T076 — `.specify/feature.json` → DELIVERED (gated em T067/T072)
- ⛔ T020/T022/T024 — skipped-by-design: `useProfessionals.js`, `EspecialidadeAutocomplete.vue`, `ProfessionalsTable.vue` já funcionam via store + `<datalist>` + tabela inline; rebuild não agrega valor funcional.

---

## Smoke HTTP 2026-05-24 (via curl — T072 parcial)

Sem ferramenta de browser disponível (sem Playwright), foi feito smoke **HTTP-level**
contra o stack real (`localhost:8088`, tenant `flowsys`, Bearer + `X-Tenant-Slug`),
exercitando middleware (`auth:sanctum`/`tenant.slug`/`tenant.not-suspended`), gate
`professional.manage`, routing e serialização.

**Resultado: 11/11 verde** após corrigir o ambiente:
1. GET list → 200 · 2. POST vincular user → 201 (sem vazar email) · 3. GET show → 200 ·
4. PUT update → 200 · 5. PUT com `user_id` → 422 (proibido) · 6. POST conselho duplicado
→ 422 (UNIQUE) · 7. GET autocomplete → 200 · 8. POST check-email → 200 (sem email) ·
9. DELETE desativar → 204 · 10. lista ativos não mostra o desativado · 11. POST activate → 200.
OUTRO sem `council_type_other` → 422. Onboarding `state` → 200.

### ⚠️ Achados de PROVISIONAMENTO/DEPLOY (não são defeitos de código — o código está correto)

1. **Migrations não aplicadas no DB de dev**: `2026_05_24_000001` (+ várias da Fase 8) estavam
   `Pending`. Smoke dava 500 (`column "especialidade" does not exist`). **Fix de deploy:
   `artisan migrate`** (aplicado durante o smoke).
2. **Permission `professional.manage` ausente + não atribuída ao role admin-clinica**: tenants
   existentes davam **403** generalizado. RolesSeeder (T006) já contém a permission, mas precisa
   ser reexecutado. **Fix de deploy: `artisan db:seed --class=RolesSeeder` + `artisan
   permission:cache-reset`** (cache do Spatie não enxerga permission nova sem reset).
3. **`APP_LOCALE=en` no dev**: mensagens de validação backend (`lang/pt_BR/professionals.php` →
   ex.: `council_duplicate`) retornam a chave crua em vez do texto PT. Pré-existente e
   transversal a todos os módulos. **Fix: `APP_LOCALE=pt_BR`** (ou mover strings p/ locale ativo).

### Ainda pendente (browser real)
- Smoke visual/UX nas 3 personas (renderização Vue, modais a11y na prática)
- T067 audit a11y axe/Lighthouse
