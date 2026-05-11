# Data Model: Fase 2 — CRM Core (Pacientes)

**Branch**: `002-crm-pacientes` | **Data**: 2026-05-11 | **Status**: Phase 1 — completo

Modelo de dados das 11 entidades novas + extensão do model `Professional` (Fase 0). Todos os schemas seguem as convenções da Fase 0: PostgreSQL 18, `timestampsTz`, `softDeletesTz` onde aplicável, JSONB nativo, CHECK constraints emulando enum, FK explícitas com `ON DELETE` definido, índices por tenant.

## Convenções gerais

- Todas as tabelas (exceto `convenios` quando catálogo global futuro) têm `tenant_id BIGINT NOT NULL FK→tenants ON DELETE CASCADE`.
- `BelongsToTenant` trait (Fase 0) aplica global scope; cross-tenant queries proibidas exceto Super Admin via `withoutTenantScope()`.
- Auditoria: alterações em campos significativos disparam evento `Auditable` → log automático (Fase 0).
- Timezone: armazenamento UTC; exibição BRT.

## Diagrama de relações (visão geral)

```
tenants ────┬───── pacientes ──┬── paciente_tags ── tags
            │        │         ├── paciente_convenios ── convenios
            │        │         ├── anotacoes
            │        │         ├── eventos_timeline
            │        │         └── (FK→ profissional_responsavel_id)
            │
            ├───── importacoes (referencia paciente* gerados)
            ├───── mesclagens_pacientes (snapshot pre-merge)
            ├───── funil_colunas
            └───── tarefas_reatribuicao
```

---

## 1. `pacientes`

Entidade central. Persiste a pessoa que é/foi paciente de uma clínica.

| Coluna                          | Tipo                  | Constraints                                          | Notas |
|---------------------------------|-----------------------|------------------------------------------------------|-------|
| `id`                            | BIGSERIAL             | PK                                                   | |
| `tenant_id`                     | BIGINT                | NOT NULL, FK → tenants ON DELETE CASCADE             | |
| `nome`                          | VARCHAR(150)          | NOT NULL                                             | Display name original |
| `nome_normalizado`              | VARCHAR(150)          | NOT NULL, **GENERATED ALWAYS AS** `lower(unaccent(nome)) STORED` | Para busca por similaridade |
| `cpf`                           | VARCHAR(14)           | NULL                                                 | Apenas dígitos (sem máscara); validado por DV |
| `documento_estrangeiro`         | VARCHAR(30)           | NULL                                                 | Passaporte/RNE; fallback ao CPF |
| `data_nascimento`               | DATE                  | NULL                                                 | |
| `telefone_primario`             | VARCHAR(20)           | NULL                                                 | E.164 (`+55...`) |
| `telefone_primario_normalizado` | VARCHAR(20)           | NULL, GENERATED `regexp_replace(telefone_primario, '\D', '', 'g')` STORED | Apenas dígitos para busca |
| `telefones_secundarios`         | JSONB                 | NOT NULL, DEFAULT `'[]'::jsonb`                      | Array de objetos `{numero, label?}` |
| `email`                         | VARCHAR(254)          | NULL                                                 | |
| `endereco`                      | JSONB                 | NULL                                                 | `{cep, logradouro, numero, complemento, bairro, cidade, uf}` |
| `status`                        | VARCHAR(20)           | NOT NULL, DEFAULT `'lead'`, CHECK IN (`lead, ativo, inativo, bloqueado`) | Máquina de estados explícita |
| `origem`                        | VARCHAR(20)           | NOT NULL, DEFAULT `'outro'`, CHECK IN (`site, indicacao, whatsapp, instagram, telefone, presencial, outro`) | |
| `origem_detalhe`                | VARCHAR(255)          | NULL                                                 | Texto livre complementar à origem |
| `origem_origem`                 | VARCHAR(10)           | NOT NULL, DEFAULT `'manual'`, CHECK IN (`manual, canal`) | Quem preencheu a origem |
| `profissional_responsavel_id`   | BIGINT                | NULL, FK → professionals ON DELETE SET NULL          | Cardinalidade 0..1 |
| `convenio_principal_id`         | BIGINT                | NULL, FK → paciente_convenios.id                     | Aponta para o registro pivot marcado `papel='principal'` (denormalizado para perf) |
| `funil_coluna_atual_id`         | BIGINT                | NULL, FK → funil_colunas ON DELETE SET NULL          | Card do Kanban |
| `funil_posicao`                 | DECIMAL(20,10)        | NULL                                                 | Fractional indexing |
| `anonimizado_em`                | TIMESTAMPTZ           | NULL                                                 | Stub LGPD; queries filtram quando NOT NULL |
| `merged_into_paciente_id`       | BIGINT                | NULL, FK → pacientes.id ON DELETE SET NULL           | Quando absorvido por outro |
| `merged_at`                     | TIMESTAMPTZ           | NULL                                                 | |
| `created_at`                    | TIMESTAMPTZ           | NOT NULL                                             | |
| `updated_at`                    | TIMESTAMPTZ           | NOT NULL                                             | |
| `deleted_at`                    | TIMESTAMPTZ           | NULL                                                 | Soft delete (raramente usado — anonimização é o caminho preferido) |

**Constraints/Índices**:

- `UNIQUE (cpf, tenant_id) WHERE cpf IS NOT NULL` — CPF único por tenant; NULL permitido (parametrização do PG aceita múltiplos NULL).
- `INDEX (tenant_id, status)` — listagem por status.
- `INDEX (tenant_id, origem)` — relatório.
- `INDEX (tenant_id, profissional_responsavel_id)` — listagem por médico.
- `INDEX (tenant_id, funil_coluna_atual_id, funil_posicao)` — Kanban ordering.
- `INDEX (tenant_id, anonimizado_em)` — exclusão rápida de anonimizados.
- `INDEX (merged_into_paciente_id)` — lookup de merges.
- **GIN INDEX** `(tenant_id, nome_normalizado gin_trgm_ops)` — busca por similaridade.
- **GIN INDEX** `(tenant_id, telefone_primario_normalizado gin_trgm_ops)` — busca por telefone.

---

## 2. `convenios`

Catálogo CRUD por tenant.

| Coluna       | Tipo         | Constraints                                          | Notas |
|--------------|--------------|------------------------------------------------------|-------|
| `id`         | BIGSERIAL    | PK                                                   | |
| `tenant_id`  | BIGINT       | NOT NULL, FK → tenants ON DELETE CASCADE             | |
| `nome`       | VARCHAR(150) | NOT NULL                                             | Ex.: "Unimed BH" |
| `codigo_ans` | VARCHAR(10)  | NULL                                                 | Registro ANS |
| `is_active`  | BOOLEAN      | NOT NULL, DEFAULT `true`                             | Toggle no painel |
| `created_at` | TIMESTAMPTZ  | NOT NULL                                             | |
| `updated_at` | TIMESTAMPTZ  | NOT NULL                                             | |

**Índices**: `UNIQUE (tenant_id, nome)`, `INDEX (tenant_id, is_active)`.

---

## 3. `paciente_convenios`

Pivot entre paciente e convênio. Suporta até 2 convênios por paciente (principal + secundário).

| Coluna              | Tipo         | Constraints                                          | Notas |
|---------------------|--------------|------------------------------------------------------|-------|
| `id`                | BIGSERIAL    | PK                                                   | |
| `tenant_id`         | BIGINT       | NOT NULL, FK → tenants ON DELETE CASCADE             | Redundante mas necessário para global scope |
| `paciente_id`       | BIGINT       | NOT NULL, FK → pacientes ON DELETE CASCADE           | |
| `convenio_id`       | BIGINT       | NOT NULL, FK → convenios ON DELETE RESTRICT          | Não permite deletar convênio em uso |
| `numero_carteirinha`| VARCHAR(30)  | NULL                                                 | Sem validação por convênio no MVP |
| `papel`             | VARCHAR(20)  | NOT NULL, CHECK IN (`principal, secundario`)         | |
| `created_at`        | TIMESTAMPTZ  | NOT NULL                                             | |
| `updated_at`        | TIMESTAMPTZ  | NOT NULL                                             | |

**Constraints**:
- `UNIQUE (paciente_id, papel)` — só um principal, só um secundário por paciente (corolário: máximo 2).
- `INDEX (tenant_id, paciente_id)`.
- `INDEX (convenio_id)` — relatório "pacientes por convênio".

---

## 4. `tags`

Catálogo de tags por tenant.

| Coluna             | Tipo         | Constraints                                          | Notas |
|--------------------|--------------|------------------------------------------------------|-------|
| `id`               | BIGSERIAL    | PK                                                   | |
| `tenant_id`        | BIGINT       | NOT NULL, FK → tenants ON DELETE CASCADE             | |
| `nome`             | VARCHAR(50)  | NOT NULL                                             | Display: o primeiro nome registrado (mantém capitalização e acento) |
| `nome_normalizado` | VARCHAR(50)  | NOT NULL                                             | `lower(unaccent(nome))`; chave de unicidade |
| `tipo`             | VARCHAR(10)  | NOT NULL, DEFAULT `'livre'`, CHECK IN (`livre, sistemica`) | `sys:` prefix = sistêmica |
| `cor`              | VARCHAR(7)   | NULL                                                 | Hex `#FFAA00`; opcional para UI |
| `descricao`        | VARCHAR(255) | NULL                                                 | Help text opcional |
| `created_at`       | TIMESTAMPTZ  | NOT NULL                                             | |
| `updated_at`       | TIMESTAMPTZ  | NOT NULL                                             | |

**Constraints**:
- `UNIQUE (tenant_id, nome_normalizado)` — diferenciação só case+accent não cria nova tag.
- `INDEX (tenant_id, tipo)` — listar só sistêmicas / só livres.
- **Validação aplicação**: prefixo `sys:` em `nome_normalizado` exige `tipo='sistemica'` e bloqueia criação por user comum (verificado no Service).

---

## 5. `paciente_tags`

Pivot N:N entre pacientes e tags.

| Coluna       | Tipo         | Constraints                                          | Notas |
|--------------|--------------|------------------------------------------------------|-------|
| `id`         | BIGSERIAL    | PK                                                   | |
| `tenant_id`  | BIGINT       | NOT NULL, FK → tenants ON DELETE CASCADE             | |
| `paciente_id`| BIGINT       | NOT NULL, FK → pacientes ON DELETE CASCADE           | |
| `tag_id`     | BIGINT       | NOT NULL, FK → tags ON DELETE CASCADE                | |
| `aplicada_por_user_id` | BIGINT | NULL, FK → users ON DELETE SET NULL             | Quem aplicou (NULL = sistema) |
| `aplicada_at`| TIMESTAMPTZ  | NOT NULL                                             | |

**Constraints**:
- `UNIQUE (paciente_id, tag_id)` — não duplica.
- `INDEX (tenant_id, tag_id)` — listar pacientes por tag.
- `INDEX (tenant_id, paciente_id)`.

---

## 6. `anotacoes`

Anotações tipadas, **imutáveis**.

| Coluna       | Tipo         | Constraints                                          | Notas |
|--------------|--------------|------------------------------------------------------|-------|
| `id`         | BIGSERIAL    | PK                                                   | |
| `tenant_id`  | BIGINT       | NOT NULL, FK → tenants ON DELETE CASCADE             | |
| `paciente_id`| BIGINT       | NOT NULL, FK → pacientes ON DELETE CASCADE           | |
| `tipo`       | VARCHAR(20)  | NOT NULL, CHECK IN (`geral, clinica, comportamental, financeira`) | |
| `texto`      | TEXT         | NOT NULL                                             | Texto livre; UX desencoraja PII de terceiros |
| `autor_id`   | BIGINT       | NOT NULL, FK → users ON DELETE RESTRICT              | Não permite excluir user com anotações |
| `retratacao_de_anotacao_id` | BIGINT | NULL, FK → anotacoes ON DELETE RESTRICT      | NULL = anotação original; preenchido = retratação |
| `created_at` | TIMESTAMPTZ  | NOT NULL                                             | **SEM** `updated_at` — imutável |

**Constraints/Imutabilidade**:
- Trigger PG `anotacoes_immutable` rejeita UPDATE (igual ao padrão de `audit_logs` da Fase 0).
- Model boot lança `AnotacaoImutavelException` em `updating`/`deleting`.
- Retratação = NOVA anotação com `retratacao_de_anotacao_id` preenchido + tipo herdado da original.

**Índices**: `INDEX (tenant_id, paciente_id, created_at DESC)` (timeline), `INDEX (tipo)`.

---

## 7. `eventos_timeline`

Tabela canônica da timeline do paciente. Esqueleto aberto para fases futuras injetarem.

| Coluna              | Tipo         | Constraints                                          | Notas |
|---------------------|--------------|------------------------------------------------------|-------|
| `id`                | BIGSERIAL    | PK                                                   | |
| `tenant_id`         | BIGINT       | NOT NULL, FK → tenants ON DELETE CASCADE             | |
| `paciente_id`       | BIGINT       | NOT NULL, FK → pacientes ON DELETE CASCADE           | |
| `tipo`              | VARCHAR(60)  | NOT NULL                                             | Snake_case dot-notation (`paciente.criado`, `tag.aplicada`, `funil.movimentacao`, `anotacao.criada`, `mesclagem.executada`, `mensagem.recebida` futuro, etc.) |
| `autor_id`          | BIGINT       | NULL, FK → users ON DELETE SET NULL                  | NULL = sistema/job/webhook |
| `actor_type`        | VARCHAR(20)  | NOT NULL, DEFAULT `'user'`, CHECK IN (`user, system, webhook, ia`) | `ia` reservado para Fase 4 |
| `payload`           | JSONB        | NOT NULL, DEFAULT `'{}'::jsonb`                      | Payload específico do tipo de evento |
| `referencia_tipo`   | VARCHAR(150) | NULL                                                 | FQCN do recurso relacionado (`App\Models\Anotacao` etc.) |
| `referencia_id`     | BIGINT       | NULL                                                 | ID do recurso relacionado |
| `created_at`        | TIMESTAMPTZ  | NOT NULL                                             | **SEM** updated_at — imutável |

**Constraints/Imutabilidade**: trigger igual a `anotacoes` e `audit_logs`.

**Índices**:
- `INDEX (tenant_id, paciente_id, created_at DESC)` — principal para a UI da timeline.
- `INDEX (tenant_id, tipo, created_at DESC)` — filtros por tipo.
- `BRIN (created_at)` — arquivamento futuro.

---

## 8. `importacoes`

Estado da importação assíncrona.

| Coluna               | Tipo         | Constraints                                          | Notas |
|----------------------|--------------|------------------------------------------------------|-------|
| `id`                 | BIGSERIAL    | PK                                                   | |
| `tenant_id`          | BIGINT       | NOT NULL, FK → tenants ON DELETE CASCADE             | |
| `executor_id`        | BIGINT       | NOT NULL, FK → users ON DELETE RESTRICT              | |
| `arquivo_path`       | VARCHAR(255) | NOT NULL                                             | `storage/app/imports/{tenant}/{ulid}.csv|xlsx` |
| `arquivo_nome_original` | VARCHAR(255) | NOT NULL                                          | Para mostrar ao usuário |
| `arquivo_hash`       | CHAR(64)     | NOT NULL                                             | SHA-256 (idempotência + validação retry) |
| `arquivo_tamanho_bytes` | BIGINT    | NOT NULL                                             | |
| `total_linhas`       | INTEGER      | NULL                                                 | Detectado no primeiro parse |
| `status`             | VARCHAR(20)  | NOT NULL, DEFAULT `'pending'`, CHECK IN (`pending, processing, completed, partial_failure, failed, retrying`) | |
| `status_inicial_pacientes` | VARCHAR(10) | NOT NULL, CHECK IN (`lead, ativo`)             | Escolha do usuário |
| `checkpoint`         | JSONB        | NOT NULL, DEFAULT `'{}'::jsonb`                      | `{linhas_processadas, ultima_linha_validada, contadores: {importadas, atualizadas, duplicatas, erros}}` |
| `relatorio`          | JSONB        | NULL                                                 | Relatório final após `completed` — array de `{linha, status, motivo?}` |
| `started_at`         | TIMESTAMPTZ  | NULL                                                 | |
| `finished_at`        | TIMESTAMPTZ  | NULL                                                 | |
| `created_at`         | TIMESTAMPTZ  | NOT NULL                                             | |
| `updated_at`         | TIMESTAMPTZ  | NOT NULL                                             | |

**Índices**: `INDEX (tenant_id, status, created_at DESC)`, `INDEX (executor_id)`.

---

## 9. `mesclagens_pacientes`

Rastro reversível das operações de merge.

| Coluna                 | Tipo         | Constraints                                          | Notas |
|------------------------|--------------|------------------------------------------------------|-------|
| `id`                   | BIGSERIAL    | PK                                                   | |
| `tenant_id`            | BIGINT       | NOT NULL, FK → tenants ON DELETE CASCADE             | |
| `paciente_alvo_id`     | BIGINT       | NOT NULL, FK → pacientes ON DELETE CASCADE           | Paciente que absorveu |
| `pacientes_origem_ids` | JSONB        | NOT NULL                                             | Array de IDs absorvidos |
| `executor_id`          | BIGINT       | NOT NULL, FK → users ON DELETE RESTRICT              | |
| `snapshot_pre_merge`   | JSONB        | NOT NULL                                             | Estado completo: paciente + tags + convenios + anotacoes + timeline (limitado às últimas 500 entradas) |
| `resolucoes`           | JSONB        | NOT NULL                                             | `{campo: 'alvo'|'origem_N'|'manual', valor_final}` |
| `executada_em`         | TIMESTAMPTZ  | NOT NULL                                             | |
| `reversivel_ate`       | TIMESTAMPTZ  | NOT NULL                                             | `executada_em + 30 days` |
| `revertida_em`         | TIMESTAMPTZ  | NULL                                                 | NULL = ainda reversível |
| `revertida_por_user_id`| BIGINT       | NULL, FK → users ON DELETE SET NULL                  | |
| `created_at`           | TIMESTAMPTZ  | NOT NULL                                             | |
| `updated_at`           | TIMESTAMPTZ  | NOT NULL                                             | |

**Índices**: `INDEX (tenant_id, paciente_alvo_id)`, `INDEX (tenant_id, reversivel_ate, revertida_em)` (para purge mensal).

**Retenção**: job mensal purga `snapshot_pre_merge` (`SET snapshot_pre_merge = '{}'::jsonb`) após `reversivel_ate + 30 days` para reduzir tamanho JSONB; restante do registro fica para auditoria.

---

## 10. `funil_colunas`

Configuração de Kanban por tenant.

| Coluna       | Tipo         | Constraints                                          | Notas |
|--------------|--------------|------------------------------------------------------|-------|
| `id`         | BIGSERIAL    | PK                                                   | |
| `tenant_id`  | BIGINT       | NOT NULL, FK → tenants ON DELETE CASCADE             | |
| `nome`       | VARCHAR(50)  | NOT NULL                                             | |
| `slug`       | VARCHAR(50)  | NOT NULL                                             | Auto-gerado: `novo`, `qualificado`, `agendado`, `compareceu`, `perdido` |
| `posicao`    | INTEGER      | NOT NULL                                             | Ordem das colunas |
| `cor`        | VARCHAR(7)   | NULL                                                 | Hex |
| `is_terminal`| BOOLEAN      | NOT NULL, DEFAULT `false`                            | Última coluna (default: "Perdido" e "Compareceu") |
| `motivo_obrigatorio` | BOOLEAN | NOT NULL, DEFAULT `false`                           | Quando mover para esta coluna, exigir motivo |
| `is_system`  | BOOLEAN      | NOT NULL, DEFAULT `false`                            | Coluna inicial criada pelo template; usuário não pode deletar mas pode renomear |
| `created_at` | TIMESTAMPTZ  | NOT NULL                                             | |
| `updated_at` | TIMESTAMPTZ  | NOT NULL                                             | |

**Constraints**:
- `UNIQUE (tenant_id, slug)`.
- `UNIQUE (tenant_id, posicao)` — sem colisão de posições; reordenamento via swap.
- `INDEX (tenant_id, posicao)`.

**Seed lazy**: `FunilTemplateService::ensureTenantHasColumns(Tenant $t)` cria as 5 colunas padrão no primeiro acesso:

| slug         | nome           | is_terminal | motivo_obrigatorio | is_system |
|--------------|----------------|-------------|--------------------|-----------|
| `novo`       | Novo           | false       | false              | true      |
| `qualificado`| Qualificado    | false       | false              | true      |
| `agendado`   | Agendado       | false       | false              | true      |
| `compareceu` | Compareceu     | true        | false              | true      |
| `perdido`    | Perdido        | true        | true               | true      |

---

## 11. `tarefas_reatribuicao`

Backlog de pacientes órfãos por desativação de profissional. UI da fila vem na Fase 10.

| Coluna                    | Tipo         | Constraints                                          | Notas |
|---------------------------|--------------|------------------------------------------------------|-------|
| `id`                      | BIGSERIAL    | PK                                                   | |
| `tenant_id`               | BIGINT       | NOT NULL, FK → tenants ON DELETE CASCADE             | |
| `profissional_desativado_id` | BIGINT    | NOT NULL, FK → professionals ON DELETE RESTRICT      | |
| `pacientes_orfaos_ids`    | JSONB        | NOT NULL                                             | Array de IDs |
| `total_pacientes`         | INTEGER      | NOT NULL                                             | Count(pacientes_orfaos_ids) — denormalizado para listagem rápida |
| `criada_em`               | TIMESTAMPTZ  | NOT NULL                                             | |
| `concluida_em`            | TIMESTAMPTZ  | NULL                                                 | NULL = pendente |
| `concluida_por_user_id`   | BIGINT       | NULL, FK → users ON DELETE SET NULL                  | |
| `created_at`              | TIMESTAMPTZ  | NOT NULL                                             | |
| `updated_at`              | TIMESTAMPTZ  | NOT NULL                                             | |

**Índices**: `INDEX (tenant_id, concluida_em)` — UI lista pendentes (`concluida_em IS NULL`).

---

## 12. Extensão de `professionals` (Fase 0)

A migration de profissionais (T030 da Fase 0) já criou o esqueleto. Esta fase **não cria migration nova** sobre ela; em vez disso, adiciona observer:

```text
app/Models/Professional.php
  + boot(): observer 'updated' que detecta is_active true→false e dispara ProfessionalDeactivated event
  + ProfessionalDeactivatedListener (em app/Listeners/) escuta o evento e:
       1. Cria registro em tarefas_reatribuicao com lista de pacientes vinculados
       2. Dispara ReassignOrphansJob (TenantAwareJob)
       3. Audit: profissional.desativado em audit_logs
```

Nenhuma alteração de schema em `professionals` nesta fase.

---

## Máquina de estados de Paciente

```text
[INICIAL]
   │
   ├── (criação: lead OU ativo, escolha do usuário)
   ↓
 lead ─────────────────────→ ativo
   │                          │↑
   │                          ↓│  (qualquer usuário com paciente.update)
   │                        inativo
   │
   │ (qualquer estado → bloqueado: requer Admin Clínica)
   ↓
 bloqueado ──── (Admin Clínica) ────→ ativo
```

Transições permitidas:
- `lead → ativo`: qualquer ability `paciente.update`.
- `ativo ↔ inativo`: qualquer ability `paciente.update`.
- `* → bloqueado`: **apenas Admin Clínica**.
- `bloqueado → ativo`: **apenas Admin Clínica**.
- Estados sem retorno: nenhum (`inativo` pode voltar a `ativo` se reativado).

**Implicações de "bloqueado"** (gates a serem respeitados pelas fases futuras):
- Fase 3 (inbox): mensagens automáticas não enviadas; mensagens manuais síncronas permitidas.
- Fase 5 (agenda): tentativa de criar agendamento retorna 422 `{error: 'paciente_bloqueado'}`.
- Fase 7 (campanhas): paciente excluído automaticamente da segmentação.

---

## Eventos de Domínio Emitidos (resumo)

Cobertura completa na spec § 6. Implementação: cada evento implementa `App\Events\Contracts\Auditable` (interface da Fase 0); listener wildcard grava em `audit_logs` e cria entrada em `eventos_timeline` automaticamente.

| Evento Class                     | Tipo `eventos_timeline.tipo` | `audit_logs.action`              |
|----------------------------------|------------------------------|----------------------------------|
| `PacienteCriado`                 | `paciente.criado`            | `paciente.criado`                |
| `PacienteAtualizado`             | `paciente.atualizado`        | `paciente.atualizado`            |
| `PacienteStatusAlterado`         | `paciente.status_alterado`   | `paciente.status_alterado`       |
| `PacienteMesclado`               | `mesclagem.executada`        | `paciente.mesclado`              |
| `PacienteMesclagemRevertida`     | `mesclagem.revertida`        | `paciente.mesclagem_revertida`   |
| `PacienteAnonimizado`            | `paciente.anonimizado`       | `paciente.anonimizado`           |
| `TagAplicada`                    | `tag.aplicada`               | `paciente.tag_aplicada`          |
| `TagRemovida`                    | `tag.removida`               | `paciente.tag_removida`          |
| `LeadMovidoNoFunil`              | `funil.movimentacao`         | `lead.movido_no_funil`           |
| `AnotacaoCriada`                 | `anotacao.criada`            | `paciente.anotacao_criada`       |
| `AnotacaoRetratada`              | `anotacao.retratada`         | `paciente.anotacao_retratada`    |
| `PacientesImportados`            | (não vai pra timeline)       | `paciente.imported`              |
| `PacientesExportados`            | (não vai pra timeline)       | `paciente.exported`              |

---

## Resumo: tabelas criadas nesta fase

| # | Tabela                    | Linhas estimadas/tenant (MVP) |
|---|---------------------------|-------------------------------|
| 1 | `pacientes`               | até 50.000                    |
| 2 | `convenios`               | ~50                           |
| 3 | `paciente_convenios`      | até 100.000 (2 × pacientes)   |
| 4 | `tags`                    | ~50                           |
| 5 | `paciente_tags`           | até 200.000 (~4 tags/paciente médio) |
| 6 | `anotacoes`               | até 500.000 (~10/paciente médio) |
| 7 | `eventos_timeline`        | até 2.500.000 (~50/paciente)  |
| 8 | `importacoes`             | ~1.000 (histórico)            |
| 9 | `mesclagens_pacientes`    | ~500 (eventos raros)          |
| 10| `funil_colunas`           | 5-15 (template + customizações)|
| 11| `tarefas_reatribuicao`    | ~50 (uma por desativação)     |

**Total estimado por tenant em MVP**: ~3.4M de linhas distribuídas — bem abaixo do que PG 18 trata com folga com índices apropriados.
