# Débito QA — Falhas pré-existentes da Fase 8 (Privacy/Campaigns/Integrations)

**Descoberto em 2026-05-24** durante o fechamento da Spec 012. A Fase 8 foi
marcada "DELIVERED" mas **a suíte completa nunca foi executada** (T288 deferido).
A primeira execução real revelou **56 testes vermelhos** — todos em módulos
secundários (não no caminho crítico do produto).

## Já corrigido (commitado na main)

| Bug | Severidade | Fix |
|---|---|---|
| Recursão infinita → SIGSEGV em `professional.manage`/`report.view`/`report.export` | 🔴 crash prod | `can()` → `hasPermissionTo()` em `AppServiceProvider` |
| `ClinicalReportService` colunas `name`/`price_cents` inexistentes | 🟠 500 prod | `nome`/`valor_particular*100` |
| Drill-down executivo shape divergente | 🟡 | `period_start`/`period_end` |
| `StoreProfessionalRequest` sem unique de conselho | 🟠 500→422 | `Rule::unique` composto |
| Seeders `RoleSeeder`/`SuperAdmin` com assertions desatualizadas | 🟢 teste | contagens 51/45 + email `admin@flowsys.com.br` |

Caminho crítico do demo (onboarding, profissionais, agenda, pacientes, inbox,
dashboard, receituários) está **100% verde**.

## Débito remanescente (~52 falhas) — NÃO corrigido

### Privacy / LGPD (~13) — 🔴 bug sistêmico real
`ForgettingExecutor`, `PortabilityArchiveGenerator` e `ConsentService` varrem
tabelas do CRM (Fase 2) com nomes de coluna **em inglês**, mas o schema é em
português: `patient_id`→`paciente_id`, `ocorreu_em` inexistente, `updated_at`
ausente em `anotacoes`/`audit_logs`. Direito ao esquecimento e portabilidade
**quebram em produção**. Exige mapa coluna-a-coluna por tabela relacionada.
Esforço: **L**. Risco: alto (LGPD).

### Campaigns (~16) — 🟠 misto test/código
- `messaging_channels` usa `type`, não `provider` (factory/teste de campanha).
- uuid inválido passado a coluna uuid; FK em `campaign_recipients`.
Esforço: **M**. Maioria test-side; validar se o dispatcher real também erra.

### Integrations / Webhooks (~9) — 🟠
- `ClassIsFinalException`: testes tentam mockar classe `final` (`WebhookPayload`).
- `ArgumentCountError`: assinatura de construtor de `UrlGuard`/SSRF guard mudou.
- QueryException em `WebhookDispatchE2ETest`.
Esforço: **M**.

### Misc (~6)
- `Constitutional/EventsForAiPseudonymizationTest` — `ArgumentCountError`.
- `Lgpd/PiiScrubberSentryIntegrationTest` (unit, 2).
- `SuperAdmin/AnomalyDetectionTest`, `TenantCancellation` (2).
- `Fase0/Admin/FilamentReusesSeeder` — `InvalidCountException` (2).
- `Unit/Frontend/RegisterTenantFrontendTest` (1).

## Recomendação
Tratar como **spec de hotfix dedicada** (`013-fase8-suite-stabilization`),
priorizando Privacy/LGPD (compliance) → Integrations → Campaigns → misc.
Rodar `php artisan test --compact` como gate de DoD — desta vez de verdade.
