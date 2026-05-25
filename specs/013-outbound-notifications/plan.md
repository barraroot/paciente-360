# Implementation Plan: Entrega de Notificações Outbound

**Branch**: `013-outbound-notifications` | **Date**: 2026-05-24 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/013-outbound-notifications/spec.md`

## Summary

Transformar os listeners-stub (que hoje só fazem `Log::info`) em **entrega real** de notificações ao paciente, reutilizando a infraestrutura de mensageria existente. Introduz-se um **orquestrador de notificação outbound** que: resolve o canal/conversa do paciente, seleciona o template HSM configurado pelo tenant, respeita a janela 24h (Princípio VI), aplica as guardas já existentes (opt-out, debounce, idempotência, sem-dado-clínico) e registra cada tentativa numa entidade rastreável. Quando a entrega automática é impossível, cai num **fallback de contato manual** materializado como mensagem de sistema na conversa do paciente na inbox.

Abordagem técnica: 2 tabelas novas (`outbound_notifications` para rastreio, `notification_templates` para o catálogo por tenant), 1 serviço orquestrador (`OutboundNotificationDispatcher`), 1 resolvedor de canal/conversa outbound, religamento de 6 listeners existentes, reconciliação de status via o callback de status do provedor já existente, e uma tela admin (Filament ou painel Vue) para configurar templates (US5).

## Technical Context

**Language/Version**: PHP 8.5 / Laravel 13; Vue 3 (apenas US5 — config de templates)
**Primary Dependencies** (todas já no projeto):
- Domínio Messaging (Fase 3): `MessageDispatchService::send(Conversation, OutboundMessage, ?senderUserId)`, `ConversationService`, models `Channel`/`Conversation`/`Message`, `WhatsAppCloudAdapter`, `SendOutboundMessageJob`, `TwilioStatusCallbackController` (reconciliação de status).
- Guardas existentes: `PatientProfessionalPreference` (opt-out), debounce Redis 4h (Fase 7), idempotência via `Message.idempotency_key`, marker `App\Support\Lgpd\ContainsNoClinicalData`.
- Métricas Prometheus (padrão `*Metrics`), Horizon (filas), Spatie Permissions, auditoria (`Auditable` + `audit_logs`).
**Storage**: PostgreSQL — 2 tabelas novas (`outbound_notifications`, `notification_templates`); ambas `tenant_id` + global scope.
**Testing**: PHPUnit (feature + unit); gates de isolamento multi-tenant e LGPD; mocks dos adapters (não-final) seguindo o padrão da Fase 5.
**Target Platform**: Linux server (Sail/Docker), filas Horizon (Redis).
**Project Type**: Web (API Laravel + SPA Vue).
**Performance Goals**: Entrega é **assíncrona** (enfileirada) — sem alvo de latência interativa. Resolução de canal/template ≤ 50ms (consultas indexadas por tenant). Throughput limitado pela fila `outbound-messages` existente.
**Constraints**: Princípio VI (template aprovado fora da janela 24h — gate de envio), Princípio I (zero dado clínico no payload e na auditoria), Princípio II (isolamento por tenant em toda resolução).
**Scale/Scope**: Volume modesto por tenant (1–2 confirmações por consulta + alertas de receituário + ofertas de waitlist). Sem disparo em massa (campanhas são feature separada — Fase 8).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação | Como o design atende |
|---|---|---|
| **I. LGPD (NON-NEGOTIABLE)** | PASS c/ gate | Conteúdo ao paciente e variáveis de template MUST ser não-clínicos. Reusa o marcador `ContainsNoClinicalData` nos eventos de origem; gate test valida que nenhum payload/variável carrega medicamento/posologia/diagnóstico. Auditoria registra decisão sem PII clínica. Telefone tratado como identificador já existente (sem novo armazenamento). |
| **II. Isolamento Multi-Tenant (NON-NEGOTIABLE)** | PASS c/ gate | `outbound_notifications` e `notification_templates` com `tenant_id` + global scope. Resolução de canal/conversa/template SEMPRE filtra pelo tenant do evento. Jobs restauram contexto de tenant (`TenantAwareJob`). Gate test cross-tenant obrigatório. |
| **III. Segurança Clínica / IA (NON-NEGOTIABLE)** | PASS (N/A) | Feature não gera conteúdo de IA nem orientação clínica; apenas entrega avisos transacionais. O listener de renovação por IA (`EnqueueInboxTaskOnAiRenewal`) apenas roteia/entrega, sem produzir texto clínico. |
| **IV. Spec-Driven / Test-First** | PASS | Originada de spec aprovada (013). Cada US tem testes; gates (isolamento, LGPD, idempotência, janela 24h) codificados antes do religamento. Migrations idempotentes. |
| **V. Observabilidade** | PASS | Estados rastreados em `outbound_notifications` + métricas Prometheus (enviadas/falhas/pendentes-manuais/ignoradas por motivo) + eventos auditáveis de decisão + Sentry em falhas não-tratadas. |
| **VI. Conformidade Meta (NON-NEGOTIABLE)** | PASS | Fora da janela 24h → `OutboundMessage` com `contentType='template'` (bypass de janela já implementado em `enforceWindow24h`). **Gate de aprovação implementado (D1)**: antes do disparo fora da janela, o `NotificationTemplateResolver`/dispatcher consulta o status de aprovação real no `ChannelTemplate` (`meta_template_status='approved'`, fonte de verdade já sincronizada da Meta/Twilio); sem template aprovado correspondente → `pending_manual/no_template` (envio bloqueado + evento auditável). Isso cumpre literalmente "o dispatcher MUST consultar status do template antes do disparo e bloquear se não aprovado". Defesa em profundidade adicional: rejeição do provedor → `failed` → fallback manual. Notificações desta feature são **transacionais** (vinculadas a uma consulta/receituário do próprio paciente) — não disparo de marketing em massa, logo as cláusulas de opt-in de marketing/“/sair” do Princípio VI se aplicam apenas como reuso do opt-out já existente, não como novo gate de massa. |
| **VII. Segurança Operacional** | PASS | Sem nova superfície de auth além da config de templates (US5), que é permission-gated (`channel.connect`/admin). Rate limiting herdado da fila `outbound-messages`. |

**Resultado**: PASS 7/7 — nenhuma violação que exija Complexity Tracking. O gate de aprovação de template do Princípio VI é **implementado** via consulta runtime ao `ChannelTemplate.meta_template_status` (decisão D1 do `/speckit-analyze`), não apenas delegado à configuração.

**Nota de escopo (D2 — Princípio IV E2E)**: o teste E2E Playwright da jornada "confirmação automática de consulta" fica **DEFERRED** nesta feature (segue o padrão das Fases 5/9/10/12), coberto por smoke browser (T045) + gates de feature (G1/G8). Reintrodução do E2E automatizado é tarefa de hardening futura — registrado como desvio consciente, não dilui o princípio.

## Project Structure

### Documentation (this feature)

```text
specs/013-outbound-notifications/
├── plan.md              # Este arquivo
├── research.md          # Decisões técnicas (Phase 0)
├── data-model.md        # Entidades novas (Phase 1)
├── quickstart.md        # Guia operacional / lotes (Phase 1)
├── contracts/           # Contratos internos + gates + endpoints admin (Phase 1)
│   └── outbound-notifications.md
└── tasks.md             # (/speckit-tasks — não criado aqui)
```

### Source Code (repository root)

```text
app/
├── Domain/Messaging/Notification/            # NOVO subdomínio de notificação outbound
│   ├── Models/
│   │   ├── OutboundNotification.php          # rastreio (estado + motivo + origem)
│   │   └── NotificationTemplate.php          # catálogo por tenant/tipo
│   ├── Services/
│   │   ├── OutboundNotificationDispatcher.php # orquestrador central
│   │   ├── OutboundChannelResolver.php        # paciente → canal/conversa de saída
│   │   └── NotificationTemplateResolver.php   # tipo → template + variáveis (tenant)
│   └── Enums/
│       ├── NotificationType.php              # confirmação/alerta-receituário/oferta-vaga/escalonamento
│       └── NotificationStatus.php            # queued/sent/delivered/failed/pending_manual/skipped
├── Listeners/                                # RELIGAR (remover Log::info stubs)
│   ├── Agenda/DispatchConfirmationToInbox.php
│   ├── Agenda/EscalateCancellationOutsideWindowToInbox.php
│   ├── Agenda/EscalateRescheduleLimitExceededToInbox.php
│   ├── Agenda/DispatchWaitlistOfferToInbox.php
│   └── Prescription/DispatchPrescriptionAlertViaMessaging.php
│   └── Prescription/EnqueueInboxTaskOnAiRenewal.php
├── Jobs/Messaging/                           # reusa SendOutboundMessageJob
├── Http/Controllers/Api/V1/Notifications/    # US5: CRUD de NotificationTemplate (admin)
├── Http/Resources/Notifications/
└── Support/Metrics/OutboundNotificationMetrics.php

database/migrations/                          # 2 migrations novas (idempotentes)
database/factories/                           # factories OutboundNotification + NotificationTemplate

resources/js/                                 # US5 (P3): tela de config de templates
├── pages/settings/NotificationTemplatesPage.vue
└── stores/notificationTemplatesStore.js

tests/Feature/Notifications/                  # CRUD + gates (isolamento, LGPD, idempotência, janela, fallback)
tests/Unit/Notifications/                     # resolvers + dispatcher (lógica pura)
```

**Structure Decision**: Novo subdomínio `app/Domain/Messaging/Notification/` (segue o padrão de subdomínios da Fase 3, irmão de `Conversation`/`Message`/`Channel`). O orquestrador é o único ponto que chama `MessageDispatchService::send`; os listeners viram finos (delegam ao dispatcher). A config de templates (US5) é P3 e pode ser provisionada por seed inicialmente — UI Vue entra no fim.

## Complexity Tracking

> Nenhuma violação constitucional a justificar. Constitution Check = PASS 7/7. Tabela omitida.
