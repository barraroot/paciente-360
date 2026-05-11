# Research: Fase 2 — CRM Core (Pacientes)

**Branch**: `002-crm-pacientes` | **Data**: 2026-05-11 | **Status**: Phase 0 — completo

Este documento consolida as decisões técnicas que orientarão `data-model.md`, `contracts/openapi.yaml` e a implementação. Todas as decisões aqui são respostas a questões levantadas no spec ou impostas pelas premissas da Fase 0.

> **Nota**: o spec já foi `Clarified` (13 NCs resolvidos via `/speckit.clarify`). Esta pesquisa **não revisita decisões de produto**; foca em decisões de engenharia que projetam aquelas decisões em código.

---

## R1 — Parser de planilhas (CSV/Excel)

**Decisão**: usar **`league/csv`** para `.csv` e **`phpoffice/phpspreadsheet`** para `.xlsx`/`.xls`. Roteamento por extensão.

**Rationale**:
- `league/csv` é a opção mais leve para CSV em PHP: 100% streaming via iterator (essencial para 10k linhas sem estourar memória do worker), encoding-aware (BOM UTF-8 honrado), zero dependências em ext-zip.
- `phpoffice/phpspreadsheet` é o padrão de fato para `.xlsx` no ecossistema Laravel. Usar **apenas para parse** (não geramos `.xlsx` nesta fase — export é só CSV).
- Evitamos `maatwebsite/excel`: envolve macros, eventos próprios, e mistura parse+queue em camadas que conflitam com o nosso `TenantAwareJob` da Fase 0. Sobrecarga conceitual indesejada.

**Alternativas consideradas**:
- `box/spout` (descontinuado em 2022 — descartado).
- `maatwebsite/excel` (camada extra de abstração; conflita com filas customizadas).
- Parser caseiro (reinventar a roda; risco de bug em CSV mal-formado).

**Implicação prática**:
- Job de import detecta extensão na request original; persiste apenas o arquivo no `storage/app/imports/{tenant}/{ulid}.xlsx`.
- Streaming: para CSV, usa `Reader::createFromPath`. Para XLSX, usa `IOFactory::load` com `setReadDataOnly(true)` (descarta fórmulas/estilos — só dados).

---

## R2 — Busca por similaridade (nome / telefone)

**Decisão**: usar **PostgreSQL `pg_trgm`** (trigram extension) com índice GIN composto em `(tenant_id, nome_normalizado gin_trgm_ops)` e `(tenant_id, telefone_primario_normalizado gin_trgm_ops)`.

**Rationale**:
- `pg_trgm` resolve **três problemas com uma única ferramenta**: erro de digitação (`% similarity`), busca parcial (`Maria` encontra "José Maria"), e normalização de acento via coluna pré-computada `nome_normalizado` (lowercased + accent-stripped via `unaccent`).
- Comparado com FTS (`tsvector`/`tsquery`), trigram é melhor para nomes próprios e telefones (não é "linguagem"). FTS é melhor para artigos/textos.
- Comparado com extensão `unaccent` apenas + LIKE: trigram tem performance constante com índice GIN; LIKE com prefixo coringa não usa índice.
- Atende SC-011 (p95 < 300ms em 50k pacientes) com folga em hardware modesto.

**Alternativas consideradas**:
- ElasticSearch / OpenSearch dedicado (over-engineering para 50k registros/tenant; outro serviço para manter).
- `tsvector` (pior para nomes próprios).
- LIKE simples (não atende SLA em 50k+).

**Implicação prática**:
- Migration nova: `CREATE EXTENSION IF NOT EXISTS pg_trgm; CREATE EXTENSION IF NOT EXISTS unaccent;` (idempotente).
- Coluna `nome_normalizado VARCHAR(150) GENERATED ALWAYS AS (lower(unaccent(nome))) STORED` — atualizada automaticamente.
- Mesmo padrão para `telefone_primario_normalizado` (apenas dígitos, via `regexp_replace`).
- Queries: `WHERE tenant_id = ? AND nome_normalizado % ? ORDER BY similarity(nome_normalizado, ?) DESC LIMIT 20`.
- Cliente Vue tem debounce 350ms + cache no composable `usePacienteSearch`.

---

## R3 — Normalização de Tags (case + accent-insensitive)

**Decisão**: armazenar tag com **dois campos**: `nome` (canônico do tenant — primeiro registrado, preserva display) e `nome_normalizado` (lower + unaccent + trim, UNIQUE composto com `tenant_id`).

**Rationale**:
- Permite que o usuário veja a tag exatamente como digitou pela primeira vez (`Diabético` com acento e maiúscula) mas garante que aplicar `diabetico` ou `DIABETICO` à um paciente **reusa** a mesma tag.
- A unique constraint é em `(tenant_id, nome_normalizado)` — não em `nome`.
- O slug `nome_normalizado` também é o que entra em queries de filtro: cliente envia `?tag=diabetico` e back compara contra `nome_normalizado`.

**Alternativas consideradas**:
- Armazenar só `nome` e fazer `lower(unaccent(nome))` em runtime — perdoa-se em pequena escala; ruim com índice; complica unique constraint.
- Padronizar a entrada pra lowercase: feio para o usuário ("diabetico" em vez de "Diabético").

**Implicação prática**:
- Service `TagService::findOrCreate($name)` faz `WHERE tenant_id = ? AND nome_normalizado = ?` antes de criar.
- Prefixo `sys:` reservado: regex `^sys:` bloqueia criação por usuário comum; apenas código interno pode criar/aplicar.

---

## R4 — Granularidade da Timeline

**Decisão**: apenas eventos **significativos** geram entrada na timeline, controlados por uma whitelist em `config('paciente.timeline.tracked_fields')`.

**Rationale**:
- Spec define os campos: `status`, `tag`, `telefone_primario`, `email`, `profissional_responsavel_id`, `convenio_principal`.
- Centraliza a configuração: se um campo for adicionado/removido da lista, alteramos config, não código.
- Implementação: observer `PacienteObserver` no método `updated` compara `getDirty()` com a whitelist; só dispara `PacienteAtualizado` se houver interseção.
- Demais alterações persistem no DB mas **não geram evento** (e portanto não inundam audit_logs nem timeline).

**Alternativas consideradas**:
- Cada `updated` dispara evento (alta verbosidade — falha SC-002 com 1000 eventos).
- Não persistir alterações não-significativas (rejeita liberdade do usuário de corrigir dados).

**Implicação prática**:
- Listener consumindo `PacienteAtualizado` grava em `EventoTimeline` (nossa tabela canônica de timeline) **e** em `audit_logs` (via `Auditable`).
- `EventoTimeline` é a fonte primária para a UI; `audit_logs` é cobertura LGPD.
- Diff sanitizado no payload do evento (apenas `[campo, old, new]`, com CPF mascarado se aparecer).

---

## R5 — Importação assíncrona com checkpoint e retomada

**Decisão**: importação em **conexão de fila dedicada `imports`** (Horizon supervisor próprio); job `ProcessPatientImportJob` processa em **batches de 100 linhas** com persistência de checkpoint em `Importacao.checkpoint` JSONB.

**Rationale**:
- Conexão dedicada isola noisy-neighbor: import de 10k linhas de um tenant não atrasa job crítico de outro (envio de e-mail, audit archive, etc.).
- Batch de 100 dá balanço entre overhead de DB transactions e granularidade do retry. Em 100 linhas/batch, 10k linhas = 100 transactions = ~10-30s; reinício custa no máximo 100 linhas.
- Checkpoint persiste: `linhas_processadas`, `ultima_linha_validada`, `contadores_parciais` (importadas / atualizadas / duplicatas / erros), `arquivo_hash` (SHA-256 do arquivo original — valida que o arquivo não mudou entre execuções).

**Alternativas consideradas**:
- Sem checkpoint, falha do worker = restart do zero: inviável em arquivo grande.
- Checkpoint por linha (DB call por linha): performance ruim.
- Filas em memória (sync queue): viola observabilidade e bloqueia request.

**Implicação prática**:
- Tabela `importacoes` tem `status enum(pending, processing, completed, partial_failure, failed, retrying)`.
- Failure de worker (OOM, sigkill) → Horizon retry → job verifica `Importacao.checkpoint` e retoma do último batch.
- Hash do arquivo é comparado antes de retomar — se mudou, marca status `failed` com `motivo='hash_mismatch_on_retry'`.

---

## R6 — Idempotência de cadastro e reimportação

**Decisão**: **dedupe sempre por CPF primeiro; fallback `telefone_primario` apenas quando CPF é nulo**. Cadastro manual mostra modal de sugestão; importação aplica `update_empty_fields_only` (preserva campos preenchidos).

**Rationale**:
- CPF é o identificador forte. Telefone é fraco (pessoa muda de número, família compartilha).
- "Update apenas campos vazios" preserva trabalho manual: se o atendente preencheu manualmente o endereço, reimport não sobrescreve.
- Spec define `MesclagemPaciente` como entidade separada para rastreabilidade — não é update em paciente, é registro de operação.

**Alternativas consideradas**:
- Match por nome + DOB (caro, propenso a homônimos).
- Sobrescrever sempre na reimportação (perde dado manual; usuário fica bravo).

**Implicação prática**:
- `DedupService::detectDuplicates(array $payload): Collection<Paciente>` retorna candidatos com score.
- Modal `DedupSuggestionModal.vue` mostra diff visual; usuário decide ação.
- Em importação, modal não existe — comportamento "update apenas vazios" é fixo.

---

## R7 — Mesclagem reversível (snapshot pre-merge)

**Decisão**: `MesclagemPaciente.snapshot_pre_merge JSONB` armazena o estado **completo** dos pacientes envolvidos no momento da operação (incluindo tags, anotações, convênios e eventos timeline). Reversão dentro de 30 dias restaura via `RevertMergeJob`.

**Rationale**:
- Snapshot completo evita LEFT JOINs históricos: o estado pré-merge é tudo que precisamos para restaurar.
- 30 dias é o equilíbrio entre dar tempo ao usuário de perceber erro e não inflar o JSONB com dados longevos. Após 30 dias, snapshot é purgado por job mensal (re-uso do padrão de archive da Fase 0).
- Reversão dispara `PacienteMesclagemRevertida` event — fases futuras podem reagir (ex.: re-popular timeline em UI).

**Alternativas consideradas**:
- Versionamento via `event sourcing` no `EventoTimeline`: poderia replay-restaurar, mas complica todos os outros fluxos.
- Apenas marcar `deleted_at` (sem reverter): perde anotações e tags do paciente "absorvido".

**Implicação prática**:
- `MergeService::merge(Paciente $alvo, Collection<Paciente> $origens, array $resolutions): Paciente`:
  1. Snapshot completo (paciente + relações) salvo em `mesclagens_pacientes.snapshot_pre_merge`.
  2. Move anotações, tags, eventos timeline para `$alvo`.
  3. Aplica resolução de campos conflitantes (regra "mais completo" ou override manual).
  4. Marca `$origens` com `merged_into_paciente_id = $alvo->id` e `merged_at = now()`.
  5. Dispara `PacienteMesclado` event.
- `RevertMergeJob`: restaura tudo, marca `MesclagemPaciente.revertida_em`, dispara `PacienteMesclagemRevertida`.

---

## R8 — Funil Kanban: persistência de colunas e movimentação

**Decisão**: tabela `funil_colunas` por tenant; coluna `funil_coluna_atual_id` no paciente (FK opcional). Template padrão é seedado por tenant na primeira leitura de `/funil/colunas` (lazy init).

**Rationale**:
- Colunas configuráveis por tenant exigem persistência (não constante).
- Lazy init evita pollution: tenants que não usam funil não recebem template até abrir a tela.
- Posição no card dentro da coluna: campo `funil_posicao DECIMAL(20,10)` no paciente (técnica de "fractional indexing" — insere entre dois cards sem reordenar todos).

**Alternativas consideradas**:
- Template fixo em config (não atende AC-3.4.1).
- Posição como `INT` ordenada (reordenar TODOS os cards ao inserir no meio — N writes).

**Implicação prática**:
- `FunilTemplateService::ensureTenantHasColumns(Tenant $t)` chamado quando o controller lista colunas pela primeira vez.
- `FunilService::moveCard(Paciente $p, FunilColuna $destino, ?string $motivo)` valida transição + grava posição + dispara `LeadMovidoNoFunil`.
- Drag-and-drop client-side calcula `posicao = (anterior + proximo) / 2`.

---

## R9 — Atribuição de profissional responsável e desativação

**Decisão**: listener `ProfessionalDeactivatedListener` reage ao evento `ProfessionalDeactivated` (criado nesta fase — extensão do model `Professional` da Fase 0); dispara `ReassignOrphansJob` que (a) atualiza `profissional_responsavel_id = null` em todos os pacientes vinculados, (b) cria `TarefaReatribuicao` para o Admin Clínica.

**Rationale**:
- Listener + Job desacopla a desativação do profissional (operação rápida na UI) do efeito (que pode tocar milhares de pacientes).
- `TarefaReatribuicao` é o registro que a UI da fila de tarefas (Fase 10) vai consumir — esta fase entrega só a criação.

**Alternativas consideradas**:
- Bloquear a desativação se houver pacientes vinculados: hostil para o usuário.
- Transferir automaticamente para outro profissional: requer regra de matching complexa que não está no escopo.

**Implicação prática**:
- Migration estende `Professional` com observer `deactivated` → dispara evento.
- `TarefaReatribuicao` tem `tenant_id, profissional_desativado_id, pacientes_orfaos_ids (JSONB), criada_em, concluida_em (NULL até conclusão)`.

---

## R10 — Auditoria de exportação (hash do arquivo)

**Decisão**: `PacientesExportados` event tem payload `{executor_id, escopo (filtros), contagem, arquivo_hash (SHA-256), formato='csv', tamanho_bytes}`. O arquivo NÃO é persistido após download (gerado streaming → cliente).

**Rationale**:
- Hash permite **provar** o que foi exportado se houver vazamento futuro, sem armazenar o conteúdo (LGPD: minimização).
- Cliente baixa direto via `window.location.href` (sem expor URL temporária pública).
- Endpoint `GET /pacientes/exportar` exige `paciente.export` ability (apenas Admin Clínica).

**Alternativas consideradas**:
- Persistir o arquivo gerado (viola minimização LGPD; cria responsabilidade de retenção).
- Apenas log "exportou N pacientes" sem hash (insuficiente para auditoria forense).

**Implicação prática**:
- `ExportacaoService::stream(callable $writer): array` calcula SHA-256 enquanto escreve no output stream usando `hash_init/hash_update/hash_final`.
- Resposta é `streamDownload(...)` (não passa pelo storage).
- AuditLog é gravado com o hash logo após streaming completo.

---

## R11 — Pseudonimização e LGPD em payload de evento

**Decisão**: `AuditAttributesBuilder` da Fase 0 já cobre sanitização de keys conhecidas (`password`, `token`, etc). Para esta fase, **estender** com sanitização de CPF: aparições de CPF em payload livre são mascaradas para `***.***.***-XX` (últimos 2 dígitos).

**Rationale**:
- Constituição (Princípio I) exige: "log de auditoria... payload sem PII desnecessária".
- CPF aparece em três contextos no payload: `(a)` evento de criação (campo direto), `(b)` evento de importação (não inclui CPF no payload — só counts), `(c)` retratação de anotação que mencione CPF (texto livre — não sanitizamos texto livre, apenas keys conhecidas).
- Tags do tipo `sys:` jamais contêm PII.

**Alternativas consideradas**:
- Não logar CPF: perde rastreabilidade ("qual paciente foi criado quando?"). Mascarar últimos 2 dígitos preserva debuggability sem expor identidade.
- Hash CPF: irreversível, dificulta investigação legítima.

**Implicação prática**:
- `AuditAttributesBuilder::sanitizePayload` ganha regra: se key é `cpf` ou contém `cpf_`, valor é mascarado.
- Texto livre (`anotacao.texto`, `motivo` de funil) não é sanitizado — responsabilidade do usuário (aviso UX explícito).

---

## R12 — Permissões granulares de anotação por tipo

**Decisão**: 4 abilities granulares `paciente.note.view:{tipo}` registradas na seed Spatie. Visibilidade aplicada **no momento da query** (não no render) via global scope em `Anotacao` que checa ability.

**Rationale**:
- Filtrar no query plan evita carregar PII desnecessária — defensa em profundidade.
- Re-uso do team mode Spatie já configurado na Fase 0 (cada tenant tem seu próprio set de abilities atribuídas).

**Alternativas consideradas**:
- Filtrar no client-side (vazamento óbvio).
- Filtrar apenas no Resource (carrega no model — viola minimização).

**Implicação prática**:
- `AnotacaoVisibilityScope` aplicado a todas as queries de `Anotacao`.
- Performance: query ganha `AND tipo IN (?)` com array curto (no máximo 4 valores) — negligible.

---

## R13 — Internacionalização e timezone

**Decisão**: timezone fixado `America/Sao_Paulo` no servidor para formatação BRT em UI e cálculos de "dias úteis"; armazenamento sempre UTC no DB (continua padrão Fase 0).

**Rationale**:
- Spec é Brasil-only neste MVP; complexidade de multi-timezone não justifica overhead.
- BRT consistente em logs, audit timestamps de UI, e cálculo de retenção de anonimização.

**Alternativas consideradas**:
- Timezone por tenant (overkill — todos os tenants são clínicas BR).
- Timezone por usuário (idem).

**Implicação prática**:
- Helper `formatDate` (já existe em `useI18nFormat`) usa `'pt-BR'` locale + timezone `'America/Sao_Paulo'`.
- `Carbon::setLocale('pt_BR')` + `config('app.timezone') = 'America/Sao_Paulo'` (já vigente).

---

## R14 — Estratégia de teste para `pg_trgm`

**Decisão**: testes que dependem de similaridade usam **base de teste real Postgres** (não sqlite). Skip explícito se rodar em sqlite (CI tem PG 18 já configurado para Fase 0).

**Rationale**:
- `pg_trgm` é específico do Postgres; sqlite não simula.
- Atestar similaridade em testes é crítico para AC-3.5.7 e SC-011.

**Alternativas consideradas**:
- Mock da query (perde valor do teste — bug de regex no like passaria batido).
- Implementar similaridade em PHP no test (caro, diverge do prod).

**Implicação prática**:
- `tests/Feature/Fase2/Pacientes/PacienteSearchTest.php` usa trait `RefreshDatabase` (já no PG).
- Asserts: criar pacientes com nomes próximos (`Maria José`, `Maria Jose`, `Mariah`), buscar `maria` → todos retornam ordenados por similaridade.

---

## Resumo das decisões

| ID | Tópico | Decisão |
|----|--------|---------|
| R1 | Parser CSV/XLSX | `league/csv` + `phpoffice/phpspreadsheet` |
| R2 | Busca por similaridade | `pg_trgm` GIN composto |
| R3 | Normalização de tags | coluna pré-computada + UNIQUE composta |
| R4 | Granularidade timeline | whitelist de campos significativos |
| R5 | Import assíncrono | Horizon supervisor `imports` + checkpoint 100 linhas |
| R6 | Dedup e reimport | CPF primário, telefone fallback, update apenas vazios |
| R7 | Mesclagem reversível | snapshot JSONB completo, 30 dias |
| R8 | Funil Kanban | fractional indexing + lazy init template |
| R9 | Profissional desativado | listener + ReassignOrphansJob |
| R10 | Audit de export | hash SHA-256 sem persistir arquivo |
| R11 | Pseudonimização CPF em logs | máscara `***.***.***-XX` |
| R12 | Visibilidade anotação por tipo | global scope no query |
| R13 | Timezone | fixado BRT no servidor |
| R14 | Teste de similaridade | PG real, skip sqlite |

Nenhuma decisão aqui muda contratos públicos do spec. Todas projetam decisões já tomadas em escolhas de engenharia. Pronto para Phase 1.
