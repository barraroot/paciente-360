# Contract — Kanban Pipeline Mapping (US3)

CRUD do mapping evento→coluna do funil, por tenant. Permite a cada clínica customizar para qual coluna do funil cada evento de domínio leva o card automaticamente. Middleware obrigatório: `['auth:sanctum', 'tenant.slug', 'tenant.not-suspended']`. Permission Spatie: `funil.manage`.

---

## 1. `GET /api/v1/kanban/pipeline-mappings` — listar mappings do tenant

### Response 200

```json
{
  "data": [
    {
      "event_kind": "lead_created",
      "event_kind_label": "Lead criado pelo canal",
      "funil_coluna": { "id": 12, "nome": "Novos Leads", "slug": "new" },
      "is_active": true
    },
    {
      "event_kind": "slot_held",
      "event_kind_label": "Slot reservado (hold) pela IA",
      "funil_coluna": { "id": 18, "nome": "Agendado", "slug": "agendado" },
      "is_active": true
    }
    /* ... 7 total ... */
  ]
}
```

`event_kind_label` é a tradução PT-BR vinda de `lang/pt_BR/kanban.php`.

---

## 2. `PUT /api/v1/kanban/pipeline-mappings/{event_kind}` — atualizar 1 mapping

### Request

```json
{
  "funil_coluna_id": 22,
  "is_active": true
}
```

### Response 200

```json
{
  "data": {
    "event_kind": "slot_held",
    "funil_coluna": { "id": 22, "nome": "Confirmação pendente", "slug": "confirmacao-pendente" },
    "is_active": true
  }
}
```

### Errors

| HTTP | Code | Quando |
|---|---|---|
| 404 | `event_kind_invalid` | event_kind não está no enum |
| 422 | `funil_coluna_invalid` | coluna não pertence ao tenant OU é terminal e o event_kind não é terminal-friendly |
| 409 | `requires_initial_column` | Tentativa de desativar mapping `lead_created` sem coluna alternativa |

---

## 3. `POST /api/v1/kanban/pipeline-mappings/restore-defaults` — reset

Restaura os 7 mappings ao default seedado (R7). Útil quando tenant errou ao customizar.

### Response 200

```json
{ "data": { "restored": 7 } }
```

---

## 4. `GET /api/v1/kanban/curation-events` — histórico de mutações automáticas

Pagina eventos de auto-curadoria (FR-022). Permission: `paciente.view` (mesmo controle de prontuário).

### Query params

- `paciente_id` (optional): filtra por card
- `event_kind` (optional)
- `source` (optional)
- `from`, `to` (ISO 8601)
- `per_page` (default 20)

### Response 200

```json
{
  "data": [
    {
      "id": 88123,
      "paciente": { "id": 145, "display_name": "Maria" },
      "event_kind": "slot_held",
      "event_kind_label": "Slot reservado (hold) pela IA",
      "source": "ia_tool",
      "from_coluna": { "id": 14, "nome": "Negociando" },
      "to_coluna": { "id": 18, "nome": "Agendado" },
      "applied": true,
      "suppression_reason": null,
      "field_changed": null,
      "value_before": null,
      "value_after": null,
      "actor": { "type": "ia", "id": null },
      "turn_version": 3,
      "created_at": "2026-05-30T15:08:42Z"
    },
    {
      "id": 88124,
      "paciente": { "id": 145, "display_name": "Maria" },
      "event_kind": "profile_updated",
      "source": "ia_tool",
      "from_coluna": null,
      "to_coluna": null,
      "applied": true,
      "field_changed": "complaint",
      "value_before": null,
      "value_after": "Enxaqueca há 3 meses",
      "actor": { "type": "ia", "id": null },
      "created_at": "2026-05-30T15:09:11Z"
    }
  ],
  "meta": { "current_page": 1, "total": 412 }
}
```

---

## 5. `POST /api/v1/pacientes/{paciente}/promote-to-kanban` — promover paciente existente para kanban (FR-011a)

Endpoint dedicado para o caso Q-clarify-3=B: paciente regular (não-lead) que conversa por canal e o operador decide criar uma nova oportunidade.

### Request

```json
{
  "reason": "Paciente quer procedimento estético novo (cliente terapêutica recorrente)",
  "funil_coluna_id": 12
}
```

### Response 201

```json
{
  "data": {
    "paciente_id": 145,
    "promoted_to_kanban": true,
    "funil_coluna": { "id": 12, "nome": "Novos Leads" },
    "curation_event_id": 88200
  }
}
```

### Errors

| HTTP | Code | Quando |
|---|---|---|
| 409 | `already_in_pipeline` | Paciente já está em uma coluna não-terminal |
| 403 | `permission_denied` | Sem `funil.manage` |

---

## Eventos de domínio (já existentes ou novos)

Listeners (R7) escutam estes eventos para disparar `KanbanAutoTransitionService`:

| Evento | Fase | Mapping |
|---|---|---|
| `InboundMessageReceived` | 3 | `lead_created` (se novo) |
| `AiQualificationStarted` (novo) | 18 | `qualification_started` |
| `AiValueAccepted` (novo) | 18 | `value_accepted` |
| `SlotReservation::created` (holder_type=ia) | 5 | `slot_held` |
| `AppointmentConfirmed` | 5 | `reservation_confirmed` |
| `AiAssignmentEscalatedToHuman` | 15 | `ai_paused_to_human` |
| Schedule `kanban:downgrade-inactive` (cron diário) | 18 | `inactivity` |
