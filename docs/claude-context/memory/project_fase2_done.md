---
name: Paciente360 — Fase 2 entregue
description: Fase 2 (CRM Pacientes — cadastro, timeline, importação, funil Kanban, tags+status, mesclagem reversível) completa em 2026-05-11. 650 testes verdes (era 467 da Fase 0), 142/146 tasks. 4 deferred para fases futuras (cobertura tool, E2E run, Sentry/Prometheus prod).
type: project
originSessionId: 1cdd9d76-3906-42d4-90a4-7a21a1ba97a6
---
A Fase 2 do Paciente360 (`002-crm-pacientes`) foi entregue em 2026-05-11 via coordenação multi-agente Claude Code, mesma estratégia de lotes da Fase 0.

**Why:** continuação da delegação end-to-end ("faça o papel do gestor e coordene a equipe até o final da feature") iniciada na Fase 0. Pipeline spec-kit completo: specify → clarify → plan → tasks → analyze → implement.

**How to apply:** ao retomar o projeto, partir do branch `002-crm-pacientes` (não mesclada em `main` ainda — pendente merge). Próxima fase (Fase 3 omnichannel — WhatsApp + Instagram + chat) consome:
- `Paciente` model + ability `paciente.view` para resolver paciente em mensagens recebidas.
- `EventoTimeline` infra: novas fases adicionam tipos próprios (`mensagem.recebida`, `consulta.agendada`, etc.) via `Auditable` event → listener wildcard grava em timeline automaticamente.
- Tags `sys:` para automação (ex.: `sys:em-tratamento` quando agenda Fase 5).

**Métricas finais:**
- 650 testes PHPUnit verdes / 1919 assertions (Fase 0: 467; Fase 2: +183 novos)
- 142/146 tasks completas (97%)
- OpenAPI drift: exit 0 (43 paths Fase 0 + Fase 2 mergeados)
- Composer audit: 0 CVEs
- Pint clean
- 0 regressões na Fase 0 (347 testes Fase 0 mantidos)

**Tasks deferidas:**
- T264 (cobertura `--min=75`): driver pcov/xdebug não disponível no Sail image padrão; rodar em CI com imagem estendida.
- T267 (E2E Playwright): spec escrito em `tests/e2e/crm-paciente-jornada-completa.spec.ts`, rodar manualmente com app up.
- T271 (Sentry paciente_id context): produção/DevOps.
- T272 (Prometheus gauge paciente count): idem.

**Decisões técnicas marcantes (Fase 2):**
1. **pg_trgm + `immutable_unaccent` wrapper**: `unaccent()` do PG não é IMMUTABLE; criada wrapper `immutable_unaccent(text)` para uso em colunas GENERATED. **Padrão deve ser preservado em fases futuras** que usarem similarity search.
2. **Mesclagem reversível**: `snapshot_pre_merge JSONB` em `mesclagens_pacientes`. **Anotações são copiadas (não movidas)** durante merge devido a trigger PG de imutabilidade.
3. **Spatie team mode + abilities granulares**: `paciente.note.view:{tipo}` (geral/clinica/comportamental/financeira) controla visibilidade no nível de query (não render).
4. **`AsJsonArray` cast** continua padrão para JSONB multi-valor em todos os Models.
5. **`RegistraEventoTimelineListener`** escuta `Auditable` wildcard e popula `eventos_timeline` automaticamente para qualquer evento com `relatedPacienteId()` não-nulo. **Fases 3/5/6 só precisam disparar events Auditable**.
6. **TenantPacientesWidget** (Q2 do analyze): widget Filament Super Admin com contagens agregadas — **APENAS contagens, NUNCA PII** (gate FR-038 100% coberto).

**Stack adicionada na Fase 2:**
- Backend: `league/csv@9.28`, `phpoffice/phpspreadsheet@5.7` (substituiu 4.x por 6 CVEs)
- Frontend: `vuedraggable@4.1` (Kanban)
- PG extensions: `pg_trgm`, `unaccent`, `btree_gin`
- Horizon: supervisor dedicado `imports` para isolamento de noisy-neighbor

**Como rodar localmente:** `vendor/bin/sail up -d && vendor/bin/sail artisan migrate:fresh --seed --class=DevSeeder` cria `clinica-alfa.lvh.me` com 30 pacientes. URLs: `/panel/pacientes`, `/panel/pacientes/importar`, `/panel/funil`, `/panel/convenios`. Super admin em `/admin`.
