# Métricas Prometheus — Módulo de Receituários (Fase 7)

## Visão Geral

Todas as métricas estão implementadas em `app/Support/Metrics/PrescriptionMetrics.php`
e expostas via contrato `PrescriptionMetricsContract`.

Quando o pacote `promphp/prometheus_client_php` não está instalado (ambiente CI/test),
as métricas são registradas via `Log::debug('metrics.counter', ...)` sem perda de dados.

---

## Métricas de Alertas

### `paciente360_prescription_alerts_dispatched_total`

- **Tipo**: Counter
- **Labels**: `tenant_id`, `alert_step` (days15/days7/days1), `status` (sent/failed/blocked)
- **Descrição**: Total de alertas de vencimento despachados por tenant e checkpoint.
- **Emitido por**: `DispatchPrescriptionAlertJob`

### `paciente360_prescription_alerts_blocked_total`

- **Tipo**: Counter
- **Labels**: `reason` (no_channel/no_template/opt_out), `tenant_id`
- **Descrição**: Alertas bloqueados antes do envio por motivo específico.
- **Emitido por**: `DispatchPrescriptionAlertJob`

### `paciente360_prescription_alerts_idempotency_hits_total`

- **Tipo**: Counter
- **Labels**: `tenant_id`, `alert_type`
- **Descrição**: Hits de idempotência Redis (alerta já processado).
- **Emitido por**: `ProcessPrescriptionAlertsJob`

### `paciente360_prescription_alerts_processed_total`

- **Tipo**: Counter
- **Labels**: `tenant_id`, `count`
- **Descrição**: Total de alertas processados pelo job principal.
- **Emitido por**: `ProcessPrescriptionAlertsJob`

---

## Métricas de Renovação

### `paciente360_prescription_renewals_initiated_total`

- **Tipo**: Counter
- **Labels**: `initiated_by` (professional/ai), `tenant_id`
- **Descrição**: Renovações iniciadas por tipo de iniciador.
- **Emitido por**: `RenewPrescriptionService`

### `paciente360_prescription_renewal_conversion_rate`

- **Tipo**: Gauge
- **Labels**: `tenant_id`
- **Descrição**: Taxa de conversão de renovações por tenant (renovações completadas / solicitadas).
- **Emitido por**: `RenewPrescriptionService`

---

## Métricas de Acesso a Controladas

### `paciente360_prescription_controlled_access_denied_total`

- **Tipo**: Counter
- **Labels**: `tenant_id`
- **Descrição**: Tentativas negadas de acesso a receitas controladas.
- **Emitido por**: `ControlledPrescriptionMaskingService` (via policy)

---

## Métricas de PDF

### `paciente360_prescription_pdfs_uploaded_total`

- **Tipo**: Counter
- **Labels**: `tenant_id`, `status` (success/failed/purged)
- **Descrição**: Uploads de PDF de receita por tenant e status. Inclui candidatos a purge.
- **Emitido por**: `PrescriptionPdfUploadJob`, `PurgeOldPrescriptionPdfVersionsJob`

### `paciente360_prescription_signed_urls_emitted_total`

- **Tipo**: Counter
- **Labels**: `tenant_id`
- **Descrição**: Signed URLs de download de PDF emitidas por tenant.
- **Emitido por**: `PrescriptionPdfController`

---

## Métricas de Relatório / Exportação

### `paciente360_prescription_csv_exports_total`

- **Tipo**: Counter
- **Labels**: `tenant_id`, `has_controlled` (true/false)
- **Descrição**: Exportações CSV realizadas por tenant, com flag indicando se havia receita controlada na exportação.
- **Emitido por**: `PrescriptionCsvExporter`

---

## Candidatos a Sentry Transaction Tracing (DEFERRED)

Os seguintes pontos estão marcados nos docblocks como candidatos a instrumentação
Sentry quando o SDK estiver configurado em produção:

- `PrescriptionReportService::paginate()` — medir `prescription_report_query_ms` por tenant
- `ProcessPrescriptionAlertsJob::handle()` — medir `prescription_alerts_job_ms` por tenant

Nenhuma chamada SDK foi adicionada; este é um TODO para quando `sentry/sentry-laravel`
for configurado no ambiente de produção.
