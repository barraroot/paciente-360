# Data Model — Entrega de Notificações Outbound (013)

2 tabelas novas. Ambas multi-tenant (`tenant_id` + global scope `BelongsToTenant`). Reusam entidades existentes (Conversation, Message, Channel, Paciente, PatientProfessionalPreference).

## Enums

### `NotificationType`
`appointment_confirmation` · `prescription_expiry_alert` · `waitlist_offer` · `cancellation_escalation` · `reschedule_limit_escalation` · `ai_renewal_task`

### `NotificationStatus`
`queued` · `sent` · `delivered` · `failed` · `pending_manual` · `skipped`

### `NotificationSkipReason` (nullable; preenchido em `skipped`/`pending_manual`)
`opt_out` · `debounced` · `no_channel` · `no_template` · `send_failed`

## Tabela: `notification_templates`

Catálogo por tenant que mapeia tipo de notificação → template aprovado do provedor.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint FK | global scope; índice |
| `notification_type` | varchar(40) | valor de `NotificationType` |
| `channel_type` | varchar(20) | `whatsapp` (Instagram não usa template proativo) |
| `provider_template_id` | varchar(120) | id/SID do template aprovado no provedor |
| `language` | varchar(10) | ex.: `pt_BR` |
| `variables_map` | jsonb | mapa `{var → fonte}` restrito à allow-list não-clínica |
| `is_active` | boolean | default true |
| `timestamps` | | |
| `deleted_at` | softdelete | reuso após remover |

**Constraints**:
- UNIQUE parcial `(tenant_id, notification_type, channel_type) WHERE deleted_at IS NULL`.
- CHECK `notification_type` ∈ enum; `channel_type` ∈ {`whatsapp`}.
- **Allow-list de `variables_map`** (validada na aplicação): `patient_name`, `appointment_datetime`, `professional_name`, `clinic_name`, `days_until_expiry`, `offer_expires_at`. Qualquer chave fora da lista → rejeição (gate LGPD).

## Tabela: `outbound_notifications`

Rastreia cada tentativa de notificar um paciente.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tenant_id` | bigint FK | global scope; índice |
| `patient_id` | bigint FK | índice |
| `notification_type` | varchar(40) | `NotificationType` |
| `milestone` | varchar(40) | marco/ocasião: `t_minus_24h`, `t_minus_2h`, `d_15`, `d_7`, `d_1`, `offer`, `escalation` |
| `status` | varchar(20) | `NotificationStatus`; default `queued` |
| `skip_reason` | varchar(30) nullable | `NotificationSkipReason` |
| `channel_id` | bigint FK nullable | canal escolhido (null se pending_manual/no_channel) |
| `conversation_id` | bigint FK nullable | conversa usada |
| `notification_template_id` | bigint FK nullable | template usado (null se dentro da janela com texto livre) |
| `message_id` | bigint FK nullable | mensagem enviada (liga reconciliação de status) |
| `source_type` | varchar | morph: Appointment / Prescription / WaitlistEntry |
| `source_id` | bigint | morph id |
| `sent_at` / `delivered_at` / `failed_at` | timestamptz nullable | marcos de status |
| `timestamps` | | |

**Constraints**:
- UNIQUE parcial de idempotência `(tenant_id, patient_id, notification_type, milestone, (created_at::date)) WHERE status <> 'skipped'` — impede duplicar o mesmo aviso no mesmo dia (FR-014). Marcos distintos não colidem.
- Índice `(tenant_id, status)` para o painel/métricas e o filtro de pendências manuais.
- FKs com `ON DELETE SET NULL` para `message_id`/`conversation_id`/`channel_id`/`notification_template_id` (preserva histórico de auditoria).

## Transições de estado

```
queued ──► sent ──► delivered        (sucesso; via status callback)
   │         └────► failed ──► pending_manual   (falha definitiva pós-retry)
   ├──► skipped(opt_out | debounced)            (terminal, antes de resolver canal)
   └──► pending_manual(no_channel | no_template | send_failed)  (terminal acionável)
```

- `skipped` e `pending_manual` são **terminais**.
- `delivered`/`failed` chegam assíncronos pelo callback do provedor (R7).

## Entidades reutilizadas (sem alteração de schema)

- **Conversation** (`patient_id`, `channel_id`, `external_thread_id_normalized`, `status`, `priority`, `last_inbound_message_at`) — passa a poder ser aberta por envio proativo (R2).
- **Message** (`sender_type` ∈ patient|user|system|ai, `content_type`, `idempotency_key`, `status`) — `system` usado no fallback manual (R10); `template` usado no envio fora da janela.
- **Channel** (`type`, `status`) — `ofType('whatsapp')->ativo()` (R1).
- **PatientProfessionalPreference** (`suppress_renewal_notifications`) — opt-out (R5).
- **Paciente** (`telefone_primario_normalizado`) — thread id WhatsApp (R1).
