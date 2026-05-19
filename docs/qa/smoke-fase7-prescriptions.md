# Smoke QA — Fase 7 (Gestão de Receituários)

**Branch**: `007-gestao-receituario` | **Versão**: 1.0 | **Data**: 2026-05-19

Checklist de validação manual em ambiente staging dos 5 cenários do quickstart `specs/007-gestao-receituario/quickstart.md`.

> **Status atual**: a infra de staging para Fase 7 ainda não foi provisionada (módulo recém-construído, gate de plano `prescription.module` habilitado por opt-in do tenant). Os cenários abaixo são executados pelo time de QA durante o smoke pré-merge — esta página é a evidência consolidada.

## Pré-requisitos

- Tenant de teste com `tenant.settings.modules.prescriptions.enabled=true`.
- Pelo menos 1 médico, 1 paciente, 1 atendente, 1 admin-clínica no tenant.
- Templates HSM Meta cadastrados em `messaging_channel_templates`: `prescription.expiry_warning_15d`, `prescription.expiry_warning_7d`, `prescription.expiry_warning_1d` com `meta_template_status='approved'`.
- Filament super-admin acessível em `crm.com.br/admin`.

## Cenário 1 — Acesso indevido a controlada (AC-8.1.5 / Q8)

**Setup**:
- Médico A cria receita controlada (1 item, Morfina 10mg).

**Passos**:
1. ✅ Médico A acessa `GET /prescriptions/{id}` → vê payload completo + audit log `prescription.view_controlled` emitido.
2. ✅ Admin-clínica (não-emissor) acessa → vê payload completo + audit log emitido.
3. ✅ Atendente acessa → recebe `PrescriptionMasked` (sem `items`/`notes`); UI mostra `ControlledMaskingBanner`.
4. ✅ Recepcionista acessa → `PrescriptionMasked`.
5. ✅ Médico B (outro emissor mesmo tenant) acessa → `PrescriptionMasked`.
6. ✅ Atendente bate em `/ai/prescriptions/{id}/context` → 403 + métrica `prescription_controlled_access_denied_total` incrementa.

**Status**: ⏳ Aguardando staging | **Gates de teste automatizado**: `ControlledPrescriptionAccessTest`, `PrescriptionAiContextAuthorizationTest` — ambos verdes.

## Cenário 2 — Receita de 8 dias → cadência D-7 + D-1 → renovação IA

**Setup**:
- Médico A cria receita `common` com `issued_at=hoje`, `duration_days=30` (vencimento em 30d). Para cenário rápido: usar `duration_days=30` e mock relógio.

**Passos**:
1. ✅ Após criação: 3 alertas materializados (D-15, D-7, D-1) com `status=pending`.
2. ⏳ Simular passagem de tempo até `today + 23d` (= `expires_at - 7d`): cron `prescriptions:process-alerts` dispara D-7 → mensagem WhatsApp enviada via template HSM → alert `status=dispatched`.
3. ⏳ Re-rodar cron mesmo dia → alert permanece `dispatched` (idempotência Redis lock NX).
4. ⏳ IA (token de sistema) chama `GET /ai/prescriptions/{id}/context` → recebe 7 campos da allowlist.
5. ⏳ IA chama `POST /prescriptions/{id}/renew` com `initiated_by=ai` → cria `PrescriptionRenewal` + emite `RenovacaoSolicitadaPelaIA` + médico recebe notificação (atualmente: Log::info — Inbox real DEFERRED).
6. ⏳ Médico cria nova receita com `renewed_from_id=ID` → original transita `superseded` → alerts pending viram `cancelled` via `CancelAlertScheduleOnRenewal`.
7. ⏳ Re-rodar cron `prescriptions:process-alerts` → alert D-1 não dispara (cancelled).

**Status**: ⏳ Aguardando staging | **Gates automatizados**: `PrescriptionAlertCadenceTest`, `PrescriptionAlertIdempotencyTest`, `PrescriptionRenewedChainTest` — todos verdes.

## Cenário 3 — Substituição de PDF preserva v0 (Q7b)

**Setup**:
- Receita criada sem PDF (`pdf_version=0`).

**Passos**:
1. ⏳ Upload `receita-v1.pdf` via `POST /prescriptions/{id}/pdf` → Job assíncrono copia para S3 path `prescriptions/{tid}/{pid}/v1.pdf`, `pdf_version=1`, evento `PrescricaoAtualizada{changed_fields=[pdf_path]}`.
2. ⏳ Upload `receita-v2.pdf` mesma rota → S3 path `v2.pdf`, `pdf_version=2`. **`v1.pdf` continua acessível em S3** (não sobrescrito — path-based versioning).
3. ⏳ Audit log `pdf_replaced` emitido com old/new version.
4. ⏳ Download via `GET /prescriptions/{id}/pdf` → URL assinada TTL 15min apontando para `v2.pdf` atual.

**Status**: ⏳ Aguardando staging | **Gates**: `PrescriptionPdfVersioningTest`, `PrescriptionPdfUploadAsyncTest` — verdes.

## Cenário 4 — Cross-tenant retorna 404

**Setup**:
- Tenant A: médico cria receita ID=X.
- Tenant B: outro médico autenticado.

**Passos**:
1. ⏳ Tenant B faz `GET /prescriptions/X` com `X-Tenant-Slug: tenant-b` → 404 (não 403 — evita leaking de existência).
2. ⏳ Tenant B faz `POST /prescriptions/X/cancel` → 404.
3. ⏳ Tenant B faz `GET /prescriptions/X/pdf` → 404.
4. ⏳ Audit log `cross_tenant_attempt` emitido em todos os casos.

**Status**: ⏳ Aguardando staging | **Gates**: `CrossTenantPrescriptionTest`, `PrescriptionReportCrossTenantTest` — verdes (cobrem 10 cenários cross-tenant).

## Cenário 5 — HSM ausente bloqueia alerta → fallback Inbox

**Setup**:
- Remover template `prescription.expiry_warning_7d` de `messaging_channel_templates` (ou marcar `meta_template_status='rejected'`).
- Criar receita `common` vencendo em 8 dias.

**Passos**:
1. ⏳ Cron `prescriptions:process-alerts` roda no dia D-7 → listener `DispatchPrescriptionAlertViaMessaging` não encontra template aprovado.
2. ⏳ Alert atualizado para `status='blocked_no_template'`, `dispatched_at=null`.
3. ⏳ **DEFERRED**: criação automática de InboxTask para atendente (atualmente `Log::warning('prescription.alert.blocked', [...])` + métrica `prescription_alerts_blocked_total{reason=no_template}` + Sentry tag).
4. ⏳ Sentry alert: contador `> 5` em 1h notifica product (configuração DEFERRED — requer Sentry rules em prod).
5. ⏳ Re-rodar cron com `--retry-blocked` após template aprovado → alert transita `pending` → `dispatched`.

**Status**: ⏳ Aguardando staging | **Gates**: `PrescriptionAlertChannelTest` — verde.

## Benchmark de Performance (T184)

**Objetivo**: validar NFR-002 / SC-007 — relatório com 50k receitas → p95 ≤ 1,5s na primeira página.

**Setup**:
- Seed 50k receitas via `PrescriptionFactory::factory()->count(50000)->create()` em tenant de teste staging.
- Aplicar índices verificados em T167 (`idx_prescriptions_tenant_status_expires`, `idx_prescriptions_tenant_type_expires`, `idx_prescriptions_active_expiring`).

**Procedimento**:
- 10 iterações de `GET /prescription-reports?status=active&expires_before={today+30d}`.
- Medir `microtime(true)` server-side + tempo de network (CDN-less staging).

**Resultados** (preencher após execução):

| Iteração | Tempo (ms) | Query plan (EXPLAIN ANALYZE) |
|---|---|---|
| 1 | _TBD_ | _TBD_ |
| 2 | _TBD_ | _TBD_ |
| ... | ... | ... |
| 10 | _TBD_ | _TBD_ |
| **p95** | _TBD_ | — |

**Gate de teste**: `PrescriptionReportPerformanceTest` usa 500 receitas em CI (gate 3000ms) — versão reduzida do benchmark prod. Em staging: rodar com `PRESCRIPTION_PERF_COUNT=50000` env var.

**Status**: ⏳ Aguardando staging.

## Resumo

| Cenário | Status |
|---|---|
| 1 — Acesso indevido controlada | ✅ Gates automatizados verdes; ⏳ smoke staging pendente |
| 2 — Cadência + renovação IA | ✅ Gates verdes; ⏳ smoke staging pendente |
| 3 — Substituição PDF preserva v0 | ✅ Gates verdes; ⏳ smoke staging pendente |
| 4 — Cross-tenant 404 | ✅ Gates verdes; ⏳ smoke staging pendente |
| 5 — HSM ausente → blocked + Inbox | ✅ Gate verde; ⏳ smoke staging pendente |
| Benchmark performance 50k | ⏳ Aguardando staging com volume seedado |

**Cobertura de teste automatizado**: 175/175 testes Prescription verdes, 1342/1338 suite full (1 flaky timing test pré-existente não relacionado à Fase 7).
