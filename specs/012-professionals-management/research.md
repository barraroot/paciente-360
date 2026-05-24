# Research — Gestão de Profissionais (012)

**Status**: Complete | **Date**: 2026-05-23

Todas as decisões técnicas para destravar Phase 1 consolidadas. Zero `NEEDS CLARIFICATION` herdados.

---

## R1 — Schema delta: ALTER `professionals` (não criar nova tabela)

**Decision**: Adicionar 2 colunas e 1 UNIQUE constraint à tabela `professionals` existente:

```sql
ALTER TABLE professionals
  ADD COLUMN especialidade VARCHAR(100) NULL,
  ADD COLUMN council_type_other VARCHAR(50) NULL;

CREATE UNIQUE INDEX professionals_council_unique_per_tenant
  ON professionals (tenant_id, council_type, council_number, council_state)
  WHERE deleted_at IS NULL;
```

**Rationale**:
- Tabela já existe da Fase 1 com colunas básicas (`name, council_type, council_number, council_state, is_active, user_id, soft_deletes`). Reaproveitar evita migração de dados.
- `especialidade VARCHAR(100)` — autocomplete livre conforme Q1; campo opcional (`NULL`).
- `council_type_other VARCHAR(50)` — preenchido APENAS quando `council_type='OUTRO'` (Q3). Validação em FormRequest força esse acoplamento.
- UNIQUE PARCIAL `WHERE deleted_at IS NULL` — permite reuso do número após soft-delete (médico saiu da clínica e voltou anos depois; ou correção de cadastro errado).
- `tenant_id` na chave UNIQUE — mesmo conselho pode existir em clínicas diferentes (médico atende múltiplas clínicas).

**Alternatives considered**:
- *UNIQUE total (sem `WHERE`)*: bloquearia reuso pós soft-delete; pior UX.
- *UNIQUE sem `tenant_id`*: incorreto — viola caso real de médico em múltiplas clínicas.
- *Tabela separada `especialidades`*: over-engineering para uma string de 100 chars que se beneficia mais de autocomplete histórico que de FK.

---

## R2 — UNIQUE composto + comportamento "Outro" (Q3)

**Decision**: Quando `council_type='OUTRO'`, o campo `council_type_other` é obrigatório (validação no Request); a tupla UNIQUE não inclui `council_type_other`. Isso significa que dois profissionais com mesmo número/UF mas com `council_type_other` diferentes (ex.: "CREFITO" e "CFN") **violam o UNIQUE** se estiverem com `council_type='OUTRO'` no mesmo tenant.

**Trade-off aceito**: na prática, a colisão "Outro + número + UF" entre conselhos diferentes é altamente improvável (números de CREFITO são distintos de CFN; cada conselho tem sua faixa). Quando ocorrer, o admin é notificado e pode resolver caso a caso. Não vale complicar o UNIQUE com 5 colunas.

**Validação visível ao admin**:
- 422 explícito quando UNIQUE viola: `"Já existe um profissional cadastrado com este conselho."`
- Mensagem mostra o nome do profissional existente (sem expor dados sensíveis) para facilitar localização.

**Alternatives considered**:
- *UNIQUE incluindo `council_type_other`*: tecnicamente mais correto mas adiciona overhead; ganho marginal.
- *Sem UNIQUE constraint, só validação de aplicação*: viola defesa em profundidade.

---

## R3 — Permission `professional.manage` no Spatie

**Decision**: Adicionar 1 permission name `professional.manage` à seeder de roles. Atribuir APENAS ao role `admin-clinica`. Médico, recepcionista, atendente, financeiro NÃO recebem.

**Rationale**:
- Pattern já estabelecido em todas as fases anteriores (`prescription.view`, `webhook.manage`, etc.).
- Granularidade adequada: uma única permission cobre CRUD + activate/deactivate (não há caso de uso para split em `professional.view`, `.create`, `.update`, `.delete` separados — quem gerencia, gerencia tudo).
- Médicos não devem ver/editar dados de colegas (privilégio mínimo).

**Implementation no seeder**:
- Adicionar `'professional.manage'` ao array de permissions do role `admin-clinica` em `database/seeders/RolesSeeder.php`.
- Test `ProfessionalsPermissionGateTest::test_medico_role_does_not_have_professional_manage_permission`.

**Alternatives considered**:
- *Split granular* (`professional.view`, `.create`, `.update`, `.delete`): over-engineering; nenhum caso real demanda separação.
- *Reusar `user.manage`*: errado — User é maior que Professional; permissions distintas.

---

## R4 — Fluxo de Invite + Ativação Automática (FR-007, FR-036)

**Decision**: Quando `POST /professionals` recebe `email` (sem `user_id`), o `ProfessionalInvitationService`:
1. Verifica se email já existe como User do tenant → se sim, lança erro 422 indicando que admin deve usar fluxo de "vincular usuário existente" via `/professionals/check-email` (R7).
2. Verifica se email existe como User em OUTRO tenant → 422 "Email já cadastrado em outro tenant" (Princípio II).
3. Caso contrário, cria `Professional` com `is_active=false, user_id=NULL`, depois cria `Invitation` (Fase 4) com role `medico` + payload `{ professional_id: $newId }`.
4. Listener `ActivatePendingProfessionalOnInvitationAccepted` escuta `Invitation\InvitationAccepted` (já existe — Fase 4):
   - Lê `payload.professional_id`
   - Atualiza `professional.user_id = invitation.acceptedByUser.id` + `is_active = true`
   - Dispara `ProfessionalActivatedByInvitation` (Auditable) para audit log

**Rationale**:
- Reusa pattern Fase 4 sem duplicação.
- Payload da Invitation carrega `professional_id` — pattern já usado para outras invitations (ex.: invite com pre-assignment).
- Ativação automática é evento de domínio (auditable) — não ação humana direta; distinção registrada em audit log via evento dedicado (FR-036).

**Alternatives considered**:
- *Ativação manual após aceite*: gera passo extra para o admin sem ganho real (já houve confirmação no aceite do email).
- *Criar User com password vazia*: viola Princípio VII (todo User precisa de credencial válida ou ser via SSO/invite).

---

## R5 — Onboarding `unlockStep` + Triggers (FR-024, FR-025, FR-026)

**Decision**: Estender `OnboardingService` com:
- Método público `unlockStep(Tenant $tenant, string $stepKey): void` que muta o status persistido do step de `locked` → `pending`.
- Trigger automático em `completeStep`:
  - Step `clinic_data` completed → unlock `first_professional`
  - Step `first_professional` completed → unlock `schedule_setup`
- Trigger em `skipStep`: pular `first_professional` NÃO unlocka `schedule_setup` (FR-026 explícito).

**Implementation**:
```php
public function completeStep(Tenant $tenant, string $stepKey, array $payload): array
{
    // ... lógica existente
    
    // Trigger automático de unlock
    if ($stepKey === 'clinic_data') {
        $this->unlockStep($fresh, 'first_professional');
    } elseif ($stepKey === 'first_professional') {
        $this->unlockStep($fresh, 'schedule_setup');
    }
    
    return $this->getState($fresh);
}
```

**Rationale**:
- Mudança aditiva — `STEPS` const não muda (steps continuam declarando status inicial `locked` para 2, 3, 4, 5).
- `unlockStep` é idempotente (chamar 2x não quebra; status `pending` → `pending` é no-op).
- Trigger no `completeStep` torna a regra de progressão **explícita e local** — leitor do service entende o fluxo sem precisar caçar listeners.

**Alternatives considered**:
- *Listener em `OnboardingStepCompleted` event*: indireto; mais difícil de raciocinar; ganho zero.
- *Configuração declarativa no `STEPS` const* (`unlocks: ['first_professional']`): elegante mas overhead para apenas 2 transições.

---

## R6 — Modal de Confirmação para Email Duplicado (Q2 / FR-005a)

**Decision**: Endpoint dedicado `POST /api/v1/professionals/check-email` recebe `{email}` e retorna:

```json
{
  "exists_in_current_tenant": true|false,
  "existing_user": { "id": 12, "name": "João Silva" } | null,
  "exists_in_other_tenant": true|false
}
```

Frontend chama esse endpoint quando o admin **desfocaliza o campo email** no formulário (onblur). Se `exists_in_current_tenant === true`, exibe `EmailAlreadyUserModal.vue` antes de permitir o submit. Modal tem 2 botões: "Vincular" (continua com user_id pré-preenchido) e "Cancelar" (volta ao form para outro email).

**Rationale**:
- Endpoint isolado evita poluir o `POST /professionals` com lógica de confirmação multi-passo.
- Resposta também sinaliza `exists_in_other_tenant` para o frontend mostrar mensagem distinta (cross-tenant bloqueado, Princípio II).
- Reuso de scope: o endpoint **NÃO** retorna User completo, apenas `{id, name}` mínimo — não vaza PII (email do user existente NÃO é retornado).

**Alternatives considered**:
- *Validação dentro do `POST /professionals` retornando 409*: confunde lógica de validation com lógica de "preview".
- *Frontend valida puramente local*: impossível — não tem acesso à lista completa de Users.

---

## R7 — Autocomplete de Especialidade (Q1 / FR-001)

**Decision**: Endpoint `GET /api/v1/professionals/especialidades?q=card` retorna lista de especialidades já cadastradas no tenant que contêm o termo. Query simples:

```sql
SELECT DISTINCT especialidade FROM professionals
WHERE tenant_id = :tenant_id
  AND deleted_at IS NULL
  AND especialidade IS NOT NULL
  AND especialidade ILIKE :term
ORDER BY especialidade ASC
LIMIT 10;
```

Cache Redis 60s opcional com chave `professionals:especialidades:{tenant_id}` (lista completa sem termo) + filter no frontend.

**Rationale**:
- DISTINCT em coluna VARCHAR(100) é leve (índice secundário não é justificável para essa cardinalidade).
- Cache 60s amortece queries durante edição contínua do form (admin digita várias letras → vários requests).
- Sem `q` parâmetro: retorna lista completa (até 50) para popular sugestões iniciais.

**Alternatives considered**:
- *Tabela separada `especialidades`*: over-engineering para a cardinalidade esperada (~20 valores únicos por tenant).
- *Cache Redis com TTL longo (24h)*: stale durante criação ativa; 60s é equilíbrio entre freshness e load.

---

## R8 — `ProfessionalService` orquestrador + eventos auditáveis (FR-034)

**Decision**: Service único `ProfessionalService` com 4 métodos públicos:
- `create(array $data): Professional` — vincula a user existente OU delega ao `ProfessionalInvitationService`
- `update(Professional $p, array $data): Professional` — emite `ProfessionalUpdated` se houve mudança real
- `deactivate(Professional $p): Professional` — set is_active=false, dispara `ProfessionalDeactivated` (já existente)
- `activate(Professional $p): Professional` — set is_active=true, sem evento dedicado (não dispara reatribuição reversa)

**Rationale**:
- Pattern já estabelecido (`PrescriptionService`, `CampaignService`, etc.).
- Eventos auditáveis: `ProfessionalCreated`, `ProfessionalUpdated`, `ProfessionalActivatedByInvitation` (novos); `ProfessionalDeactivated` (reuso Fase 5).
- Reativação NÃO emite evento dedicado porque é operação inversa idempotente — admin pode toggle várias vezes; audit log padrão (via `RecordsAuditableEvents` listener da Fase 1) cobre.

**Alternatives considered**:
- *Lógica nos controllers diretamente*: viola SRP; dificulta teste unit.

---

## R9 — Resource expõe campos seguros, sem `email` do User vinculado

**Decision**: `ProfessionalResource` expõe:

```json
{
  "id": 42,
  "name": "Dr. Carlos Santos",
  "council_type": "CRM",
  "council_type_other": null,
  "council_number": "123456",
  "council_state": "SP",
  "especialidade": "Cardiologia",
  "is_active": true,
  "user": {
    "id": 17,
    "name": "Carlos Santos"
  },
  "created_at": "2026-05-23T14:30:00Z"
}
```

**NÃO expõe email do user vinculado** — admin pode acessar email via `/users/{id}` se precisar (princípio I: minimização de PII em payloads agregados).

**Rationale**:
- Email já está disponível em outra surface (lista de Usuários) — repetir aqui amplia surface de PII sem ganho.
- Frontend de lista de profissionais não exibe email — só nome do User vinculado (visual + clickable se aplicável).

**Alternatives considered**:
- *Incluir email com `whenNotNull`*: tentação por conveniência; viola minimização.

---

## R10 — UI: Modal vs Página Dedicada para Form

**Decision**: Modal (`ProfessionalFormModal.vue`) em vez de página dedicada `/panel/profissionais/novo`. Reusos:
- Standalone: chamado via botão "Novo profissional" na `ProfessionalsListPage.vue`
- Edição: chamado via botão "Editar" da tabela com `professional` pré-populado
- Onboarding: embarcado no step 2 do `OnboardingWizardPage.vue`

**Rationale**:
- Reuso máximo (3 lugares diferentes consomem o mesmo modal).
- Padrão usado em outras telas do projeto (Inbox AssignDialog, Prescription CancelModal, etc.).
- A11y já resolvida com Teleport + focus trap (composables do spec 009).
- Modal preserva contexto da lista (admin não perde scroll position ao abrir/fechar).

**Alternatives considered**:
- *Página dedicada `/profissionais/novo`*: força navegação; perde contexto da lista; reuso no wizard fica awkward.

---

## R11 — Reatribuição de Pacientes ao Desativar (FR-012)

**Decision**: Reusar **100% do comportamento existente**. Quando `ProfessionalService::deactivate()` atualiza `is_active: true → false`, o observer já presente no `Professional.boot()` (Fase 2) dispara `ProfessionalDeactivated`. Listener `EnqueueReassignOrphansListener` (ou similar — verificar nome exato no código) enfileira `ReassignOrphansJob`.

**Verificação adicional**: o teste `ProfessionalDeactivationReassignsTest` valida que o job é enfileirado quando o profissional é desativado via nova API.

**Rationale**:
- Comportamento crítico já testado e em produção (Fase 2).
- Spec não introduz mudança de lógica — apenas garante que a nova superfície de UI (desativar via dashboard) aciona o mesmo caminho.

**Alternatives considered**:
- *Reimplementar reatribuição*: violação clara de "don't repeat".
- *Síncrono ao invés de job*: pode demorar para tenants com 1000+ pacientes; assíncrono é correto.

---

## R12 — Pinia Store `professionalsStore` + Composable `useProfessionals`

**Decision**: Criar Pinia store dedicada `professionalsStore.js` com:
- `state`: `{ list: [], filters: {is_active, search}, loading, error, lastFetched }`
- `actions`: `fetchList`, `create`, `update`, `deactivate`, `activate`, `checkEmail`
- Composable `useProfessionals.js` que wrap o store + adiciona debounce no search (300ms).

**Rationale**:
- Padrão do projeto: telas com tabela + form (Pacientes, Receituários, Campanhas) usam Pinia store.
- Cache leve: lista fica em memória; mutações (create/update) atualizam store sem refetch completo.
- Composable adiciona ergonomia (debounce, defaults) sem misturar concerns no store.

**Alternatives considered**:
- *Composable puro sem store* (como spec 010 fez para PanelHome): inadequado aqui porque há múltiplos consumers (list page, modal, onboarding wizard) que precisam compartilhar estado.

---

## R13 — Testes: 9 Feature + 0 Unit (com 1 exceção)

**Decision**: 9 testes Feature cobrindo:
1. `ProfessionalsCrudTest` — happy path CRUD
2. `ProfessionalsCrossTenantTest` — Princípio II (gate G1)
3. `ProfessionalsPermissionGateTest` — admin × médico × recepcionista (3 perfis)
4. `ProfessionalsCouncilUniquenessTest` — UNIQUE constraint funciona + permite reuso pós soft-delete
5. `ProfessionalInvitationFlowTest` — convite cria Invitation + Professional inativo; aceite ativa via listener
6. `ProfessionalEmailAlreadyUserTest` — Q2: endpoint check-email + modal de confirmação
7. `ProfessionalDeactivationReassignsTest` — desativar dispara ReassignOrphansJob
8. `EspecialidadesAutocompleteTest` — endpoint retorna DISTINCT do tenant + cross-tenant isolado
9. `OnboardingUnlockProgressionTest` — step 1→2 unlock + step 2→4 unlock + skip step 2 não unlocka step 4

Adicionalmente: 1 Unit `OnboardingServiceTest::unlockStep_idempotent_when_already_pending` (cobertura de borda do método novo).

**Rationale**:
- Pattern do projeto: Feature tests predominantes (Princípio IV).
- Cobertura completa dos 36 FRs + gates do contract.

**Alternatives considered**:
- *Playwright E2E* para form: pode entrar como bonus, mas não é bloqueante (UI é padrão do projeto, baixo risco).

---

## Resumo das decisões

| ID | Decisão | Impacto |
|---|---|---|
| R1 | ALTER `professionals` + UNIQUE composto PARCIAL | 1 migration, sem nova tabela |
| R2 | Comportamento "Outro": campo extra obrigatório; UNIQUE não inclui | Q3 cobertura completa |
| R3 | Permission `professional.manage` apenas em `admin-clinica` | Princípio VII |
| R4 | Listener `ActivatePendingProfessionalOnInvitationAccepted` | FR-007/FR-036 |
| R5 | `unlockStep` em OnboardingService + triggers em completeStep | FR-024/025/026 |
| R6 | Endpoint dedicado `/professionals/check-email` + modal Q2 | UX clara, sem 409 confuso |
| R7 | Endpoint `/professionals/especialidades` com cache Redis 60s opcional | Q1 |
| R8 | `ProfessionalService` único com 4 métodos + 3 eventos novos | Auditabilidade |
| R9 | Resource sem email do user vinculado | Minimização PII |
| R10 | Modal compartilhado entre standalone + edit + onboarding | DRY |
| R11 | Reuso 100% de ReassignOrphansJob da Fase 2 | Zero churn |
| R12 | Pinia `professionalsStore` + composable `useProfessionals` | Pattern do projeto |
| R13 | 9 Feature tests + 1 Unit (unlockStep idempotent) | Princípio IV |

Constitution Check 7/7 preservado em todas as decisões.
