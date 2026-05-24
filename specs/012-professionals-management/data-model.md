# Data Model — Gestão de Profissionais (012)

**Status**: Complete | **Date**: 2026-05-23

Esta feature aplica **1 ALTER + 1 UNIQUE INDEX** à tabela `professionals` existente, adiciona 3 novos eventos auditáveis, 1 nova permission Spatie, e estende `onboarding_state` no Tenant. Sem nova tabela.

---

## 1. Tabela `professionals` — schema delta

### 1.1 Estado atual (Fase 1)

```
professionals
├── id                BIGINT PK
├── tenant_id         BIGINT FK → tenants
├── user_id           BIGINT FK → users (NULLABLE, ON DELETE SET NULL)
├── name              VARCHAR(150)
├── council_type      VARCHAR(10) NULL
├── council_number    VARCHAR(20) NULL
├── council_state     CHAR(2) NULL
├── is_active         BOOLEAN DEFAULT true
├── timestamps_tz
└── soft_deletes_tz   (deleted_at)
```

### 1.2 Mudanças aplicadas por esta feature

**Migration**: `2026_05_24_000001_alter_professionals_add_especialidade_and_constraints.php`

```sql
ALTER TABLE professionals
  ADD COLUMN especialidade VARCHAR(100) NULL,
  ADD COLUMN council_type_other VARCHAR(50) NULL;

CREATE UNIQUE INDEX professionals_council_unique_per_tenant
  ON professionals (tenant_id, council_type, council_number, council_state)
  WHERE deleted_at IS NULL;
```

**Notas**:
- `especialidade` é nullable (campo opcional — FR-001).
- `council_type_other` é nullable em geral, mas **OBRIGATÓRIO via FormRequest** quando `council_type='OUTRO'` (Q3 / FR-002).
- UNIQUE PARCIAL (`WHERE deleted_at IS NULL`) permite reuso do número após soft-delete.

### 1.3 Estado final pós-feature

```
professionals
├── id                  BIGINT PK
├── tenant_id           BIGINT FK → tenants
├── user_id             BIGINT FK → users (NULLABLE)
├── name                VARCHAR(150)
├── council_type        ENUM('CRM','CRO','COREN','CRP','OUTRO') (na app; coluna VARCHAR no DB)
├── council_type_other  VARCHAR(50) NULL [novo]
├── council_number      VARCHAR(20)
├── council_state       CHAR(2)
├── especialidade       VARCHAR(100) NULL [novo]
├── is_active           BOOLEAN DEFAULT true
├── created_at, updated_at, deleted_at
└── UNIQUE PARCIAL (tenant_id, council_type, council_number, council_state) WHERE deleted_at IS NULL [novo]
```

### 1.4 Validações de aplicação

| Campo | Regra |
|---|---|
| `name` | required, string, min:3, max:150 |
| `council_type` | required, in:CRM,CRO,COREN,CRP,OUTRO |
| `council_type_other` | required_if:council_type,OUTRO, string, min:2, max:50 |
| `council_number` | required, string, min:5, max:20, regex:/^[A-Za-z0-9.\-]+$/ |
| `council_state` | required, string, size:2, in:AC,AL,AM,AP,BA,CE,DF,ES,GO,MA,MG,MS,MT,PA,PB,PE,PI,PR,RJ,RN,RO,RR,RS,SC,SE,SP,TO |
| `especialidade` | nullable, string, max:100 |
| `user_id` | nullable, exists:users,id (com BelongsToTenant scope) |
| `email` (alternativa) | nullable, email:rfc, max:255 |

### 1.5 State transitions

```
[criação via user_id existente]
   |
   v
is_active = true
   ↑↓
[admin desativa]                 [admin reativa]
   |                                    |
   v                                    |
is_active = false ───────────────────────┘
   |
   v (dispara ProfessionalDeactivated → ReassignOrphansJob)
[pacientes órfãos reatribuídos]

────────────────────────────────────────────

[criação via convite por email]
   |
   v
is_active = false, user_id = NULL
   |
   v (Invitation criada com payload.professional_id)
[user aceita invite] → InvitationAccepted listener
   |
   v
is_active = true, user_id = acceptedByUser.id
   |
   v (dispara ProfessionalActivatedByInvitation)
[audit log registra ativação automática]
```

---

## 2. Eventos auditáveis (3 novos + 1 reuso)

| Evento | Quando | Auditable | Origem |
|---|---|---|---|
| `ProfessionalCreated` | `ProfessionalService::create()` sucede | ✅ | Novo (012) |
| `ProfessionalUpdated` | `ProfessionalService::update()` detecta diff | ✅ | Novo (012) |
| `ProfessionalActivatedByInvitation` | Listener processa `InvitationAccepted` com payload.professional_id | ✅ | Novo (012) |
| `ProfessionalDeactivated` | `is_active: true → false` (observer existente) | ✅ | Reuso Fase 5 |

**Payload comum** (todos os eventos):
- `professional_id`, `tenant_id`, `user_id_acted_by` (NULL se evento por listener), `timestamp`.
- `ProfessionalUpdated` adicional: `changes` (diff campo→[old,new]).
- `ProfessionalActivatedByInvitation` adicional: `invitation_id`.

Nenhum evento contém PII clínica ou de paciente (Princípio I).

---

## 3. Permission Spatie

**Nome**: `professional.manage`

**Roles que recebem** (modificação no `RolesSeeder`):
- ✅ `admin-clinica`
- ❌ `medico`, `recepcionista`, `atendente`, `financeiro` (NÃO recebem)

Aplicada via `ProfessionalPolicy` em todos os endpoints CRUD.

---

## 4. Extensão do `onboarding_state` (Tenant.onboarding_state JSON)

### 4.1 Estado atual

```json
{
  "completed": false,
  "steps": {
    "clinic_data": { "status": "pending|completed|skipped", "completed_at?": "...", "payload?": {...} },
    "first_professional": { "status": "locked|pending|completed|skipped", ... },
    "channel_connection": { "status": "locked", ... },
    "schedule_setup": { "status": "locked", ... },
    "ai_knowledge_base": { "status": "locked", ... }
  }
}
```

### 4.2 Mudanças por esta feature

- Step `first_professional` agora **transita de `locked` → `pending`** automaticamente após step `clinic_data` ser `completed` (FR-024).
- Step `schedule_setup` transita de `locked` → `pending` após step `first_professional` ser `completed` (FR-025).
- Step `first_professional`, quando `completed`, persiste em `payload`:

```json
{
  "first_professional": {
    "status": "completed",
    "completed_at": "2026-05-23T14:30:00Z",
    "payload": {
      "professional_id": 42,
      "via": "linked_user" | "invited_user"
    }
  }
}
```

Lógica implementada em `OnboardingService::completeStep()` via trigger automático (research R5).

---

## 5. Endpoints (resumo — detalhe em contracts/)

| Método | Rota | Descrição | Auth |
|---|---|---|---|
| `GET` | `/api/v1/professionals` | Lista paginada com filtro/busca | `professional.manage` |
| `POST` | `/api/v1/professionals` | Cria (vincular ou convidar) | `professional.manage` |
| `GET` | `/api/v1/professionals/{id}` | Show | `professional.manage` |
| `PUT` | `/api/v1/professionals/{id}` | Update | `professional.manage` |
| `DELETE` | `/api/v1/professionals/{id}` | Soft-delete (desativa) | `professional.manage` |
| `POST` | `/api/v1/professionals/{id}/activate` | Reativa | `professional.manage` |
| `GET` | `/api/v1/professionals/especialidades?q=...` | Autocomplete histórico | `professional.manage` |
| `POST` | `/api/v1/professionals/check-email` | Verifica se email já é User no tenant (Q2) | `professional.manage` |

---

## 6. Resource shape

`ProfessionalResource::toArray()`:

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
  "user": { "id": 17, "name": "Carlos Santos" } | null,
  "created_at": "2026-05-23T14:30:00Z",
  "updated_at": "2026-05-23T14:30:00Z"
}
```

**Princípio I**: NÃO expõe email do user vinculado, NÃO expõe deleted_at (campo interno), NÃO expõe payload de invitations pendentes.

---

## 7. Compatibilidade com features existentes

| Feature dependente | Como esta spec interage |
|---|---|
| **Agenda (Fase 5)** | Profissionais ativos aparecem em selects de criação de appointment; inativos somem. Nenhuma mudança em `Appointment.professional_id` FK. |
| **Pacientes (Fase 2)** | `Paciente.profissional_responsavel_id` FK → professionals. Desativação dispara `ReassignOrphansJob` que atualiza pacientes órfãos. |
| **Onboarding (Fase 1)** | `OnboardingService::completeStep` ganha triggers de unlock. |
| **Invitations (Fase 4)** | `Invitation.payload` carrega `professional_id`. Evento `InvitationAccepted` observado por novo listener. |
| **Dashboard Home (010)** | KPI `occupancy_by_professional` (que vem da Fase 8) e seção "Ocupação por profissional" do Executive Dashboard (Fase 11) leem `professional.is_active`. Desativação remove imediatamente do dashboard. |
| **App Shell (009)** | Item novo "Profissionais" em "Configurações"; visibilidade gated por `professional.manage`. |

Sem alteração em modelos ou tabelas dessas features.
