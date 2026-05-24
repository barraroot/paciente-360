# Contract — API Professionals (012)

**Status**: Complete | **Date**: 2026-05-23

Contrato canônico dos 8 endpoints introduzidos. Todos sob `/api/v1/professionals` + middleware stack `['auth:sanctum', 'tenant.slug', 'tenant.not-suspended', 'throttle:api']` + permission gate `professional.manage`.

---

## 1. `GET /api/v1/professionals`

Lista paginada com filtros.

### Query params

| Param | Tipo | Default | Descrição |
|---|---|---|---|
| `is_active` | `'true' \| 'false' \| 'all'` | `'true'` | Filtro de status |
| `search` | string | — | Busca ILIKE no nome (case+accent insensitive) |
| `cursor` | string | — | Paginação cursor-based |
| `per_page` | int (1–100) | 25 | Tamanho da página |

### Response 200

```json
{
  "data": [
    {
      "id": 42,
      "name": "Dr. Carlos Santos",
      "council_type": "CRM",
      "council_type_other": null,
      "council_number": "123456",
      "council_state": "SP",
      "especialidade": "Cardiologia",
      "is_active": true,
      "user": { "id": 17, "name": "Carlos Santos" },
      "created_at": "2026-05-23T14:30:00Z",
      "updated_at": "2026-05-23T14:30:00Z"
    }
  ],
  "links": { "next": "...", "prev": null },
  "meta": { "per_page": 25 }
}
```

### Response 403

Usuário sem `professional.manage` → resposta de proibido (Princípio VII).

---

## 2. `POST /api/v1/professionals`

Cria profissional. Dois modos: vincular a User existente OU enviar convite.

### Request body — modo "vincular"

```json
{
  "name": "Dr. Carlos Santos",
  "council_type": "CRM",
  "council_number": "123456",
  "council_state": "SP",
  "especialidade": "Cardiologia",
  "user_id": 17
}
```

### Request body — modo "convite"

```json
{
  "name": "Dra. Maria Souza",
  "council_type": "CRO",
  "council_number": "789012",
  "council_state": "RJ",
  "especialidade": "Endodontia",
  "email": "maria@example.com"
}
```

### Request body — modo "vincular após confirmação Q2"

Quando admin confirma vincular user existente após modal (R6):

```json
{
  "name": "Dra. Maria Souza",
  "council_type": "CRO",
  "council_number": "789012",
  "council_state": "RJ",
  "user_id": 23,
  "confirmed_existing_user": true
}
```

### Validação

- `name` 3-150 chars
- `council_type` in:CRM,CRO,COREN,CRP,OUTRO
- `council_type_other` required_if council_type=OUTRO, max:50
- `council_number` 5-20 chars, regex `^[A-Za-z0-9.\-]+$`
- `council_state` UF brasileira válida
- `especialidade` nullable, max:100
- **XOR**: ou `user_id` (com `exists:users,id` + tenant scope), ou `email` (rfc valid)
- `email` que já é user no tenant SEM `confirmed_existing_user=true` → 409 (R6)
- `email` que é user em outro tenant → 422 com mensagem específica

### Response 201 — modo vincular

```json
{
  "data": {
    "id": 42,
    "name": "Dr. Carlos Santos",
    "is_active": true,
    "user": { "id": 17, "name": "Carlos Santos" },
    "...": "..."
  }
}
```

### Response 201 — modo convite

```json
{
  "data": {
    "id": 43,
    "name": "Dra. Maria Souza",
    "is_active": false,
    "user": null,
    "invitation": {
      "id": 91,
      "email": "maria@example.com",
      "status": "pending",
      "expires_at": "2026-05-30T14:30:00Z"
    },
    "...": "..."
  }
}
```

### Response 409 — Q2 confirmação requerida

```json
{
  "message": "Email já pertence a um usuário existente do tenant.",
  "code": "email_already_user_requires_confirmation",
  "existing_user": { "id": 23, "name": "Maria Souza" }
}
```

Frontend trata 409 abrindo `EmailAlreadyUserModal`; após confirmação, re-envia POST com `confirmed_existing_user: true`.

### Response 422 — UNIQUE violado

```json
{
  "message": "Já existe um profissional cadastrado com este conselho.",
  "errors": {
    "council_number": ["Conselho duplicado para o tipo e UF informados."]
  }
}
```

---

## 3. `GET /api/v1/professionals/{id}`

Show com eager loading de `user`. Retorna mesmo shape de `data` do endpoint 1.

### Response 404

Profissional não pertence ao tenant ou foi soft-deleted (cross-tenant invisível por design).

---

## 4. `PUT /api/v1/professionals/{id}`

Atualiza dados editáveis. **Campos imutáveis** (rejeitar se enviados): `tenant_id`, `user_id` (após criação não pode trocar — FR-010), `is_active` (só via `/activate` ou `DELETE`).

### Request body

```json
{
  "name": "Dr. Carlos R. Santos",
  "council_type": "CRM",
  "council_number": "987654",
  "council_state": "RJ",
  "especialidade": "Cardiologia Pediátrica"
}
```

### Response 200

Mesmo shape de show.

### Response 422

UNIQUE viola se admin troca número para outro já existente.

---

## 5. `DELETE /api/v1/professionals/{id}`

Soft delete + set `is_active=false`. Dispara `ProfessionalDeactivated` (Fase 5) → `ReassignOrphansJob` (Fase 2).

### Response 204

No content. Frontend atualiza lista.

### Side effects

- `is_active` → `false`
- `deleted_at` → `NOW()`
- Pacientes com `profissional_responsavel_id = $id` ficam órfãos; job de reatribuição é enfileirado.
- Audit log via `ProfessionalDeactivated` event.

---

## 6. `POST /api/v1/professionals/{id}/activate`

Reativa profissional soft-deleted. Restaura `deleted_at = NULL` + `is_active = true`.

### Response 200

```json
{
  "data": { "id": 42, "is_active": true, "...": "..." }
}
```

### Side effects

- **NÃO restaura** pacientes que foram reatribuídos durante a desativação (admin pode fazer reatribuição manual se desejar).
- Audit log via `ProfessionalUpdated` (mudança em `is_active` é diff).

---

## 7. `GET /api/v1/professionals/especialidades`

Autocomplete contra histórico do tenant (Q1).

### Query params

| Param | Tipo | Default |
|---|---|---|
| `q` | string | (opcional — sem `q`, retorna até 50 valores únicos do tenant) |

### Response 200

```json
{
  "data": [
    "Cardiologia",
    "Cardiologia Pediátrica",
    "Cardiogeriatria"
  ]
}
```

Lista DISTINCT, ordenada alfabeticamente, limit 10 com `q`, limit 50 sem `q`.

Cache Redis 60s opcional (chave `professionals:especialidades:{tenant_id}`).

---

## 8. `POST /api/v1/professionals/check-email`

Verifica se um email já pertence a User do tenant (Q2 / R6).

### Request body

```json
{ "email": "maria@example.com" }
```

### Response 200

```json
{
  "exists_in_current_tenant": true,
  "existing_user": { "id": 23, "name": "Maria Souza" },
  "exists_in_other_tenant": false
}
```

### Outras combinações

- Email não existe em lugar nenhum: `exists_in_current_tenant=false, existing_user=null, exists_in_other_tenant=false`
- Email existe em outro tenant: `exists_in_current_tenant=false, existing_user=null, exists_in_other_tenant=true` (admin recebe mensagem "Email já cadastrado em outro tenant" no frontend; FR-008)

**Privacy nota**: NÃO retorna email do user; só id + nome (já visível no /users do tenant). Princípio I (minimização) preservado.

---

## 9. Gates de validação obrigatórios

| Gate | Teste | Cenário |
|---|---|---|
| **G1** Cross-tenant | `ProfessionalsCrossTenantTest` | Admin de tenant A não vê profissionais de tenant B em nenhum endpoint |
| **G2** Permission | `ProfessionalsPermissionGateTest` | Medico/recepcionista recebem 403 em todos os endpoints CRUD |
| **G3** UNIQUE | `ProfessionalsCouncilUniquenessTest` | Mesma tupla (council_type, council_number, council_state) bloqueada; permite reuso após soft-delete |
| **G4** Invite flow | `ProfessionalInvitationFlowTest` | POST com email cria Invitation + Professional inativo; aceite ativa via listener |
| **G5** Q2 confirmação | `ProfessionalEmailAlreadyUserTest` | 409 quando email já-é-user; 201 após `confirmed_existing_user=true` |
| **G6** Reatribuição | `ProfessionalDeactivationReassignsTest` | DELETE dispara ReassignOrphansJob |
| **G7** Autocomplete tenant-scope | `EspecialidadesAutocompleteTest` | Especialidades de tenant B não aparecem para admin de A |
| **G8** Onboarding unlock | `OnboardingUnlockProgressionTest` | clinic_data done → first_professional unlocked; first_professional done → schedule_setup unlocked; first_professional skipped → schedule_setup permanece locked |
| **G9** Cross-tenant invite | `ProfessionalInvitationFlowTest::test_email_in_other_tenant_blocked` | 422 ao tentar invitar email que é user de outro tenant |

---

## 10. Versionamento

API `/api/v1/professionals/*` é nova (Fase 12). Breaking changes futuras → `/api/v2/`. Adições não-breaking (novos campos opcionais) podem entrar em `/api/v1/` sem bump.
