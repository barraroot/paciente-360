# Contracts — Entrega de Notificações Outbound (013)

Contratos internos (serviço/dispatcher), endpoints REST de configuração (US5) e **gates de aceite** (testes obrigatórios).

## 1. Contrato interno — `OutboundNotificationDispatcher`

```
dispatch(NotificationRequest $request): OutboundNotification
```

**`NotificationRequest`** (DTO imutável montado pelos listeners):
- `tenantId: int`
- `patientId: int`
- `type: NotificationType`
- `milestone: string` (ex.: `t_minus_24h`)
- `source: {type, id}` (Appointment | Prescription | WaitlistEntry)
- `context: array` — **apenas dados não-clínicos** para preencher variáveis (nome, data/hora, profissional, clínica, dias até vencimento)
- `freeFormBody: ?string` — usado só se houver conversa dentro da janela (texto livre)

**Garantias**:
- SEMPRE retorna um `OutboundNotification` persistido com estado terminal ou `sent` (nunca lança para o listener — falhas viram `pending_manual`).
- Curto-circuita na ordem R5 (opt_out → debounce → idempotência → canal → template/janela → envio).
- Idempotente por `(tenant, patient, type, milestone, date)`.

## 2. Contrato interno — resolvers

```
OutboundChannelResolver::resolve(int $tenantId, int $patientId): ?ResolvedChannel   // {channel, conversation, withinWindow}
NotificationTemplateResolver::resolve(int $tenantId, NotificationType $type, string $channelType): ?NotificationTemplate
ConversationService::findOrCreateForPatientChannel(Channel $c, string $threadId, int $patientId): Conversation
```

## 3. Endpoints REST (US5 — config de templates)

Middleware: `['auth:sanctum', 'tenant.slug', 'tenant.not-suspended']`. Permissão: `channel.connect` (admin da clínica).

| Método | Rota | Ação |
|---|---|---|
| GET | `/api/v1/notification-templates` | lista templates do tenant |
| POST | `/api/v1/notification-templates` | cria (valida allow-list de variáveis + tipo/canal) |
| PUT | `/api/v1/notification-templates/{id}` | atualiza |
| DELETE | `/api/v1/notification-templates/{id}` | soft-delete |

`NotificationTemplateResource`: `{id, notification_type, channel_type, provider_template_id, language, variables_map, is_active}` — **sem** vazar dados de outro tenant.

## 4. Gates de aceite (testes obrigatórios)

| Gate | Teste | Valida |
|---|---|---|
| **G1** | `OutboundConfirmationDeliveryTest` | US1: confirmação T-24h com canal+template → `sent` + `Message` template criada (não mais só log). |
| **G2** | `OutboundCrossTenantTest` (Princípio II) | canal/template/notification de um tenant nunca usados por outro; resolução escopada. |
| **G3** | `OutboundPayloadLgpdTest` (Princípio I) | nenhum corpo/variável contém medicamento/posologia/diagnóstico (fixtures clínicas); allow-list de `variables_map` enforçada. |
| **G4** | `OutboundWindowTemplateTest` (Princípio VI) | fora da janela sem template → `pending_manual/no_template`; com template → envia; dentro da janela → texto livre permitido. |
| **G5** | `OutboundOptOutDebounceTest` | opt-out → `skipped/opt_out` (evento de domínio ainda emitido); 2º envio em <4h → `skipped/debounced`. |
| **G6** | `OutboundIdempotencyTest` | mesmo (paciente,tipo,marco,data) não duplica; T-24h e T-2h são distintos. |
| **G7** | `OutboundPendingManualTest` | sem canal → `pending_manual/no_channel` + **mensagem de sistema** na conversa + conversa sinalizada (priority alta). |
| **G8** | `OutboundDeliveryReconciliationTest` | callback de status do provedor transiciona `sent → delivered`/`failed`; falha definitiva → `pending_manual/send_failed`. |
| **G9** | `OutboundPrescriptionAlertTest` | US2: alerta D-7 entregue, sem dado clínico, respeitando opt-out de renovação. |
| **G10** | `OutboundWaitlistOfferTest` | US3: oferta ao topo da fila; expirada a janela → próximo notificado. |
| **G11** | `NotificationTemplatesCrudTest` | US5: CRUD + isolamento por tenant + validação de allow-list. |

## 5. Métricas expostas (Prometheus)

`outbound_notifications_total{tenant,type,status}` · `outbound_notifications_pending_manual_total{tenant,reason}` · `outbound_notifications_skipped_total{reason}` · `outbound_notifications_delivery_latency_seconds`.

## 6. Eventos auditáveis (sem PII clínica)

`NotificacaoEnviada`, `NotificacaoSuprimida{motivo}`, `NotificacaoRoteadaParaManual{motivo}` — payload com `notification_id, patient_id, type, milestone, reason` (nunca conteúdo clínico).
