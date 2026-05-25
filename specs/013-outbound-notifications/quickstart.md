# Quickstart — Entrega de Notificações Outbound (013)

**Status**: Plan complete | **Date**: 2026-05-24

Guia operacional. Reusa Messaging (Fase 3) + guardas (Fases 5/7/8). Toda mudança PHP → `pint --dirty` + teste.

## Pré-requisitos

- ✅ Branch `013-outbound-notifications`
- ✅ Spec + Clarifications + plan + research + data-model + contracts
- ✅ Fases 3 (Messaging), 5 (Agenda), 7 (Receituários) entregues
- ✅ Sail + Horizon (fila `outbound-messages`) rodando
- Tenant com 1 canal WhatsApp `ativo` + paciente com `telefone_primario` (para smoke)

## Ordem sugerida (Lotes)

### Lote A — Foundation (enums + migrations + models)
1. Enums `NotificationType`, `NotificationStatus`, `NotificationSkipReason` em `app/Domain/Messaging/Notification/Enums/`.
2. Migration `notification_templates` (UNIQUE parcial `(tenant_id, type, channel_type) WHERE deleted_at IS NULL`).
3. Migration `outbound_notifications` (UNIQUE parcial de idempotência + índice `(tenant_id, status)`).
4. Models `NotificationTemplate` + `OutboundNotification` (BelongsToTenant global scope; morph `source`).
5. Factories + (opcional) seeder de template default para dev (`DevSeeder`).

### Lote B — Resolvers + Dispatcher (núcleo)
6. `ConversationService::findOrCreateForPatientChannel(...)` (R2).
7. `OutboundChannelResolver::resolve(...)` (R1 — WhatsApp ativo + telefone normalizado + detecção de janela).
8. `NotificationTemplateResolver::resolve(...)` (R3).
9. `OutboundNotificationDispatcher::dispatch(NotificationRequest)` (R5 — ordem opt_out→debounce→idempotência→canal→janela/template→envio; fallback `pending_manual` com mensagem de sistema R10).
10. `OutboundNotificationMetrics` (R12).

### Lote C — Gates do núcleo (Test-First)
11. G2 (cross-tenant), G3 (LGPD payload), G4 (janela/template), G5 (opt-out/debounce), G6 (idempotência), G7 (pending_manual + system message).

### Lote D — Religamento dos listeners (remove stubs)
12. Agenda: `DispatchConfirmationToInbox` (G1), `DispatchWaitlistOfferToInbox` (G10), `EscalateCancellationOutsideWindowToInbox`, `EscalateRescheduleLimitExceededToInbox`.
13. Prescription: `DispatchPrescriptionAlertViaMessaging` (G9), `EnqueueInboxTaskOnAiRenewal`.
14. Cada listener monta `NotificationRequest` e chama o dispatcher; **remover `Log::info` placeholder**. Validar que auto-discovery não duplica (lição Fase 5).

### Lote E — Reconciliação de status
15. Listener/observer ligando `Message.status` (atualizado pelo `TwilioStatusCallbackController`) → `OutboundNotification` (`sent→delivered`/`failed`; falha definitiva → `pending_manual/send_failed`). Gate G8.

### Lote F — Config de templates (US5, P3)
16. Endpoints REST `notification-templates` + `StoreNotificationTemplateRequest` (valida allow-list de variáveis + tipo/canal) + Resource. Gate G11.
17. (Opcional nesta fase) tela Vue `NotificationTemplatesPage.vue` + item na sidebar (grupo Configurações, ability `channel.connect`). Provisionável por seed até a UI existir.

### Lote G — Polish + gates finais
18. `OutboundNotificationMetrics` registrada; eventos auditáveis (`NotificacaoEnviada`/`Suprimida`/`RoteadaParaManual`).
19. `pint --dirty` + suíte dos novos testes + suíte cheia (não regredir o baseline 1576/0).
20. Smoke browser: agendar consulta → confirmar envio real (ou `pending_manual` se sem template) → conferir conversa na inbox.
21. Constitution Re-Check + `.specify/feature.json` → DELIVERED.

## Comandos úteis

```bash
vendor/bin/sail artisan migrate
vendor/bin/sail artisan test --compact tests/Feature/Notifications
vendor/bin/sail bin pint --dirty --format agent
# smoke: ver pendências manuais
# (filtro de conversas sinalizadas priority=alta na inbox)
```

## Riscos / Notas

- **WhatsApp via Twilio** neste codebase — `provider_template_id` é o SID/id do template no provedor; não confundir com Meta Cloud direto.
- **Princípio VI**: verificação de aprovação de template é config-time + runtime (rejeição→failed→manual); consulta proativa de status fica como melhoria futura (research §6).
- **DEFERRED candidato**: UI Vue de templates (Lote F.17) pode ficar para o fim se o tempo apertar — backend + seed já operam.
