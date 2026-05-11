---
description: "Tasks executáveis da Fase 2 — CRM Core (Pacientes)"
---

# Tasks: Fase 2 — CRM Core: Cadastro e Gestão de Pacientes

**Feature directory**: `specs/002-crm-pacientes/`
**Plan**: [plan.md](./plan.md) — **Spec**: [spec.md](./spec.md) — **Constitution**: v1.2.0

## Como ler este arquivo

Cada task segue o formato:

```
- [ ] TXXX [P?] [USx?] Título curto — caminho-arquivo principal
  - Descrição: o que fazer e por quê
  - Aceitação: critério testável (referencia AC-3.x.y do spec quando aplicável)
  - Depende de: TIDs anteriores
  - Princípio: I, II, III, IV, V, VI, VII (constituição v1.2.0)
```

**Convenções**:

- `[P]` = paralelizável (arquivos distintos, sem dependência ativa em tarefas pendentes).
- `[USx]` mapeia user stories: `US1`=US-3.1 (Cadastro Manual), `US2`=US-3.2 (Timeline), `US3`=US-3.3 (Importação), `US4`=US-3.4 (Funil), `US5`=US-3.5 (Tags/Status).
- **TDD obrigatório** (Princípio IV): tasks de teste vêm antes da implementação correspondente.
- Comandos sempre via `vendor/bin/sail`.
- Migrations são **imutáveis e idempotentes** — novos arquivos para cada mudança.
- AC referenciado entre parênteses em cada task de teste para rastreabilidade.

**Mapa de fases (contagem real após `/speckit.analyze` 2026-05-11)**:

| Fase | Bloco | Faixa de TIDs | Tasks reais |
|------|-------|---------------|-------------|
| 1 | Setup (compartilhado) | T001–T009a | 10 (T009a = vuedraggable) |
| 2 | Foundational (migrations + infra + abilities) | T010–T039 | 30 |
| 3 | US1 — Cadastro Manual (P1) 🎯 MVP | T100–T129 | 23 |
| 4 | US2 — Timeline (P1) | T140–T159 | 10 |
| 5 | US3 — Importação (P2) | T170–T194 | 20 |
| 6 | US4 — Funil Kanban (P2) | T200–T219 | 13 |
| 7 | US5 — Tags/Status (P2) | T230–T249 | 13 |
| 8 | Polish + E2E + verificação final | T260–T279 | 20 (T274 removida; T272a, T009a adicionadas) |

**Total: ~146 tasks numeradas** após decisões de produto Q1/Q2/Q3 (TIDs deixam gaps deliberados para inserir refinamentos sem renumerar; `[P]` em ~98 delas).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: dependências e configurações compartilhadas por todas as user stories. Sem `[USx]`.

- [ ] T001 [P] Adicionar `league/csv` ao composer.json — `composer.json`
  - Descrição: rodar `vendor/bin/sail composer require league/csv:^9.0` para parsing CSV streaming. Confirmar lock atualizado.
  - Aceitação: `vendor/bin/sail composer show league/csv` lista versão instalada.
  - Depende de: —
  - Princípio: Restrições Técnicas

- [ ] T002 [P] Adicionar `phpoffice/phpspreadsheet` ao composer.json — `composer.json`
  - Descrição: `vendor/bin/sail composer require phpoffice/phpspreadsheet:^4.0` para parse de `.xlsx`. Configurar `readDataOnly` por padrão no service.
  - Aceitação: `vendor/bin/sail composer show phpoffice/phpspreadsheet` lista versão instalada.
  - Depende de: —
  - Princípio: Restrições Técnicas

- [ ] T003 Configurar fila dedicada `imports` no Horizon — `config/horizon.php`, `config/queue.php`
  - Descrição: criar supervisor `imports` em `config/horizon.php` (`processes: 2`, `tries: 3`, `timeout: 600`, `balance: simple`); criar conexão `imports` em `config/queue.php` apontando para mesma engine Redis com `queue: imports` por padrão. Limitar concorrência para evitar noisy-neighbor entre tenants.
  - Aceitação: `vendor/bin/sail artisan horizon:status` lista supervisor `imports`; `vendor/bin/sail artisan queue:work imports --once` consome com sucesso.
  - Depende de: —
  - Princípio: II, V

- [ ] T004 [P] Configuração de timeline tracked fields — `config/paciente.php`
  - Descrição: criar `config/paciente.php` com chave `timeline.tracked_fields = ['status','telefone_primario','email','profissional_responsavel_id','convenio_principal_id']` e `timeline.tracked_relations = ['tag','convenio']`. Centraliza a whitelist da R4.
  - Aceitação: `vendor/bin/sail artisan config:show paciente.timeline.tracked_fields` retorna array esperado.
  - Depende de: —
  - Princípio: V, IV

- [ ] T005 [P] Configuração de limites de importação — `config/paciente.php`
  - Descrição: estender `config/paciente.php` com `import.max_size_mb=5`, `import.max_rows=10000`, `import.batch_size=100`. Centraliza R5.
  - Aceitação: leitura via `config('paciente.import.max_rows')` retorna `10000`.
  - Depende de: T004
  - Princípio: V

- [ ] T006 [P] Configuração de rate limiters novos — `app/Providers/RouteServiceProvider.php`
  - Descrição: adicionar limiters `import` (5/h por tenant) e `export` (10/h por tenant) em `configureRateLimiting()`. Usar chave `Limit::perHour(N)->by("$tenant_id:$endpoint")`.
  - Aceitação: feature test confirma 429 na 6ª chamada de import e 11ª de export no mesmo tenant.
  - Depende de: —
  - Princípio: VII

- [ ] T007 [P] Storage para arquivos de import — `config/filesystems.php`
  - Descrição: adicionar disk `imports` local com root `storage/app/imports/{tenant_id}`. Confirmar permissão de escrita em runtime.
  - Aceitação: `Storage::disk('imports')->put('test.txt', 'ok')` funciona.
  - Depende de: —
  - Princípio: II

- [ ] T008 [P] Lang pt-BR estendido para CRM — `lang/pt_BR/paciente.php`
  - Descrição: criar arquivo de chaves traduzidas para pacientes/anotações/tags/funil/import/export. Lista mínima: `paciente.cpf_invalido`, `paciente.status.*`, `tag.sys_reservado`, `funil.motivo.*`, `import.limite_excedido`, `merge.expirada`, etc.
  - Aceitação: `__('paciente.status.lead')` retorna `Lead`; sem chaves missing.
  - Depende de: —
  - Princípio: Localização

- [ ] T009 [P] Vue i18n pt-BR estendido — `resources/js/i18n/pt-BR.json`
  - Descrição: adicionar namespaces `paciente.*`, `anotacao.*`, `tag.*`, `funil.*`, `import.*`, `convenio.*`, `mesclagem.*` em pt-BR. Estrutura espelha `lang/pt_BR/paciente.php` no front.
  - Aceitação: `vendor/bin/sail npm run build` sem warnings; `t('paciente.list.title')` resolve.
  - Depende de: —
  - Princípio: Localização, Restrições Técnicas

- [ ] T009a [P] Adicionar `vuedraggable@^4` ao `package.json` — `package.json`
  - Descrição: `vendor/bin/sail npm install vuedraggable@next --save` (~12KB, SortableJS-based). Decisão de produto Q1/A2 do `/speckit.analyze` (2026-05-11): substituí drag-and-drop nativo HTML5 para garantir consistência UX desktop+mobile no Kanban (US4) e simplificar fractional indexing.
  - Aceitação: `vendor/bin/sail npm run build` sucesso; bundle final +12KB.
  - Depende de: —
  - Princípio: Restrições Técnicas

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: nenhuma user story pode começar antes deste bloco. Inclui migrations, abilities, traits, infra de timeline e extensão de auditoria.

### 2.1 — Extensões PostgreSQL

- [ ] T010 Migration `enable_pg_trgm_and_unaccent` — `database/migrations/2026_05_11_000001_enable_pg_trgm_and_unaccent.php`
  - Descrição: `DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm')` e `IF NOT EXISTS unaccent`. Idempotente. `down()` faz `DROP EXTENSION` com guard.
  - Aceitação: `\dx` no psql lista `pg_trgm` e `unaccent`.
  - Depende de: —
  - Princípio: Restrições Técnicas, II

### 2.2 — Migrations das 11 entidades (data-model.md §1–§11)

- [ ] T011 [P] Migration `pacientes` — `database/migrations/2026_05_11_000002_create_pacientes_table.php`
  - Descrição: schema completo da data-model.md §1. Colunas GENERATED (`nome_normalizado`, `telefone_primario_normalizado`). FKs e CHECK constraints. **Sem** índices GIN ainda (vão em migration separada após `pg_trgm`).
  - Aceitação: `migrate` cria tabela; `\d pacientes` confirma 21 colunas.
  - Depende de: T010
  - Princípio: II, I

- [ ] T012 [P] Migration `convenios` — `database/migrations/2026_05_11_000003_create_convenios_table.php`
  - Descrição: schema §2; UNIQUE `(tenant_id, nome)`.
  - Aceitação: tabela criada com índice unique.
  - Depende de: T010
  - Princípio: II

- [ ] T013 [P] Migration `paciente_convenios` — `database/migrations/2026_05_11_000004_create_paciente_convenios_table.php`
  - Descrição: schema §3; UNIQUE `(paciente_id, papel)`; FK CASCADE para pacientes, RESTRICT para convenios.
  - Aceitação: insert tentando 2 principais para mesmo paciente falha por unique.
  - Depende de: T011, T012
  - Princípio: II

- [ ] T014 [P] Migration `tags` — `database/migrations/2026_05_11_000005_create_tags_table.php`
  - Descrição: schema §4; UNIQUE `(tenant_id, nome_normalizado)`.
  - Aceitação: insert de `Diabético` e `diabetico` no mesmo tenant falha unique.
  - Depende de: T010
  - Princípio: II

- [ ] T015 [P] Migration `paciente_tags` — `database/migrations/2026_05_11_000006_create_paciente_tags_table.php`
  - Descrição: schema §5; UNIQUE `(paciente_id, tag_id)`.
  - Aceitação: tag não duplica em paciente.
  - Depende de: T011, T014
  - Princípio: II

- [ ] T016 Migration `anotacoes` + trigger imutabilidade — `database/migrations/2026_05_11_000007_create_anotacoes_table.php`
  - Descrição: schema §6; trigger PG `anotacoes_immutable` rejeita UPDATE/DELETE (mesma estratégia de `audit_logs` Fase 0). FK `retratacao_de_anotacao_id` autoreferencial.
  - Aceitação: insert ok; update/delete via SQL retorna erro do trigger.
  - Depende de: T011
  - Princípio: I, V

- [ ] T017 Migration `eventos_timeline` + trigger imutabilidade — `database/migrations/2026_05_11_000008_create_eventos_timeline_table.php`
  - Descrição: schema §7; mesmo padrão de imutabilidade. Indexes principais + BRIN em `created_at`.
  - Aceitação: trigger ativo; query por `(tenant_id, paciente_id, created_at DESC)` usa index.
  - Depende de: T011
  - Princípio: I, V

- [ ] T018 [P] Migration `importacoes` — `database/migrations/2026_05_11_000009_create_importacoes_table.php`
  - Descrição: schema §8. CHECK `status IN (pending, processing, completed, partial_failure, failed, retrying)`. Index `(tenant_id, status, created_at DESC)`.
  - Aceitação: tabela criada; CHECK constraint válida.
  - Depende de: T011
  - Princípio: II, V

- [ ] T019 [P] Migration `mesclagens_pacientes` — `database/migrations/2026_05_11_000010_create_mesclagens_pacientes_table.php`
  - Descrição: schema §9. Index `(tenant_id, reversivel_ate, revertida_em)` para purge mensal.
  - Aceitação: tabela criada.
  - Depende de: T011
  - Princípio: I, V

- [ ] T020 [P] Migration `funil_colunas` — `database/migrations/2026_05_11_000011_create_funil_colunas_table.php`
  - Descrição: schema §10. UNIQUE `(tenant_id, slug)` e `(tenant_id, posicao)`.
  - Aceitação: insert ok; colisão de slug falha.
  - Depende de: T010
  - Princípio: II

- [ ] T021 [P] Migration `tarefas_reatribuicao` — `database/migrations/2026_05_11_000012_create_tarefas_reatribuicao_table.php`
  - Descrição: schema §11. Index `(tenant_id, concluida_em)` para listar pendentes.
  - Aceitação: tabela criada.
  - Depende de: T010
  - Princípio: II

- [ ] T022 Migration índices trigram em `pacientes` — `database/migrations/2026_05_11_000013_add_pacientes_trigram_indexes.php`
  - Descrição: criar GIN indexes `pacientes_nome_trgm_idx` em `(tenant_id, nome_normalizado gin_trgm_ops)` e `pacientes_telefone_trgm_idx` em `(tenant_id, telefone_primario_normalizado gin_trgm_ops)`. Usar `DB::statement` direto.
  - Aceitação: `\d pacientes` lista os 2 indexes GIN; EXPLAIN da query de busca usa o index.
  - Depende de: T010, T011
  - Princípio: Restrições Técnicas

### 2.3 — Models e relações

- [ ] T023 [P] Model `Paciente` com trait `BelongsToTenant` + casts + relações — `app/Models/Paciente.php`
  - Descrição: campos fillable, casts (`telefones_secundarios=>array`, `endereco=>AsJsonArray`, `anonimizado_em=>datetime`), relations `tags`, `convenios`, `anotacoes`, `eventosTimeline`, `profissionalResponsavel`, `funilColuna`. Global scope que oculta `anonimizado_em IS NOT NULL`.
  - Aceitação: `Paciente::factory()->create()->tags()->count()` é 0; query padrão exclui anonimizados.
  - Depende de: T011
  - Princípio: I, II

- [ ] T024 [P] Models `Convenio` + `PacienteConvenio` — `app/Models/Convenio.php`, `app/Models/PacienteConvenio.php`
  - Descrição: ambos com `BelongsToTenant`. PacienteConvenio é pivot com `papel` cast como string.
  - Aceitação: `$paciente->convenios` retorna collection com `pivot.papel`.
  - Depende de: T012, T013
  - Princípio: II

- [ ] T025 [P] Models `Tag` + `PacienteTag` — `app/Models/Tag.php`, `app/Models/PacienteTag.php`
  - Descrição: `BelongsToTenant`; `Tag::scopeLivre()`, `scopeSistemica()`.
  - Aceitação: `Tag::livre()->get()->pluck('tipo')->unique()` retorna `['livre']`.
  - Depende de: T014, T015
  - Princípio: II

- [ ] T026 [P] Model `Anotacao` + bloqueio em `updating`/`deleting` — `app/Models/Anotacao.php`
  - Descrição: `BelongsToTenant`; boot lança `AnotacaoImutavelException` em update/delete. Relations `paciente`, `autor`, `retratacaoDe`, `retratacoes`.
  - Aceitação: `$anotacao->update([...])` lança exceção; `$anotacao->delete()` idem.
  - Depende de: T016
  - Princípio: I

- [ ] T027 [P] Model `EventoTimeline` + bloqueio imutabilidade — `app/Models/EventoTimeline.php`
  - Descrição: mesmo padrão de `AuditLog` (Fase 0). Casts `payload=>AsJsonArray`.
  - Aceitação: update/delete via Model lança exceção.
  - Depende de: T017
  - Princípio: I, V

- [ ] T028 [P] Models `Importacao`, `MesclagemPaciente`, `FunilColuna`, `TarefaReatribuicao` — `app/Models/*.php`
  - Descrição: 4 Models com `BelongsToTenant`. Casts apropriados (`checkpoint=>AsJsonArray`, `snapshot_pre_merge=>AsJsonArray`, `relatorio=>AsJsonArray`, `pacientes_origem_ids=>array`, etc.).
  - Aceitação: cada Model instancia via factory e relacionamentos funcionam.
  - Depende de: T018, T019, T020, T021
  - Princípio: II

- [ ] T029 [P] Exception `AnotacaoImutavelException` — `app/Exceptions/Pacientes/AnotacaoImutavelException.php`
  - Descrição: extends `\RuntimeException`. Mapeada em `bootstrap/app.php` para 409.
  - Aceitação: exception map produz JSON `{error: 'anotacao_imutavel'}`.
  - Depende de: T026
  - Princípio: I

### 2.4 — Spatie abilities + assignment

- [ ] T030 [P] **TEST** Permissões CRM aplicadas por perfil — `tests/Feature/Fase2/Pacientes/PacientePermissionsTest.php`
  - Descrição: verifica que cada um dos 9 abilities (`paciente.view/create/update/delete/import/export/merge/note.write/note.view:{tipo}`) é gateado conforme tabela da spec § 2.4. Inclui asserts 403 para Financeiro e Super Admin em endpoints de paciente.
  - Aceitação: SC-010 atendido — 100% das tentativas de Financeiro/Super Admin retornam 403.
  - Depende de: T030 (TDD primeiro; passa após T032)
  - Princípio: II, VII, IV

- [ ] T031 Seeder Spatie estendido com abilities CRM — `database/seeders/RolesSeeder.php`
  - Descrição: criar 9 permissions globais (`tenant_id NULL`) e atribuí-las aos roles template conforme tabela § 2.4. Idempotente.
  - Aceitação: T030 passa para a porção de "ability existe".
  - Depende de: T031 (faz parte do mesmo arquivo modificado da Fase 0)
  - Princípio: II, VII

- [ ] T032 Policies — `app/Policies/{PacientePolicy,AnotacaoPolicy,TagPolicy,ConvenioPolicy,FunilPolicy}.php`
  - Descrição: 5 policies cobrindo `viewAny/view/create/update/delete/export/import/merge`. `AnotacaoPolicy` aplica visibilidade granular por tipo lendo abilities `paciente.note.view:{tipo}`. Registrar em `AppServiceProvider::boot()`.
  - Aceitação: T030 passa completo.
  - Depende de: T030, T031
  - Princípio: II, VII

### 2.5 — Audit pseudonimização + Auditable events

- [ ] T033 [P] Estender `AuditAttributesBuilder` para mascarar CPF — `app/Services/Audit/AuditAttributesBuilder.php`
  - Descrição: adicionar regra à `sanitizePayload`: se key é `cpf` ou contém `cpf_`, mascarar para `***.***.***-XX` (preservar últimos 2 dígitos). Idempotente; não afeta payloads sem CPF.
  - Aceitação: teste unit `AuditAttributesBuilderCpfMaskTest` confirma máscara aplicada (R11).
  - Depende de: —
  - Princípio: I

- [ ] T034 [P] 13 Events da Fase 2 (skeleton) — `app/Events/Paciente/*.php`
  - Descrição: criar 13 classes evento implementando `App\Events\Contracts\Auditable` via trait `IsAuditable`: `PacienteCriado`, `PacienteAtualizado`, `PacienteStatusAlterado`, `PacienteMesclado`, `PacienteMesclagemRevertida`, `PacienteAnonimizado`, `TagAplicada`, `TagRemovida`, `LeadMovidoNoFunil`, `AnotacaoCriada`, `AnotacaoRetratada`, `PacientesImportados`, `PacientesExportados`. Cada um expõe `auditAction()`, `auditPayload()`, `auditableModel()`.
  - Aceitação: dispatch via `Event::dispatch(new PacienteCriado(...))` grava em `audit_logs` automaticamente via listener wildcard da Fase 0.
  - Depende de: —
  - Princípio: I, V

- [ ] T035 [P] Listener `RegistraEventoTimelineListener` — `app/Listeners/Paciente/RegistraEventoTimelineListener.php`
  - Descrição: escuta `Auditable` para tipos relacionados a paciente (filtro por `auditableModel()` ser instance de `Paciente`/`Anotacao`/`Tag`) e grava em `eventos_timeline`. Bind em `EventServiceProvider`.
  - Aceitação: dispatch de qualquer evento da Fase 2 cria linha em `eventos_timeline` além de `audit_logs`.
  - Depende de: T017, T027, T034
  - Princípio: V

### 2.6 — Support classes

- [ ] T036 [P] `CpfValidator` (DV algoritmo BR) — `app/Support/Cpf/CpfValidator.php` + teste unit
  - Descrição: método estático `isValid(string $digits): bool` com algoritmo padrão BR de DV. Rejeitar todos os dígitos iguais (`00000000000`).
  - Aceitação: teste unit `tests/Unit/Support/CpfValidatorTest.php` (3 válidos, 3 inválidos, 3 edge: vazio, <11 chars, all zeros).
  - Depende de: —
  - Princípio: VII, IV

- [ ] T037 [P] `TelefoneNormalizer` (E.164 + display BR) — `app/Support/Telefone/TelefoneNormalizer.php` + teste unit
  - Descrição: `normalize(string $raw): string` retorna E.164 (`+55...`). `format(string $e164): string` retorna `(31) 99999-9999`. Lida com fixo e celular, com DDD com/sem 9 inicial.
  - Aceitação: teste unit com 8 casos (entrada/saída).
  - Depende de: —
  - Princípio: Localização

- [ ] T038 [P] `TagNormalizer` (case + accent-insensitive) — `app/Support/Tags/TagNormalizer.php` + teste unit
  - Descrição: `normalize(string $name): string` → `Diabético` → `diabetico`. Usa `Transliterator` ou regex de normalização Unicode. Trim + lower.
  - Aceitação: teste unit com 6 casos incluindo emoji (rejeitado), espaços múltiplos, acentos compostos.
  - Depende de: —
  - Princípio: Localização

- [ ] T039 [P] Estender `DevSeeder` com 30 pacientes em `clinica-alfa` — `database/seeders/DevSeeder.php`
  - Descrição: criar 30 pacientes via factory com nomes realistas, telefones, CPFs válidos, distribuição de status e tags. Idempotente (não duplica em reseed).
  - Aceitação: `vendor/bin/sail artisan db:seed --class=DevSeeder` cria 30 pacientes em `clinica-alfa`; reseed mantém o mesmo número.
  - Depende de: T023, T024, T025, T028
  - Princípio: IV

**Checkpoint**: Foundational completo — todas as 11 migrations rodando, models prontos, abilities aplicadas, audit estendido. User stories podem começar.

---

## Phase 3: User Story 1 — Cadastro Manual de Paciente (Priority: P1) 🎯 MVP

**Goal**: usuário autorizado cadastra paciente manualmente com todos os campos da spec; deduplicação por CPF é oferecida; isolamento por tenant garantido.

**Independent Test**: Atendente cria paciente novo via `POST /api/v1/pacientes` em tenant A; resposta 201 com paciente válido; tentativa de criar mesmo CPF dispara 409 com sugestão; cross-tenant retorna 404.

### Tests for User Story 1 (TDD-first — Princípio IV)

- [ ] T100 [P] [US1] **TEST** Cadastro happy path — `tests/Feature/Fase2/Pacientes/PacienteCadastroTest.php`
  - Descrição: cobre AC-3.1.1, AC-3.1.5, AC-3.1.6, AC-3.1.7, AC-3.1.8. Login admin-clinica; POST `/pacientes`; assert 201, evento `PacienteCriado`, AuditLog gravado, profissional vinculado.
  - Aceitação: todos os ACs cobertos. Falha antes da impl; passa após T110.
  - Depende de: T030, T032, T034
  - Princípio: II, IV, V

- [ ] T101 [P] [US1] **TEST** Validação de CPF — `tests/Feature/Fase2/Pacientes/PacienteCpfValidationTest.php`
  - Descrição: AC-3.1.2. POST com CPF DV inválido → 422; POST sem CPF (apenas nome+telefone) → 201; POST com `documento_estrangeiro` → 201.
  - Aceitação: 3 cenários verde.
  - Depende de: T036
  - Princípio: VII, IV

- [ ] T102 [P] [US1] **TEST** Deduplicação na criação — `tests/Feature/Fase2/Pacientes/PacienteDedupTest.php`
  - Descrição: AC-3.1.3. Cria paciente com CPF X; POST outro com mesmo CPF → 409 + `DedupSuggestionResponse` com candidatos. POST com `ignorar_duplicata=true` → 201. Telefone duplicado NÃO dispara dedup.
  - Aceitação: 3 cenários verde.
  - Depende de: T100
  - Princípio: I, IV

- [ ] T103 [P] [US1] **TEST** Isolamento entre tenants — `tests/Feature/Fase2/Pacientes/PacienteIsolationTest.php`
  - Descrição: AC-3.1.4. Cria pacientes em tenant A e B com mesmo CPF (UNIQUE composto permite). User de A não enxerga paciente de B em listagem/show/search.
  - Aceitação: 4 cenários (listagem, show, search, dedup) cross-tenant retorna 404/vazio.
  - Depende de: T100
  - Princípio: II, IV

- [ ] T104 [P] [US1] **TEST** Mesclagem reversível — `tests/Feature/Fase2/Pacientes/PacienteMergeTest.php`
  - Descrição: cria 2 pacientes duplicados; merge via `POST /pacientes/mesclagens`; absorvido marcado com `merged_into_paciente_id`; snapshot completo persiste; evento `PacienteMesclado` gravado; reversão dentro de 30 dias restaura tudo; reversão após 30 dias retorna 410.
  - Aceitação: 5 cenários verde. R7.
  - Depende de: T100
  - Princípio: I, IV

- [ ] T105 [P] [US1] **TEST** Anonimização (stub LGPD) — `tests/Feature/Fase2/Pacientes/PacienteAnonimizacaoTest.php`
  - Descrição: FR-035. POST `/pacientes/{id}/anonimizar` zera PII (cpf/telefone/email/endereco/data_nascimento), seta `anonimizado_em`, dispara `PacienteAnonimizado`. GET subsequente retorna 404. Audit log preservado.
  - Aceitação: 4 cenários verde.
  - Depende de: T100
  - Princípio: I, IV

- [ ] T106 [P] [US1] **TEST** Busca por similaridade — `tests/Feature/Fase2/Pacientes/PacienteSearchTest.php`
  - Descrição: SC-011, FR-040. Seedar 5 pacientes com nomes próximos. Busca por `?q=maria` retorna todos ordenados por similaridade. Busca por número parcial de telefone retorna match. Skip explícito se driver não é PostgreSQL.
  - Aceitação: 4 cenários verde em PG.
  - Depende de: T022, T100
  - Princípio: Restrições Técnicas, IV

### Implementation for User Story 1

- [ ] T110 [US1] `PacienteService` (cria/atualiza/anonimiza) — `app/Services/Pacientes/PacienteService.php`
  - Descrição: métodos `create(array $data, ?Tenant $tenant): Paciente`, `update(Paciente $p, array $data): Paciente`, `anonymize(Paciente $p, User $executor): void`. `create` orquestra: valida CPF, canonicaliza telefone, persiste, sincroniza tags+convenios+profissional, dispara `PacienteCriado` (que via listener registra em timeline + audit).
  - Aceitação: T100 e T105 passam.
  - Depende de: T100, T105, T036, T037
  - Princípio: II, I, V

- [ ] T111 [US1] `DedupService` — `app/Services/Pacientes/DedupService.php`
  - Descrição: `detectDuplicates(array $payload, Tenant $tenant): Collection<Paciente>` — busca por CPF primeiro; se CPF nulo, fallback telefone primário (não dispara modal de dedup, apenas para reimport). Retorna candidatos com `match_score`. Não persiste nada.
  - Aceitação: T102 passa.
  - Depende de: T102, T110
  - Princípio: I, II

- [ ] T112 [US1] `MergeService` (mesclagem reversível) — `app/Services/Pacientes/MergeService.php`
  - Descrição: `merge(Paciente $alvo, Collection<Paciente> $origens, array $resolutions, User $executor): MesclagemPaciente` — snapshot completo, move anotações/tags/convenios/timeline, marca origens, dispara `PacienteMesclado`. `revert(MesclagemPaciente $m, User $executor): void` — restaura snapshot, marca `revertida_em`, dispara `PacienteMesclagemRevertida`.
  - Aceitação: T104 passa.
  - Depende de: T104, T110
  - Princípio: I, IV, V

- [ ] T113 [US1] `AnonimizacaoService` — `app/Services/Pacientes/AnonimizacaoService.php`
  - Descrição: limpa PII direta do paciente (zerar campos), seta `anonimizado_em = now()`, dispara `PacienteAnonimizado`. Audit log preserva trace mas paciente fica fora de listagens.
  - Aceitação: T105 passa.
  - Depende de: T105, T110
  - Princípio: I

- [ ] T114 [US1] Form Requests — `app/Http/Requests/Pacientes/{Create,Update}PacienteRequest.php`
  - Descrição: regras conforme `openapi.yaml > CreatePacienteRequest`. Validação de CPF via `CpfValidator`. Mensagens em pt-BR. `authorize()` checa Policy.
  - Aceitação: T100, T101 passam.
  - Depende de: T036, T032
  - Princípio: VII, Localização

- [ ] T115 [US1] Form Request `MesclagemRequest` — `app/Http/Requests/Pacientes/MesclagemRequest.php`
  - Descrição: valida `paciente_alvo_id`, `pacientes_origem_ids` (1..5), `resolucoes` opcional. Garante que todos os pacientes existem no tenant.
  - Aceitação: T104 passa para validações.
  - Depende de: T112
  - Princípio: II

- [ ] T116 [US1] Resources — `app/Http/Resources/{Paciente,PacienteList,Convenio,PacienteConvenio,Mesclagem}Resource.php`
  - Descrição: serialização conforme openapi.yaml. `PacienteResource` formata CPF/telefone para display BR; calcula `idade` server-side.
  - Aceitação: shape do response bate com openapi.yaml.
  - Depende de: T110
  - Princípio: Restrições Técnicas

- [ ] T117 [US1] `PacientesController` (CRUD + busca + anonimizar) — `app/Http/Controllers/Api/V1/Pacientes/PacientesController.php`
  - Descrição: `index` (paginado, filtros, busca trigram), `show`, `store` (handle 409 dedup), `update`, `destroy` (soft delete; raramente usado), `anonimizar`. Cada método usa Service + Policy + Request.
  - Aceitação: T100-T106 passam (parte HTTP).
  - Depende de: T110, T111, T113, T114, T116
  - Princípio: II, IV

- [ ] T118 [US1] `MesclagemController` — `app/Http/Controllers/Api/V1/Pacientes/MesclagemController.php`
  - Descrição: `store(MesclagemRequest)` cria merge; `reverter(int $id)` chama `MergeService::revert`. Trata exceções para 410/422.
  - Aceitação: T104 passa.
  - Depende de: T112, T115
  - Princípio: I, IV

- [ ] T119 [US1] `PatchStatusController` (transição de status) — `app/Http/Controllers/Api/V1/Pacientes/PatchStatusController.php`
  - Descrição: `__invoke(int $id, PatchStatusRequest)` aplica máquina de estados (`lead → ativo`, `ativo ↔ inativo`, `* → bloqueado` apenas Admin). Dispara `PacienteStatusAlterado`.
  - Aceitação: AC-3.5.5 e AC-3.5.6 passam (testes em US5 mas controller vem aqui pra ter ficha completa funcional).
  - Depende de: T110
  - Princípio: II

- [ ] T120 [US1] Rotas em `routes/api.php`
  - Descrição: grupo `auth:sanctum` + `tenant.not-suspended` + middleware existente; registrar `Route::apiResource('pacientes', ...)`, `pacientes/{id}/status`, `pacientes/{id}/anonimizar`, `pacientes/mesclagens`, `pacientes/mesclagens/{id}/reverter`. Throttles `api`.
  - Aceitação: `vendor/bin/sail artisan route:list --path=pacientes` lista 9 rotas.
  - Depende de: T117, T118, T119
  - Princípio: II

- [ ] T121 [US1] `PacienteFactory` — `database/factories/PacienteFactory.php`
  - Descrição: factory gera nome BR realista, CPF válido, telefone formatado E.164, distribuição de status. State `lead()`, `ativo()`, `inativo()`, `bloqueado()`, `anonimizado()`.
  - Aceitação: `Paciente::factory()->ativo()->create()` retorna paciente válido.
  - Depende de: T023
  - Princípio: IV

- [ ] T122 [US1] `ConvenioFactory` — `database/factories/ConvenioFactory.php`
  - Descrição: factory com nomes reais ("Unimed", "Amil", "Bradesco Saúde"), código ANS opcional.
  - Aceitação: factory funciona; usada nos testes.
  - Depende de: T024
  - Princípio: IV

- [ ] T123 [P] [US1] Vue: `PacientesListPage.vue` — `resources/js/pages/pacientes/PacientesListPage.vue`
  - Descrição: tabela paginada com filtros (status, tag multi-select, profissional, origem, convênio, data). Campo busca com debounce 350ms chama composable `usePacienteSearch`. Ações por linha: ver, editar, anonimizar, exportar (botão global).
  - Aceitação: smoke test estático em `tests/Unit/Frontend/PacientesListFrontendTest.php`.
  - Depende de: T120
  - Princípio: Restrições Técnicas, Localização

- [ ] T124 [P] [US1] Vue: `PacienteFormPage.vue` (criar/editar) — `resources/js/pages/pacientes/PacienteFormPage.vue`
  - Descrição: form completo com máscara CPF (reusa do RegisterTenantPage Fase 0), máscara telefone, dropdown convênios (até 2), tag picker, profissional responsável. Trata 409 dedup mostrando `DedupSuggestionModal`. Trata 422.
  - Aceitação: smoke test estático.
  - Depende de: T120, T123
  - Princípio: Restrições Técnicas

- [ ] T125 [P] [US1] Vue: `PacienteShowPage.vue` (ficha) — `resources/js/pages/pacientes/PacienteShowPage.vue`
  - Descrição: tabs `Detalhes | Timeline | Anotações | Mesclagens`. Detalhes mostra todos os campos formatados. Status com badge + botão de transição. Botão "Anonimizar" para Admin.
  - Aceitação: smoke test estático.
  - Depende de: T120, T123
  - Princípio: Restrições Técnicas

- [ ] T126 [P] [US1] Vue: `DedupSuggestionModal.vue` — `resources/js/components/pacientes/DedupSuggestionModal.vue`
  - Descrição: modal disparado pelo 409 mostrando candidatos lado a lado + 3 ações: Mesclar (abre `MesclagemPage`), Criar paralelo (resubmit com `ignorar_duplicata=true`), Abrir existente.
  - Aceitação: smoke test estático.
  - Depende de: T124
  - Princípio: Restrições Técnicas

- [ ] T127 [P] [US1] Vue: `MesclagemPage.vue` — `resources/js/pages/pacientes/MesclagemPage.vue`
  - Descrição: tela de mesclagem com diff lado a lado dos campos conflitantes; cliente envia resoluções; confirmação via `ConfirmModal` (existente da Fase 0).
  - Aceitação: smoke test estático.
  - Depende de: T120, T126
  - Princípio: Restrições Técnicas

- [ ] T128 [P] [US1] Composable `usePacienteSearch` — `resources/js/composables/usePacienteSearch.js`
  - Descrição: debounce 350ms + cache de 10 últimas buscas + cancelamento de request anterior via AbortController.
  - Aceitação: importado por `PacientesListPage.vue`.
  - Depende de: —
  - Princípio: Restrições Técnicas

- [ ] T129 [US1] Router + i18n keys US1 — `resources/js/router/index.js`, `resources/js/i18n/pt-BR.json`
  - Descrição: adicionar 5 rotas (`pacientes.list`, `pacientes.create`, `pacientes.show`, `pacientes.edit`, `pacientes.mesclagem`) com `requiresAuth`. Adicionar chaves i18n específicas que faltaram em T009.
  - Aceitação: navegação funciona; sem chaves missing no console.
  - Depende de: T123, T124, T125, T127
  - Princípio: Restrições Técnicas, Localização

**Checkpoint US1**: cadastro manual fim a fim funcional. Atendente cria paciente, sistema deduplica, médico vê ficha completa, admin pode anonimizar.

---

## Phase 4: User Story 2 — Linha do Tempo Unificada (Priority: P1)

**Goal**: visualizar histórico de eventos próprios do CRM em ordem cronológica reversa, com filtros e anotações tipadas imutáveis.

**Independent Test**: após criar paciente, alterar telefone, adicionar tag e adicionar anotação, GET `/pacientes/{id}/timeline` retorna 4 eventos ordenados; filtro `?tipo=anotacao.criada` retorna apenas 1; anotação tipo `clinica` não aparece para perfil Atendente.

### Tests for User Story 2

- [ ] T140 [P] [US2] **TEST** Timeline com eventos próprios — `tests/Feature/Fase2/Pacientes/PacienteTimelineTest.php`
  - Descrição: cobre AC-3.2.1, AC-3.2.2, AC-3.2.3, AC-3.2.6, AC-3.2.8. Cria paciente, altera campos significativos vs. não-significativos; assert que apenas significativos viram evento. Filtros funcionam. p95 < 1s para 1000 eventos.
  - Aceitação: 5 cenários verde.
  - Depende de: T035 (listener), T100 (paciente existe)
  - Princípio: V, IV

- [ ] T141 [P] [US2] **TEST** Anotações tipadas e visibilidade — `tests/Feature/Fase2/Pacientes/PacienteAnotacaoTest.php`
  - Descrição: cobre AC-3.2.4. Cria anotações dos 4 tipos com 4 perfis diferentes; assert visibilidade por perfil + tipo. Atendente NÃO vê anotação `clinica`.
  - Aceitação: matriz 4x4 verde.
  - Depende de: T032 (AnotacaoPolicy)
  - Princípio: I, II, IV

- [ ] T142 [P] [US2] **TEST** Imutabilidade + retratação — `tests/Feature/Fase2/Pacientes/AnotacaoImutabilidadeTest.php`
  - Descrição: cobre AC-3.2.5. Update direto via Model lança exceção. POST retratação cria nova anotação linkada. Timeline mostra ambas (original com flag "retratada por…").
  - Aceitação: 3 cenários verde.
  - Depende de: T029 (exception), T026 (model)
  - Princípio: I, IV

### Implementation for User Story 2

- [ ] T150 [US2] `TimelineService` (query da timeline) — `app/Services/Pacientes/TimelineService.php`
  - Descrição: `forPaciente(Paciente $p, array $filters, ?string $cursor, int $limit): array` — paginação cursor-based em `eventos_timeline`. Aplica `AnotacaoVisibilityScope` via JOIN condicional quando tipo é `anotacao.*`.
  - Aceitação: T140 passa.
  - Depende de: T027, T140
  - Princípio: I, V

- [ ] T151 [US2] `AnotacaoService` — `app/Services/Pacientes/AnotacaoService.php`
  - Descrição: `create(Paciente $p, array $data, User $autor): Anotacao` dispara `AnotacaoCriada`. `retratar(Anotacao $original, string $texto, User $autor): Anotacao` cria nova com `retratacao_de_anotacao_id` preenchido e dispara `AnotacaoRetratada`.
  - Aceitação: T141, T142 passam.
  - Depende de: T026, T141, T142
  - Princípio: I, IV

- [ ] T152 [US2] Form Requests — `app/Http/Requests/Pacientes/{CreateAnotacao,Retratacao}Request.php`
  - Descrição: rules conforme openapi.yaml. Tipo enum estrito. Texto 1..5000 chars (retratação min 10).
  - Aceitação: validação passa nos testes.
  - Depende de: T151
  - Princípio: VII, Localização

- [ ] T153 [US2] Resources — `app/Http/Resources/{Anotacao,EventoTimeline,Timeline}Resource.php`
  - Descrição: serialização conforme openapi.yaml. `EventoTimelineResource` formata actor + referencia. `TimelineResource` aplica cursor pagination meta.
  - Aceitação: shape bate com contrato.
  - Depende de: T150, T151
  - Princípio: Restrições Técnicas

- [ ] T154 [US2] `TimelineController` + `AnotacoesController` — `app/Http/Controllers/Api/V1/Pacientes/{TimelineController,AnotacoesController}.php`
  - Descrição: 3 endpoints (timeline, anotacao create, retratacao). Cada um usa Policy + Service + Request.
  - Aceitação: T140, T141, T142 passam (parte HTTP).
  - Depende de: T150, T151, T152, T153
  - Princípio: II, IV

- [ ] T155 [US2] Rotas timeline + anotações — `routes/api.php`
  - Descrição: `Route::get('pacientes/{id}/timeline', ...)`, `Route::post('pacientes/{id}/anotacoes', ...)`, `Route::post('pacientes/{id}/anotacoes/{anotacao_id}/retratacao', ...)`. Auth + tenant scope.
  - Aceitação: `route:list` lista 3 rotas.
  - Depende de: T154
  - Princípio: II

- [ ] T156 [P] [US2] `AnotacaoFactory` — `database/factories/AnotacaoFactory.php`
  - Descrição: factory com 4 estados (`geral`, `clinica`, `comportamental`, `financeira`) + state `retratacao_de($anotacao)`.
  - Aceitação: usada nos testes.
  - Depende de: T026
  - Princípio: IV

- [ ] T157 [P] [US2] Vue: `PacienteTimelineTab.vue` — `resources/js/components/pacientes/PacienteTimelineTab.vue`
  - Descrição: tab dentro de `PacienteShowPage` mostrando timeline cronológica reversa. Filtro por tipo (dropdown multi). Cursor pagination. Cada item via `TimelineEvent.vue`.
  - Aceitação: smoke test estático.
  - Depende de: T155
  - Princípio: Restrições Técnicas

- [ ] T158 [P] [US2] Vue: `TimelineEvent.vue` — `resources/js/components/pacientes/TimelineEvent.vue`
  - Descrição: componente que renderiza um evento de timeline com ícone por tipo, autor, timestamp formatado (BRT), e payload sumarizado.
  - Aceitação: smoke test estático.
  - Depende de: T157
  - Princípio: Restrições Técnicas

- [ ] T159 [P] [US2] Vue: `AnotacaoForm.vue` + integração com timeline — `resources/js/components/pacientes/AnotacaoForm.vue`
  - Descrição: form dentro do tab Anotações de `PacienteShowPage`. Dropdown tipo (4 opções). Textarea. Botão retratação ao lado de anotação existente do mesmo usuário (abre form de retratação inline).
  - Aceitação: smoke test estático.
  - Depende de: T155
  - Princípio: Restrições Técnicas

**Checkpoint US2**: timeline e anotações operacionais. Médico vê histórico completo; Atendente não enxerga anotações clínicas.

---

## Phase 5: User Story 3 — Importação em Massa (Priority: P2)

**Goal**: Admin importa planilha CSV/XLSX com até 10.000 linhas em background; recebe relatório detalhado de importações/duplicatas/erros; retomada automática em falha.

**Independent Test**: Admin baixa template; preenche 100 linhas (80 válidas, 20 com erros); upload retorna 202 com import_id; status busca `partial_failure`; relatório lista 80 importadas + 20 erros com motivo.

### Tests for User Story 3

- [ ] T170 [P] [US3] **TEST** Fluxo completo de importação — `tests/Feature/Fase2/Pacientes/ImportacaoTest.php`
  - Descrição: cobre AC-3.3.1 (template), AC-3.3.2 (upload async), AC-3.3.4 (parcial), AC-3.3.5 (dedup), AC-3.3.6 (status inicial), AC-3.3.8 (audit), AC-3.3.10 (não bloqueia).
  - Aceitação: 7 cenários verde.
  - Depende de: T034
  - Princípio: II, IV, V

- [ ] T171 [P] [US3] **TEST** Limites e formatos — `tests/Feature/Fase2/Pacientes/ImportacaoLimitsTest.php`
  - Descrição: cobre AC-3.3.3 (acima do limite). Arquivo > 5 MB → 413. > 10.000 linhas → 422. Cabeçalhos faltantes → 422.
  - Aceitação: 4 cenários verde.
  - Depende de: T005
  - Princípio: VII

- [ ] T172 [P] [US3] **TEST** Reimportação atualiza apenas vazios — `tests/Feature/Fase2/Pacientes/ImportacaoReimportTest.php`
  - Descrição: cobre AC-3.3.7. Importa 10 linhas. Reimporta 10 linhas com campos adicionais → relatório classifica como "atualizada por reimport"; campos preenchidos preservados.
  - Aceitação: 3 cenários verde.
  - Depende de: T170
  - Princípio: I, IV

- [ ] T173 [P] [US3] **TEST** Permissão restritiva (Admin apenas) — `tests/Feature/Fase2/Pacientes/ImportacaoPermissionTest.php`
  - Descrição: cobre AC-3.3.9. Médico/Atendente/Recepcionista tentam upload → 403. Admin Clínica → 202.
  - Aceitação: 4 cenários verde.
  - Depende de: T031
  - Princípio: VII

- [ ] T174 [P] [US3] **TEST** Retomada após falha de worker — `tests/Feature/Fase2/Pacientes/ImportacaoRetomadaTest.php`
  - Descrição: cobre R5. Job processa 200 linhas, é morto manualmente, retoma do checkpoint (linha 200) sem reprocessar. Hash do arquivo mismatch durante retry marca `failed`.
  - Aceitação: 3 cenários verde.
  - Depende de: T170
  - Princípio: V, IV

### Implementation for User Story 3

- [ ] T180 [US3] `ImportacaoService` (orquestrador) — `app/Services/Pacientes/ImportacaoService.php`
  - Descrição: `create(UploadedFile $file, string $statusInicial, User $executor): Importacao` valida tamanho/linhas, salva no disk `imports`, calcula SHA-256, persiste registro, despacha `ProcessPatientImportJob`.
  - Aceitação: T170, T171 passam (parte HTTP).
  - Depende de: T028, T170, T171
  - Princípio: II, V

- [ ] T181 [US3] `ProcessPatientImportJob` extends `TenantAwareJob` — `app/Jobs/Pacientes/ProcessPatientImportJob.php`
  - Descrição: `run()` processa em batches de 100 linhas, persiste checkpoint após cada batch em `Importacao.checkpoint`. Detecta extensão para escolher parser (`league/csv` ou `phpoffice/phpspreadsheet`). Tratamento de erro por linha não aborta lote. No final dispara `PacientesImportados` event.
  - Aceitação: T170, T174 passam.
  - Depende de: T180, T001, T002
  - Princípio: II, V

- [ ] T182 [US3] `CsvImportParser` + `XlsxImportParser` — `app/Services/Pacientes/Importadores/{CsvImportParser,XlsxImportParser}.php`
  - Descrição: cada parser implementa interface comum `ImportParser` com `iterate(): Generator` retornando arrays de linha. Streaming sempre.
  - Aceitação: T170 passa para ambos formatos.
  - Depende de: T001, T002
  - Princípio: V

- [ ] T183 [US3] `LinhaImportacaoValidator` — `app/Services/Pacientes/Importadores/LinhaImportacaoValidator.php`
  - Descrição: valida cada linha (CPF DV, telefone formato, data de nascimento, etc.). Retorna `['ok' => bool, 'motivo' => string|null, 'data_normalizada' => array]`.
  - Aceitação: usado pelo Job; testes cobrem 8 cenários comuns de erro.
  - Depende de: T036, T037, T038
  - Princípio: VII, IV

- [ ] T184 [US3] Template generator — `app/Services/Pacientes/TemplateGenerator.php`
  - Descrição: gera CSV ou XLSX vazio com cabeçalhos pt-BR + 1 linha exemplo. Retorna como streamed response.
  - Aceitação: AC-3.3.1 cumprido; download funciona.
  - Depende de: T001, T002
  - Princípio: Localização

- [ ] T185 [US3] Form Request `ImportPacientesRequest` — `app/Http/Requests/Pacientes/ImportPacientesRequest.php`
  - Descrição: validação multipart com `file:csv,xlsx`, `max:5120` KB, `status_inicial:in:lead,ativo`. Authorize via `paciente.import` ability.
  - Aceitação: T171, T173 passam.
  - Depende de: T005, T032
  - Princípio: VII

- [ ] T186 [US3] Resources `ImportacaoResource` — `app/Http/Resources/ImportacaoResource.php`
  - Descrição: serialização conforme openapi.yaml com `progress_percent` calculado de `checkpoint.linhas_processadas / total_linhas * 100`.
  - Aceitação: shape bate com contrato.
  - Depende de: T180
  - Princípio: Restrições Técnicas

- [ ] T187 [US3] `ImportacaoController` (template + upload + status) — `app/Http/Controllers/Api/V1/Pacientes/ImportacaoController.php`
  - Descrição: 3 endpoints: `template` (download), `store` (upload com rate limit `import`), `show` (status). Cada um valida ability + tenant.
  - Aceitação: T170-T174 passam (parte HTTP).
  - Depende de: T180, T184, T185, T186
  - Princípio: II, VII

- [ ] T188 [US3] Rotas import — `routes/api.php`
  - Descrição: `Route::get('pacientes/importacao/template', ...)`, `Route::post('pacientes/importacao', ...)->middleware('throttle:import')`, `Route::get('pacientes/importacao/{id}', ...)`.
  - Aceitação: 3 rotas em `route:list`.
  - Depende de: T187, T006
  - Princípio: VII

- [ ] T189 [P] [US3] Vue: `ImportacaoPage.vue` (upload) — `resources/js/pages/pacientes/ImportacaoPage.vue`
  - Descrição: tela com botão "Baixar template", input file, dropdown status inicial. Submit → 202 → redireciona para `ImportacaoStatusPage`.
  - Aceitação: smoke test estático.
  - Depende de: T188
  - Princípio: Restrições Técnicas

- [ ] T190 [P] [US3] Vue: `ImportacaoStatusPage.vue` (progresso + relatório) — `resources/js/pages/pacientes/ImportacaoStatusPage.vue`
  - Descrição: poll a cada 3s para `/pacientes/importacao/{id}`. Barra de progresso. Quando `completed`/`partial_failure`, mostra relatório tabelado com linhas + status + motivo.
  - Aceitação: smoke test estático.
  - Depende de: T189
  - Princípio: Restrições Técnicas

- [ ] T191 [P] [US3] Vue: rotas e i18n US3 — `resources/js/router/index.js`, `resources/js/i18n/pt-BR.json`
  - Descrição: 2 rotas (`import.upload`, `import.status`); chaves i18n `import.*`.
  - Aceitação: navegação ok.
  - Depende de: T189, T190
  - Princípio: Localização

- [ ] T192 [P] [US3] `ExportacaoService` (cross-cutting com US1) — `app/Services/Pacientes/ExportacaoService.php`
  - Descrição: `stream(callable $writer, array $filters): array` — escreve CSV streaming usando `CsvExporter` da Fase 0 (já tem escape de formula injection). Calcula SHA-256 conforme escreve. Retorna `['hash', 'count', 'bytes']`.
  - Aceitação: hash bate com download.
  - Depende de: T117
  - Princípio: I, VII

- [ ] T193 [US3] `ExportacaoController` — `app/Http/Controllers/Api/V1/Pacientes/ExportacaoController.php`
  - Descrição: `__invoke()` aplica mesmos filtros do `index` de PacientesController. Após streaming, dispara `PacientesExportados` event com hash. Throttle `export`.
  - Aceitação: AC-3.3.10 cumprido; audit log gravado com hash.
  - Depende de: T192, T006
  - Princípio: I, V, VII

- [ ] T194 [US3] Rota export — `routes/api.php`
  - Descrição: `Route::get('pacientes/exportar', ExportacaoController::class)->middleware('throttle:export')->name('pacientes.exportar')`.
  - Aceitação: rota em route:list.
  - Depende de: T193
  - Princípio: VII

**Checkpoint US3**: importação assíncrona robusta com retomada; exportação auditada com hash; permissões restritivas.

---

## Phase 6: User Story 4 — Funil de Leads (Kanban) (Priority: P2)

**Goal**: visualizar/mover leads em Kanban configurável; movimentação para "Perdido" exige motivo; auto-movimentação prevista para Fase 5+.

**Independent Test**: GET `/funil/colunas` retorna 5 colunas default no primeiro acesso; PATCH paciente para coluna "Perdido" sem motivo → 422; com motivo → 200 e timeline registra `funil.movimentacao`.

### Tests for User Story 4

- [ ] T200 [P] [US4] **TEST** Funil Kanban — `tests/Feature/Fase2/Pacientes/FunilKanbanTest.php`
  - Descrição: cobre AC-3.4.1 (template seed lazy), AC-3.4.2 (card info), AC-3.4.3 (drag-and-drop persistido), AC-3.4.4 (motivo Perdido), AC-3.4.6 (filtros), AC-3.4.7 (funil ≠ status).
  - Aceitação: 6 cenários verde.
  - Depende de: T028, T034
  - Princípio: II, IV

- [ ] T201 [P] [US4] **TEST** Configuração de colunas — `tests/Feature/Fase2/Pacientes/FunilConfigTest.php`
  - Descrição: PATCH para renomear coluna preserva slug; mudar `motivo_obrigatorio=true` em coluna não-terminal funciona; usuário comum não pode criar coluna (apenas Admin).
  - Aceitação: 4 cenários verde.
  - Depende de: T032
  - Princípio: II

- [ ] T202 [P] [US4] **TEST** Movimentação automática (gancho Fase 5) — `tests/Feature/Fase2/Pacientes/FunilAutoMoveTest.php`
  - Descrição: AC-3.4.5. Simula evento externo (Fase 5 enviaria via dispatch direto); `FunilService::moveCard()` é chamado com `automatico=true`; evento `LeadMovidoNoFunil` tem `automatico=true` no payload.
  - Aceitação: 1 cenário verde.
  - Depende de: T034
  - Princípio: IV (contrato estável)

### Implementation for User Story 4

- [ ] T210 [US4] `FunilTemplateService` (lazy init) — `app/Services/Funil/FunilTemplateService.php`
  - Descrição: `ensureTenantHasColumns(Tenant $t): Collection<FunilColuna>` cria as 5 colunas default em uma transação se ainda não existem.
  - Aceitação: T200 passa para AC-3.4.1.
  - Depende de: T028, T200
  - Princípio: II

- [ ] T211 [US4] `FunilService` (movimentação) — `app/Services/Funil/FunilService.php`
  - Descrição: `moveCard(Paciente $p, FunilColuna $destino, ?float $posicao, ?string $motivo, ?string $motivoOutro, bool $automatico = false): Paciente` valida transição (motivo obrigatório se coluna pede), grava `funil_coluna_atual_id` e `funil_posicao`, dispara `LeadMovidoNoFunil`. Posição via fractional indexing.
  - Aceitação: T200, T202 passam.
  - Depende de: T028, T034, T200, T202
  - Princípio: IV, V

- [ ] T212 [US4] Form Request `MoveCardRequest` — `app/Http/Requests/Pacientes/MoveCardRequest.php`
  - Descrição: rules: `coluna_id required exists`, `posicao numeric optional`, `motivo in:sem_interesse,sem_retorno,preco,outro`, `motivo_outro required_if:motivo,outro|min:10|max:255`.
  - Aceitação: T200 passa para AC-3.4.4.
  - Depende de: T211
  - Princípio: VII

- [ ] T213 [US4] Resources `FunilColunaResource` — `app/Http/Resources/FunilColunaResource.php`
  - Descrição: serialização conforme contrato; inclui `pacientes_count` em listagens.
  - Aceitação: shape bate.
  - Depende de: T210
  - Princípio: Restrições Técnicas

- [ ] T214 [US4] `FunilController` + `FunilColunasController` — `app/Http/Controllers/Api/V1/Pacientes/FunilController.php`
  - Descrição: `colunas` (GET — chama TemplateService), `updateColuna(int $id)`, `moveCard(Paciente $p)`. Cada um com Policy.
  - Aceitação: T200, T201, T202 passam (parte HTTP).
  - Depende de: T210, T211, T212, T213
  - Princípio: II

- [ ] T215 [US4] Rotas funil — `routes/api.php`
  - Descrição: `GET/PATCH funil/colunas/{id?}`, `PATCH pacientes/{id}/funil`.
  - Aceitação: 3 rotas em route:list.
  - Depende de: T214
  - Princípio: II

- [ ] T216 [P] [US4] Vue: `FunilKanbanPage.vue` (board) — `resources/js/pages/pacientes/FunilKanbanPage.vue`
  - Descrição: layout horizontal com colunas via `KanbanBoard.vue`. Drag-and-drop nativo HTML5. Filtros (canal, profissional, data). On drop calcula `posicao = (anterior + proximo) / 2`. Trata 422 (motivo).
  - Aceitação: smoke test estático.
  - Depende de: T215
  - Princípio: Restrições Técnicas

- [ ] T217 [P] [US4] Vue: `KanbanBoard.vue` + `KanbanColumn.vue` + `PacienteCard.vue` — `resources/js/components/funil/*.vue`
  - Descrição: componentes do Kanban via `vuedraggable` (T009a). `KanbanBoard` envolve `<draggable>` por coluna com `group="funil"` permitindo drop cross-coluna; `KanbanColumn` mostra contador, título e accepts drop; `PacienteCard` mostra nome, canal, última interação, valor estimado (placeholder). Fractional indexing: ao on-end, calcular `posicao = (anterior + proximo) / 2` antes de fazer PATCH para `pacientes/{id}/funil`.
  - Aceitação: smoke test estático; drag funciona em desktop e touch.
  - Depende de: T216, T009a
  - Princípio: Restrições Técnicas

- [ ] T218 [P] [US4] Vue: `FunilConfigPage.vue` (configurar colunas) — `resources/js/pages/pacientes/FunilConfigPage.vue`
  - Descrição: form simples para Admin renomear/reordenar/marcar `motivo_obrigatorio` em colunas. Drag-and-drop para reordenar.
  - Aceitação: smoke test estático.
  - Depende de: T215
  - Princípio: Restrições Técnicas

- [ ] T219 [P] [US4] Vue: `PerdidoMotivoModal.vue` — `resources/js/components/pacientes/PerdidoMotivoModal.vue`
  - Descrição: modal disparado pelo drop em coluna `motivo_obrigatorio=true`. Radio com 4 motivos + textarea quando `outro`. Reusa `ConfirmModal` da Fase 0 como base.
  - Aceitação: smoke test estático.
  - Depende de: T216
  - Princípio: Restrições Técnicas

**Checkpoint US4**: Kanban operacional; admin configura colunas; movimentação manual e gancho automático prontos.

---

## Phase 7: User Story 5 — Segmentação por Tags e Status (Priority: P2)

**Goal**: tags globais com sistêmicas; máquina de estados explícita; busca/filtro por tag+status.

**Independent Test**: cria 3 tags livres + 1 sistêmica; aplica em pacientes; busca por tag retorna corretos; transição inválida → 422.

### Tests for User Story 5

- [ ] T230 [P] [US5] **TEST** Tags globais + normalização — `tests/Feature/Fase2/Pacientes/TagSegmentacaoTest.php`
  - Descrição: cobre AC-3.5.1 (criação livre), AC-3.5.2 (sistêmicas com sys:), AC-3.5.3 (limite soft 10), AC-3.5.4 (multi-tag), AC-3.5.7 (busca por tag+status).
  - Aceitação: 5 cenários verde.
  - Depende de: T025, T038
  - Princípio: II, IV

- [ ] T231 [P] [US5] **TEST** Máquina de estados — `tests/Feature/Fase2/Pacientes/PacienteStatusMachineTest.php`
  - Descrição: cobre AC-3.5.5 (mudança registrada), AC-3.5.6 (transições inválidas), AC-3.5.8 ("bloqueado" gates). Cada transição testada; bloqueado por Médico → 422; bloqueado por Admin → 200.
  - Aceitação: matriz de transições verde.
  - Depende de: T119
  - Princípio: II, IV, VII

- [ ] T232 [P] [US5] **TEST** Convênios CRUD + uso — `tests/Feature/Fase2/Pacientes/ConvenioCrudTest.php`
  - Descrição: cria 3 convênios; associa 2 a paciente; tenta excluir convênio em uso → 409; deactivate idempotente.
  - Aceitação: 5 cenários verde.
  - Depende de: T024
  - Princípio: II

### Implementation for User Story 5

- [ ] T240 [US5] `TagService` (find or create + normalização) — `app/Services/Pacientes/TagService.php`
  - Descrição: `findOrCreate(string $name, Tenant $t, User $autor): Tag` normaliza nome via `TagNormalizer`, bloqueia prefixo `sys:` para usuário comum, retorna existente ou cria livre. `apply(Paciente $p, Tag $t)` e `remove(Paciente $p, Tag $t)` disparam eventos.
  - Aceitação: T230 passa.
  - Depende de: T025, T038, T230
  - Princípio: II

- [ ] T241 [US5] `StatusMachineService` — `app/Services/Pacientes/StatusMachineService.php`
  - Descrição: `transition(Paciente $p, string $novoStatus, User $executor, ?string $motivo): Paciente` valida transições permitidas (`lead → ativo`, `ativo ↔ inativo`, `* → bloqueado` apenas Admin, `bloqueado → ativo` apenas Admin). Dispara `PacienteStatusAlterado`.
  - Aceitação: T231 passa.
  - Depende de: T119, T231
  - Princípio: II, VII

- [ ] T242 [US5] `ConvenioService` — `app/Services/Convenios/ConvenioService.php`
  - Descrição: CRUD de convênios. `destroy` verifica uso por pacientes antes de permitir delete; oferece deactivate (`is_active=false`) como alternativa.
  - Aceitação: T232 passa.
  - Depende de: T024, T232
  - Princípio: II

- [ ] T243 [US5] Form Requests — `app/Http/Requests/{Pacientes/CreateTagRequest,Convenios/CreateConvenioRequest}.php`
  - Descrição: rules conforme openapi.yaml. Bloqueio de prefixo `sys:` em `CreateTagRequest`.
  - Aceitação: validação passa.
  - Depende de: T240, T242
  - Princípio: VII

- [ ] T244 [US5] Resources `TagResource` + `ConvenioResource` — `app/Http/Resources/{Tag,Convenio}Resource.php`
  - Descrição: serialização. `TagResource` inclui `pacientes_count` opcional.
  - Aceitação: shape bate com contrato.
  - Depende de: T240, T242
  - Princípio: Restrições Técnicas

- [ ] T245 [US5] `TagsController` + `ConveniosController` + `PacienteTagsController` — `app/Http/Controllers/Api/V1/Pacientes/*.php`
  - Descrição: 7 endpoints: `tags.index`, `tags.store`, `paciente.tags.attach`, `paciente.tags.detach`, `convenios.index`, `convenios.store`, `convenios.update`, `convenios.destroy`.
  - Aceitação: T230, T232 passam (parte HTTP).
  - Depende de: T240, T242, T243, T244
  - Princípio: II

- [ ] T246 [US5] Rotas tags + convenios — `routes/api.php`
  - Descrição: 8 rotas novas (apiResource para tags, convenios + endpoint custom de attach/detach). **Nota**: a rota `PATCH /pacientes/{id}/status` já é registrada em T120 (US1) e **NÃO** deve ser duplicada aqui.
  - Aceitação: route:list confirma.
  - Depende de: T245
  - Princípio: II

- [ ] T247 [P] [US5] `TagFactory` — `database/factories/TagFactory.php`
  - Descrição: factory para tags. State `sistemica()` aplica prefixo `sys:`.
  - Aceitação: usada em testes.
  - Depende de: T025
  - Princípio: IV

- [ ] T248 [P] [US5] Vue: `TagPicker.vue` + ConvenioForm + ConveniosListPage — `resources/js/components/pacientes/TagPicker.vue`, `resources/js/pages/convenios/{ConveniosListPage,ConvenioFormPage}.vue`
  - Descrição: TagPicker oferece autocomplete (cria novas livre); sistêmicas read-only. CRUD de convênios em listas separadas (Admin).
  - Aceitação: smoke test estático.
  - Depende de: T246
  - Princípio: Restrições Técnicas, Localização

- [ ] T249 [P] [US5] Vue: extender `PacienteShowPage.vue` com seções status/tags/convenios — `resources/js/pages/pacientes/PacienteShowPage.vue`
  - Descrição: adicionar UI inline para mudar status (dropdown + modal de confirmação com motivo), aplicar/remover tags via `TagPicker`, gerenciar até 2 convênios.
  - Aceitação: smoke test estático.
  - Depende de: T125, T248
  - Princípio: Restrições Técnicas

**Checkpoint US5**: tags + status + convênios operacionais; busca por tag+status responde em p95 < 300ms.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: ajustes finais, profissional desativado, E2E, OpenAPI drift check, quickstart real, coverage gate.

- [ ] T260 [P] Profissional desativado: listener + job — `app/Listeners/Pacientes/ProfessionalDeactivatedListener.php`, `app/Jobs/Pacientes/ReassignOrphansJob.php`
  - Descrição: observer no Model `Professional` detecta `is_active true→false` e dispara `ProfessionalDeactivated`. Listener cria `TarefaReatribuicao` + dispatch do job que atualiza pacientes para `profissional_responsavel_id = null`. Audit `profissional.desativado`.
  - Aceitação: teste feature `ProfessionalDeactivatedTest`: desativar profissional com 3 pacientes vinculados cria 1 tarefa + 3 pacientes ficam órfãos.
  - Depende de: T021, T034
  - Princípio: II, V

- [ ] T261 [P] Purge mensal de snapshots de merge — `app/Jobs/Pacientes/PurgeOldMergeSnapshotsJob.php`, `routes/console.php`
  - Descrição: job mensal que zera `snapshot_pre_merge` JSONB de mesclagens cujo `reversivel_ate + 30 days` já passou. Preserva metadados do registro.
  - Aceitação: teste feature com mesclagem antiga → snapshot vazio após job.
  - Depende de: T019
  - Princípio: I

- [ ] T262 [P] Atualizar OpenAPI `openapi.yaml` real com Scribe + drift check — `specs/002-crm-pacientes/contracts/openapi.yaml`, anotações Scribe em todos os Controllers Fase 2
  - Descrição: rodar `vendor/bin/sail artisan scribe:generate`; comparar com `openapi.yaml` manual; reconciliar diferenças. Adicionar entradas dos 27 endpoints novos no whitelist do `openapi:check`.
  - Aceitação: `vendor/bin/sail artisan openapi:check` exit 0.
  - Depende de: T120, T155, T188, T194, T215, T246
  - Princípio: IV

- [ ] T263 [P] Atualizar `quickstart.md` com observações reais — `specs/002-crm-pacientes/quickstart.md`
  - Descrição: validar manualmente os 14 passos; ajustar URLs e comandos reais; documentar gotchas encontrados.
  - Aceitação: novo dev consegue subir + testar a feature em < 30 min seguindo o doc.
  - Depende de: todas as fases anteriores
  - Princípio: IV

- [ ] T264 [P] Verificação final de cobertura ≥ 75% local — `phpunit.xml`
  - Descrição: rodar `vendor/bin/sail artisan test --coverage --min=75 tests/Feature/Fase2/`. Adicionar testes unitários extras se necessário para fechar gap.
  - Aceitação: comando retorna sem erro.
  - Depende de: todas as fases
  - Princípio: IV

- [ ] T265 [P] `TenantIsolationTest` expandido com 27 endpoints novos — `tests/Feature/Fase0/Tenant/TenantIsolationTest.php`
  - Descrição: estender o teste da Fase 0 com cada um dos 27 endpoints novos: tenant A não enxerga recursos de tenant B.
  - Aceitação: 100% cobertura nos endpoints da Fase 2.
  - Depende de: T120, T155, T188, T194, T215, T246
  - Princípio: II, IV

- [ ] T266 [P] Pint clean global — `pint.json`
  - Descrição: `vendor/bin/sail bin pint --dirty --format agent` em toda codebase Fase 2.
  - Aceitação: 0 diffs.
  - Depende de: todas as fases
  - Princípio: IV

- [ ] T267 [P] **E2E** Jornada completa de CRM — `tests/e2e/crm-paciente-jornada-completa.spec.ts`
  - Descrição: Playwright: login admin-clinica → cadastrar paciente novo → aplicar tag → adicionar anotação clínica → mover no funil → conferir timeline → exportar CSV. Conclui em < 90s.
  - Aceitação: spec verde em CI headless.
  - Depende de: T129, T155, T215, T246, T194
  - Princípio: IV

- [ ] T268 [P] Documentar extensão de `Professional` em `data-model.md` da Fase 2 — `specs/002-crm-pacientes/data-model.md`
  - Descrição: garantir que a seção **§ 12 "Extensão de `professionals` (Fase 0)"** do `data-model.md` da Fase 2 (já presente) está completa: observer `deactivated`, evento `ProfessionalDeactivated`, listener, job `ReassignOrphansJob`, ausência de migration nova. **NÃO** modificar `specs/001-fundacao-multitenant/data-model.md` (artefato de fase já entregue — migrations imutáveis).
  - Aceitação: seção § 12 do `specs/002-crm-pacientes/data-model.md` reflete o que foi efetivamente implementado em T260.
  - Depende de: T260
  - Princípio: IV (artefatos de fase passada são imutáveis)

- [ ] T269 [P] Adicionar seção "CRM Pacientes (Fase 2)" em `CLAUDE.md` — `CLAUDE.md`
  - Descrição: adicionar 4 bullets sob a seção SPECKIT (ou em seção dedicada se preferível): (1) **pg_trgm + unaccent** habilitados em PG; buscas por nome/telefone usam `% similarity` com índice GIN composto; (2) **Cast `AsJsonArray`** (já criado na Fase 0) é o padrão para JSONB em colunas multi-valor (checkpoints de import, snapshot de merge, payload de evento); (3) **Listener `RegistraEventoTimelineListener`** grava em `eventos_timeline` ao receber qualquer `Auditable` cujo `auditableModel()` seja instance de `Paciente`/`Anotacao`/`Tag`; (4) **Abilities granulares `paciente.note.view:{tipo}`** controlam visibilidade de anotações por perfil + tipo (4 tipos: `geral`/`clinica`/`comportamental`/`financeira`).
  - Aceitação: novos devs leem CLAUDE.md e identificam os 4 padrões sem precisar varrer `app/`.
  - Depende de: todas as fases
  - Princípio: IV

- [ ] T270 [P] Atualizar `checklists/requirements.md` marcando tudo `[x]` — `specs/002-crm-pacientes/checklists/requirements.md`
  - Descrição: marcar todos os itens como concluídos. Anotar quaisquer drifts não esperados.
  - Aceitação: doc em estado final.
  - Depende de: T262, T263, T264, T265, T266
  - Princípio: IV

- [ ] T271 [P] Sentry: capturar tenant_id + paciente_id em erros — `app/Providers/AppServiceProvider.php`
  - Descrição: estender contexto do Sentry para incluir `paciente_id` quando a request tem `route('id')` resolvida como Paciente. Já incluímos `tenant_id` (Fase 0).
  - Aceitação: erro em endpoint de paciente carrega `paciente_id` no Sentry context.
  - Depende de: T117
  - Princípio: V

- [ ] T272 [P] Metric: contagem de pacientes/tenant exposta em Prometheus — `app/Http/Controllers/MetricsController.php`
  - Descrição: gauge `paciente360_pacientes_total{tenant_id="..."}`. Atualizado por listener nos eventos `PacienteCriado`/`PacienteAnonimizado`/`PacienteMesclado`.
  - Aceitação: scrape do `/metrics` mostra gauge.
  - Depende de: T034
  - Princípio: V

- [ ] T272a [P] Widget Filament Super Admin: contagem agregada de pacientes/tenant — `app/Filament/Widgets/TenantPacientesWidget.php`
  - Descrição: `TableWidget` no painel Super Admin (`/admin`) listando `[slug, nome, status, total_pacientes_ativos, total_pacientes_lead, total_anonimizados]` por tenant. **Apenas contagens agregadas — NUNCA PII**. Query usa `Paciente::query()->withoutGlobalScopes()->groupBy('tenant_id', 'status')->selectRaw('tenant_id, status, count(*)')`. Cumpre FR-038 100% (decisão Q2/C1 do `/speckit.analyze`, 2026-05-11).
  - Aceitação: teste feature `tests/Feature/Fase2/Admin/TenantPacientesWidgetTest.php` confirma que (1) Super Admin acessa o widget, (2) Admin Clínica recebe 403, (3) widget mostra contagens corretas, (4) widget **NÃO** expõe nome/CPF/telefone/email de paciente em nenhum payload.
  - Depende de: T030, T272
  - Princípio: II, I, FR-038

- [ ] T273 [P] Documentação dos eventos de domínio para fases futuras — `docs/domain-events.md`
  - Descrição: documento descrevendo os 13 eventos da Fase 2 com payload e exemplo de subscriber em fases futuras. Public contract.
  - Aceitação: arquivo criado e linkado em `CLAUDE.md`.
  - Depende de: T034
  - Princípio: IV

> **T274 removida** (decisão Q3/C2 do `/speckit.analyze`, 2026-05-11): archive da timeline > 2 anos não tem fundamento no spec da Fase 2 nem urgência operacional (50k pacientes × 50 eventos/ano = 2.5M linhas/tenant, suportável em PG indexado por ~3-5 anos). Movido para backlog da **Fase 8 (LGPD)** onde compõe naturalmente o fluxo de retenção/portabilidade.

- [ ] T275 [P] Suite de regressão Fase 0 — `tests/Feature/Fase0/`
  - Descrição: rodar `vendor/bin/sail artisan test --compact tests/Feature/Fase0/ tests/Unit/Frontend/` e garantir 0 regressões introduzidas pela Fase 2.
  - Aceitação: contagem ≥ 467 testes verdes da Fase 0.
  - Depende de: todas as fases
  - Princípio: IV

- [ ] T276 [P] Composer audit + segurança das deps novas — `composer.json`
  - Descrição: `vendor/bin/sail composer audit` — verificar CVEs em `league/csv` e `phpoffice/phpspreadsheet`.
  - Aceitação: 0 vulnerabilidades High/Critical.
  - Depende de: T001, T002
  - Princípio: VII

- [ ] T277 [P] LGPD review final — manual
  - Descrição: revisar lista: (1) toda PII em audit é sanitizada; (2) anonimização zera campos corretos; (3) export tem hash e audit; (4) anotação clínica fica restrita; (5) timeline não vaza dado entre perfis. Documentar em `docs/lgpd-checklist-fase2.md`.
  - Aceitação: checklist 100%.
  - Depende de: T117, T125, T154, T193
  - Princípio: I

- [ ] T278 [P] Final: contagem de testes + Sentry sanity check — manual
  - Descrição: confirmar suite total ≥ 567 testes (467 Fase 0 + ~100 Fase 2), 0 errors, 0 failures. Rodar build front + lint. Smoke manual em `clinica-alfa.lvh.me/panel/pacientes`.
  - Aceitação: tudo verde.
  - Depende de: todas as fases
  - Princípio: IV, V

- [ ] T279 Verificação constitucional pós-implementação — `specs/002-crm-pacientes/plan.md`
  - Descrição: re-verificar princípios I-VII após a feature pronta. Atualizar seção "Verificação Constitucional pós-design" do plan.md com status real.
  - Aceitação: nenhuma violação detectada; feature pronta para merge.
  - Depende de: T275, T277, T278
  - Princípio: I, II, IV, V, VII

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: independente; pode rodar em paralelo onde marcado [P].
- **Phase 2 (Foundational)**: depende de Phase 1; **bloqueia todas as user stories**.
- **Phase 3 (US1)**: depende de Phase 2 — MVP entregável.
- **Phase 4 (US2)**: depende de Phase 2 + integra com US1 (paciente existe).
- **Phase 5 (US3)**: depende de Phase 2 + reusa Services de US1 (Dedup, Tag, Convenio).
- **Phase 6 (US4)**: depende de Phase 2 + integra com US1 (paciente existe).
- **Phase 7 (US5)**: depende de Phase 2 + integra com US1 (status via paciente).
- **Phase 8 (Polish)**: depende de todas as fases anteriores.

### User Story Dependencies

- **US1 (P1)** — pode começar após Phase 2.
- **US2 (P1)** — pode começar após US1 (precisa de paciente existente para timeline).
- **US3 (P2)** — pode começar após US1 (precisa de PacienteService, DedupService).
- **US4 (P2)** — pode começar após US1 (paciente referencia coluna).
- **US5 (P2)** — pode começar após US1 (status transitions tocam paciente).

**Importante**: US2, US3, US4, US5 **podem rodar em paralelo entre si** após US1 entregue. É a estratégia ideal de equipe.

### Within Each User Story

- Testes (TDD) escritos primeiro e FAILING antes da implementação.
- Models antes de Services.
- Services antes de Controllers.
- Backend completo antes de Frontend.
- Cada AC do spec tem ao menos 1 teste rastreado.

### Parallel Opportunities

- Phase 1: T001, T002, T004, T005, T006, T007, T008, T009 em paralelo.
- Phase 2: migrations T011–T021 paralelas exceto onde há FK; Models T023–T028 paralelos; Support classes T036–T039 paralelas.
- US1: T100–T106 (tests) em paralelo; T123–T128 (Vue components) em paralelo.
- US2: T140–T142 (tests) em paralelo; T157–T159 (Vue) em paralelo.
- US3: T170–T174 (tests) em paralelo.
- US4: T200–T202 (tests) em paralelo; T216–T219 (Vue) em paralelo.
- US5: T230–T232 (tests) em paralelo; T247–T249 (Vue) em paralelo.
- Phase 8: 19 das 20 tasks são [P] — quase todas paralelizáveis.

---

## Parallel Example: User Story 1

```bash
# Lote A — Testes TDD em paralelo (todos falhando inicialmente):
- T100 PacienteCadastroTest
- T101 PacienteCpfValidationTest
- T102 PacienteDedupTest
- T103 PacienteIsolationTest
- T104 PacienteMergeTest
- T105 PacienteAnonimizacaoTest
- T106 PacienteSearchTest

# Lote B — Services e Form Requests (sequencial dentro do lote):
T110 PacienteService → T111 DedupService → T112 MergeService → T113 AnonimizacaoService → T114 Form Requests → T115 MesclagemRequest

# Lote C — Resources + Controllers (paralelo após Lote B):
T116 Resources → T117 PacientesController → T118 MesclagemController → T119 PatchStatusController → T120 Rotas

# Lote D — Frontend (paralelo):
T123 PacientesListPage.vue
T124 PacienteFormPage.vue
T125 PacienteShowPage.vue
T126 DedupSuggestionModal.vue
T127 MesclagemPage.vue
T128 usePacienteSearch composable
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1 (Setup) — 9 tasks.
2. Phase 2 (Foundational) — 30 tasks (todas as migrations + infra + abilities).
3. Phase 3 (US1 — Cadastro Manual) — 30 tasks.
4. **STOP & VALIDATE**: cadastro funcional + isolamento + dedup + mesclagem + anonimização.
5. Demonstrar — **MVP entregável já tem valor real** (clínica pode cadastrar pacientes).

### Incremental Delivery

1. Setup + Foundational → infraestrutura pronta.
2. **MVP (US1)** → cadastrar + buscar + mesclar + anonimizar.
3. **+ US2 (Timeline)** → contexto histórico para cada paciente.
4. **+ US3 (Importação)** → onboarding rápido de bases existentes.
5. **+ US4 (Funil)** → pipeline comercial visualizável.
6. **+ US5 (Tags+Status)** → segmentação para campanhas futuras.
7. **Polish** → ajustes finais + LGPD review + E2E.

### Parallel Team Strategy

Após Foundational completo, 4 desenvolvedores podem trabalhar em paralelo:

- Dev A: US1 (P1) — driver do MVP.
- Dev B: US2 (P1) — depois de US1 ter primeira versão de PacienteService.
- Dev C: US3 (P2) — depois de US1 ter Dedup + Tag + Convenio Services.
- Dev D: US4 + US5 (P2) — após US1 ter paciente persistido.

Polish (Phase 8) absorve quem terminar primeiro.

---

## Notes

- `[P]` tasks = arquivos distintos, sem dependência ativa.
- `[USx]` label rastreia para US do spec.
- Cada AC do spec § 11 deve ter ao menos 1 teste correspondente nos TIDs T100, T140, T170, T200, T230.
- Verifique tests **falhando** antes de implementar.
- Commit após cada task ou grupo lógico.
- Parar em cada Checkpoint para validar US independentemente.
- **NUNCA** quebrar a regra: "Pint clean + OpenAPI drift exit 0 + Tenant isolation expandido" antes de mergear.
- Coverage gate: ≥ 75% local da Fase 2, ≥ 70% global.
