# Constitution Gates — Fase 8 Final

> **T287 (Fase 8 — Polish)** — Validação manual dos 7 gates pós-implementação.

## Status: PENDENTE EXECUÇÃO REAL

Os tests gates foram **codificados** durante Fase 8 (Lotes A-E), mas a execução final consolidada **requer Sail rodando**:

```bash
vendor/bin/sail artisan test --compact --filter="Gate"
```

Esta tabela documenta o **escopo de validação esperado**. Execução real após `vendor/bin/sail up -d`.

## Os 7 Gates

| # | Gate | Test path | Cobertura |
|---|------|-----------|-----------|
| 1 | Compliance Dispatcher | `tests/Feature/Campaigns/CampaignDispatcherComplianceTest.php` | 4 etapas sequenciais (rate limit, template HSM, janela 24h, consentimento) |
| 2 | Cross-tenant | `tests/Feature/Multitenancy/CrossTenantPrescriptionTest.php`, `tests/Feature/Integrations/PublicApiTenantSuspendedTest.php` | 404 (não 403) — não revela existência |
| 3 | LGPD Mapa de Anonimização | `tests/Feature/Privacy/MapaAnonimizacaoTest.php` | Cada coluna marcada como `anonymizable` realmente é zerada/redigida |
| 4 | Pseudonimização CI (IA) | `tests/Feature/Lgpd/EventsForAiPseudonymizationTest.php` | Todos eventos `ContainsNoClinicalData` têm 0 PII via reflection |
| 5 | Super Admin Scope | `tests/Feature/SuperAdmin/SuperAdminCrossTenantScopeTest.php` | Sem impersonate, super admin NÃO vê dados clínicos cross-tenant |
| 6 | Retention | `tests/Feature/Privacy/RetentionExecutorTest.php` | Crons `privacy:*` purgam respeitando políticas Q20 |
| 7 | Impersonate Audit | `tests/Feature/SuperAdmin/ImpersonateAuditTest.php` | Toda tela visitada gera `super_admin.screen.visited`; sessão sem audit é flagged |

## Procedimento de execução

```bash
# 1. Suite full
vendor/bin/sail artisan test --compact

# 2. Filtro só Gates
vendor/bin/sail artisan test --compact --filter="Gate|CrossTenant|MapaAnonimizacao|PseudonymizationCi|SuperAdminCrossTenant|RetentionExecutor|ImpersonateAudit"

# 3. Output esperado: 0 failures
```

## Critérios de aceitação

- **Todos os 7 gates ATIVOS** (não skipped).
- **Zero failures** na suite full.
- **Zero flaky** — se ≥1 flaky aparecer, investigar antes de marcar feature como DELIVERED.

## Resultado real (preencher após Sail up)

| Gate | Status | Tempo | Notas |
|------|--------|-------|-------|
| 1 | (pendente) | — | — |
| 2 | (pendente) | — | — |
| 3 | (pendente) | — | — |
| 4 | (pendente) | — | — |
| 5 | (pendente) | — | — |
| 6 | (pendente) | — | — |
| 7 | (pendente) | — | — |

## Histórico

| Data | Executor | Resultado | Notas |
|------|----------|-----------|-------|
| 2026-05-22 | (pendente) | RASCUNHO | Documento criado por T287. |
