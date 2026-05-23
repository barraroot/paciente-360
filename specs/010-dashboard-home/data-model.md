# Data Model — Dashboard Home (010)

**Status**: Complete | **Date**: 2026-05-23

Esta feature **não introduz entidades persistidas** em banco de dados. O modelo de dados relevante é (1) os **view models** que o endpoint produz para o frontend (DTOs in-memory) e (2) o **esquema de persistência local** do toggle de scope. Sem migrations, sem alterações em models Eloquent existentes.

---

## 1. View Models (DTOs in-memory expostos pela API)

### 1.1 Envelope da response

```
PanelHomeResponse := {
  scope_requested: 'user' | 'clinic',
  scope_applied: 'user' | 'clinic',          // pode diferir se user perdeu permissão
  can_toggle_scope: boolean,                  // controla visibilidade do toggle
  generated_at: string (ISO 8601 UTC),
  cache_hit: boolean,                         // diagnóstico — opcional em prod
  sections: {
    kpis: KpisSection | null,                 // null + error=true se collector falhou
    upcoming_appointments: UpcomingAppointmentsSection | null,
    attention_items: AttentionItemsSection | null,
    recent_activity: RecentActivitySection | null
  }
}
```

Cada `*Section` carrega `{ data: [...], error: boolean }` para suportar degradação graceful (R13).

### 1.2 KpisSection

```
KpisSection := {
  data: {
    appointments_today: {
      total: int,
      confirmed: int,
      pending: int                            // status='scheduled' não-confirmada
    },
    conversations_pending: {
      total: int,
      unassigned: int
    },
    leads_new_7d: {
      total: int
    },
    prescriptions_expiring_30d: {
      total: int
    }
  },
  error: false
}
```

Regras (Cf. spec FR-001, FR-002, FR-004):

- `appointments_today.total` = `count(Appointment where starts_at::date = today AND status IN ('scheduled','confirmed','realizada'))` no scope efetivo.
- `appointments_today.confirmed` = subset com `status='confirmed' OR status='realizada'`.
- `appointments_today.pending` = `appointments_today.total - appointments_today.confirmed`.
- `conversations_pending.total` = `count(Conversation where status='open' AND (assignee_id IS NULL OR assignee_id = user_id_if_scope_user))`.
- `conversations_pending.unassigned` = subset com `assignee_id IS NULL`.
- `leads_new_7d.total` = `count(Paciente where funil_stage IN ('lead','qualificando') AND created_at >= now()-7d)`.
- `prescriptions_expiring_30d.total` = `count(Prescription where expires_at BETWEEN today AND today+30 AND status='active')`.

**Scope effect**:
- Quando `scope_applied='user'`: appointments filtra `professional_id = user.id`; conversations filtra `assignee_id IN (user.id, NULL)`; prescriptions filtra `emitted_by_id = user.id`; pacientes filtra `responsavel_id = user.id`.
- Quando `scope_applied='clinic'`: sem filtros adicionais além do tenant.

### 1.3 UpcomingAppointmentsSection

```
UpcomingAppointmentsSection := {
  data: UpcomingAppointment[],                // até 5
  error: false
}

UpcomingAppointment := {
  id: int,
  starts_at: string (ISO 8601),
  starts_at_local: string ('HH:mm'),          // já formatado backend para evitar TZ confusion no front
  patient_name: string,                       // truncado em 60 chars no resource
  appointment_type: string,                   // ex: "Consulta", "Retorno"
  professional_name: string,
  status: 'scheduled' | 'confirmed'
}
```

Regras (FR-005..FR-009):
- Filter: `starts_at BETWEEN now() AND now()+config('panel.upcoming_window_minutes')` AND `status IN ('scheduled','confirmed')`.
- Order: `starts_at ASC`.
- Limit: 5.
- Scope effect: `scope=user` adiciona `professional_id = user.id`; `scope=clinic` sem filtro extra.

### 1.4 AttentionItemsSection

```
AttentionItemsSection := {
  data: AttentionItem[],                      // até 5
  error: false
}

AttentionItem := {
  type: 'conversation_escalated' | 'prescription_expiring' | 'paciente_funil_stale' | 'confirmation_pending' | 'webhook_dlq',
  severity: 'danger' | 'warn' | 'info',
  title: string,                              // i18n já resolvida
  description: string,                        // já humanizada, sem PII sensível
  link: string,                               // path relativo (ex: '/panel/inbox/conversa/123')
  occurred_at: string (ISO 8601)
}
```

Severidade-tipo mapping (R6):

| Tipo | Trigger | Severity |
|---|---|---|
| `conversation_escalated` | Conversation.ai_status='escalated' AND escalated_at < now()-10min | danger |
| `prescription_expiring` | Prescription.status='active' AND expires_at BETWEEN today AND today+7 | danger |
| `paciente_funil_stale` | Paciente.funil_stage IN ('lead','qualificando','interessado','agendamento') AND last_touched_at < now()-48h | warn |
| `confirmation_pending` | ConfirmationDispatch.status='pending_manual' | warn |
| `webhook_dlq` | WebhookDelivery in DLQ AND moved_at >= now()-24h | info |

Order: `severity DESC (danger>warn>info)` → `occurred_at DESC`. Limit: 5.

Permission gates (FR-013):
- `webhook_dlq` itens **MUST** ser omitidos se `!can('webhook.manage')`.
- `confirmation_pending` itens omitidos se `!can('agenda.view')`.

### 1.5 RecentActivitySection

```
RecentActivitySection := {
  data: RecentActivityEntry[],                // até 8
  error: false
}

RecentActivityEntry := {
  id: int,                                    // audit_log.id
  actor: {
    name: string,
    initials: string                          // calculado backend: "Maria Silva" → "MS"
  },
  description: string,                        // humanizada, SEM CPF/email/telefone/clínico (R7)
  occurred_at: string (ISO 8601),
  link: string | null                         // path relativo do recurso (ou null se não aplicável)
}
```

Regras (FR-015..FR-020):
- Source: `audit_logs` da Fase 1.
- Filter: `created_at >= now()-24h` AND `user_id IS NOT NULL`.
- Allow-listed event types (decisão de design — R7):
  - `paciente.created`, `paciente.updated`, `paciente.merged`
  - `appointment.created`, `appointment.confirmed`, `appointment.realizada`, `appointment.cancelada`, `appointment.rescheduled`
  - `prescription.created`, `prescription.renewed`
  - `conversation.assigned`, `conversation.closed`
  - `tag.created`, `funil_stage.updated`
- Event types fora da allow-list NÃO aparecem (ex.: `paciente.viewed` — visualização de prontuário não vaza para a timeline pública).
- Limit: 8. Order: `occurred_at DESC`.
- Humanização via `humanizeAuditEvent(AuditLog $event): string`.

---

## 2. Esquema de persistência local (frontend `localStorage`)

### Chave separada do app-shell (R11)

```
panel_home:scope:v1
```

### Valor (JSON)

```json
{
  "rb-clinic": {
    "1": "user",
    "5": "clinic"
  },
  "outra-clinica": {
    "1": "user"
  }
}
```

### Schema

```
PanelHomeScopePrefs := {
  [tenantSlug: string]: {
    [userId: string]: 'user' | 'clinic'
  }
}
```

### Operações

| Operação | Quando | Comportamento |
|---|---|---|
| **Read** | A cada mount de `PanelHome.vue` | Lê chave, navega path `[tenantSlug][userId]`, retorna default `'user'` se ausente. |
| **Write** | Usuário alterna toggle | Escreve no path correto, re-serializa o JSON completo. |

### Invariantes

- **INV-1** (isolamento multi-tenant): chave **MUST** usar `auth.tenant.slug + auth.user.id` correntes. Princípio II via cliente.
- **INV-2** (tolerância a corrupção): JSON malformado → default `'user'` + sobrescrita silenciosa.
- **INV-3** (tolerância a localStorage indisponível): operações são no-ops; feature funciona com escolha volátil em memória até user trocar de aba.
- **INV-4** (eventual override pelo backend): se `?scope=clinic` mas user perdeu permissão, backend retorna `scope_applied='user'` + `can_toggle_scope=false`; frontend reconcilia atualizando localStorage para `'user'`.

### Sem PII

Apenas IDs internos e enum string. Compatível com Princípio I (LGPD minimização).

---

## 3. Entidades consumidas (read-only, sem modificação)

| Entity | Origem | Como é consumida |
|---|---|---|
| `App\Models\Agenda\Appointment` | Fase 5 | KPI `appointments_today` + `UpcomingAppointmentsCollector` |
| `App\Models\Agenda\ConfirmationDispatch` | Fase 5 | `AttentionItemsCollector` (`confirmation_pending`) |
| `App\Models\Paciente` | Fase 2 | KPI `leads_new_7d` + `AttentionItemsCollector` (`paciente_funil_stale`) |
| `App\Domain\Messaging\Conversation\Models\Conversation` | Fase 3 | KPI `conversations_pending` + `AttentionItemsCollector` (`conversation_escalated`) |
| `App\Domain\Prescription\Prescription\Prescription` | Fase 7 | KPI `prescriptions_expiring_30d` + `AttentionItemsCollector` (`prescription_expiring`) |
| `App\Domain\Integrations\Models\WebhookDelivery` | Fase 8 | `AttentionItemsCollector` (`webhook_dlq`) — apenas para users com `webhook.manage` |
| `App\Models\AuditLog` | Fase 1 | `RecentActivityCollector` (com allow-list de event types) |
| `App\Models\User` | Fase 1 | Resolução de `actor` na timeline; cálculo de iniciais |
| `App\Models\Tenant` | Fase 1 | Cache key + métricas |

Todas com `tenant_id` (global scope ativo) — garantia Princípio II.

---

## 4. State transitions

Não há transições de estado a modelar — feature read-only. Não criamos, alteramos ou removemos registros. As únicas mutações ocorrem em:

1. `localStorage` (cliente, schema da Seção 2)
2. Cache Redis (TTL passivo)
3. Métricas Prometheus (incrementos)

Nenhuma envolve transição de estado de entidade persistida.
