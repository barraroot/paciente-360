# Smoke Fase 8 — Staging

> **T296 (Fase 8 — Polish)** — Roteiro dos 10 cenários do `quickstart.md` em ambiente staging.

## Status: PENDENTE EXECUÇÃO

Cenários a executar em staging com tenant QA + Stripe sandbox.

## Pré-requisitos

- [ ] Tenant `qa-alpha` provisionado em staging.
- [ ] Plano `enterprise` ativo (limites altos para evitar falsos negativos).
- [ ] Usuários seeded: 1 super-admin, 2 admin-clinica, 3 médicos, 2 atendentes.
- [ ] DevSeeder rodado com 100 pacientes + 30 conversas + 20 agendamentos + 10 receitas.
- [ ] Stripe sandbox configurado.
- [ ] Mock webhook server externo (rodando em staging.webhooks.paciente360.com.br).

## Cenários

### Cenário 1 — Campanha de reativação

- [ ] Admin cria campanha "Reativação Outubro".
- [ ] Preview mostra ≥30 elegíveis.
- [ ] Compliance Gate aprova (template HSM, janela 24h, consentimento).
- [ ] Dispatch → status `dispatching` → após 5min status `completed`.
- [ ] Relatório mostra `delivery_rate > 70%`.

### Cenário 2 — Direito ao Esquecimento

- [ ] Paciente submete via `/privacidade/esquecimento/publico`.
- [ ] Admin vê pendente em ≤30s.
- [ ] Executar → confirmar modal com mapa Q26.
- [ ] Paciente fica anônimo na lista.
- [ ] Timeline mostra banner "Conteúdo anonimizado (LGPD)".
- [ ] Receita controlada do paciente preservada (verificar painel super-admin).

### Cenário 3 — Portabilidade

- [ ] Admin cria PortabilityRequest.
- [ ] Executar → arquivo gerado em ≤30s.
- [ ] URL assinada baixada com sucesso.
- [ ] JSON válido com 6 seções (paciente, consultas, mensagens, anotações, receitas, consentimentos).
- [ ] Aguardar 7d (ou simular `Carbon::setTestNow`) — URL retorna 403.

### Cenário 4 — Super Admin Impersonate

- [ ] Super admin loga em `crm.staging.com.br/admin`.
- [ ] Impersonate tenant `qa-alpha`.
- [ ] Banner sticky persistente em 5 telas (pacientes, agenda, inbox, receitas, campanhas).
- [ ] Sair → banner removido.
- [ ] Audit log mostra 5 `super_admin.screen.visited` + 1 `session.started` + 1 `session.ended`.

### Cenário 5 — Webhook delivery

- [ ] Admin configura webhook apontando para mock server.
- [ ] Criar paciente via SPA.
- [ ] Mock recebe POST em ≤2s com HMAC válido.
- [ ] Mock responde 500 nas próximas 5 tentativas → após 6h, delivery em DLQ.
- [ ] Admin reenvia do DLQ → mock recebe novamente.

### Cenário 6 — API Pública

- [ ] Admin cria token API com ability `*`.
- [ ] Cliente externo (curl) faz `GET /api/public/v1/patients` com Bearer.
- [ ] Resposta 200 com headers `X-RateLimit-Limit: 60`.
- [ ] Resposta de paciente sem consentimento `integracoes` retorna `consent_status: withheld`.
- [ ] `GET /api/public/v1/campaigns` retorna 404 (fora do escopo Q14).
- [ ] `POST` com `Idempotency-Key` repetido retorna mesmo response + header `Idempotency-Replayed: true`.

### Cenário 7 — Dashboard Executivo

- [ ] Admin acessa `/panel/relatorios/executivo`.
- [ ] 5 cards carregam em ≤1,5s (verificar via DevTools Network).
- [ ] Drill-down `leads_by_channel` abre modal com lista.
- [ ] Export PDF gera arquivo em ≤3s.
- [ ] PDF tem cabeçalho + 5 cards + rodapé com filtros.
- [ ] Médico (não admin) acessa relatório clínico — vê apenas própria agenda.

### Cenário 8 — Suspender + reativar tenant

- [ ] Super admin suspende `qa-beta`.
- [ ] Login no `qa-beta` → 401 `tenant_suspended`.
- [ ] API pública `qa-beta` → 503.
- [ ] Reativar → login funciona novamente.

### Cenário 9 — Anomalia detectada

- [ ] Provocar export de pacientes em massa (>500 em 5min).
- [ ] Cron `super_admin:detect-anomalies` (rodar manual) → cria `AnomalyDetected`.
- [ ] Super admin vê alerta em `/admin/anomalies-page`.

### Cenário 10 — Suite completa de Privacidade

- [ ] Consentimento marketing registrado.
- [ ] Paciente envia "PARE" no WhatsApp.
- [ ] Sistema revoga marketing automaticamente.
- [ ] Próxima campanha de marketing NÃO inclui esse paciente.
- [ ] Admin recebe notificação de revogação.

## Performance (T297)

- [ ] Dashboard executivo ≤1,5s p95 com 50k pacientes seedados (medir via Prometheus `report_executive_dashboard_views_total` + `http_request_duration_seconds`).
- [ ] Webhook delivery ≤5s p95 (medir `webhook_delivery_duration_ms`).
- [ ] Campanha 100 destinatários ≤5min do dispatch ao último delivery.

## Resultado (preencher após execução)

Capturar prints de cada cenário em `docs/qa/screenshots/fase8/`.

| Cenário | Status | Tempo | Notas |
|---------|--------|-------|-------|
| 1 — Campanha | (pendente) | — | — |
| 2 — Esquecimento | (pendente) | — | — |
| 3 — Portabilidade | (pendente) | — | — |
| 4 — Impersonate | (pendente) | — | — |
| 5 — Webhook | (pendente) | — | — |
| 6 — API Pública | (pendente) | — | — |
| 7 — Dashboard | (pendente) | — | — |
| 8 — Suspensão | (pendente) | — | — |
| 9 — Anomalia | (pendente) | — | — |
| 10 — Privacidade | (pendente) | — | — |
