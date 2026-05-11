# Feature Specification: Fase 2 — CRM Core: Cadastro e Gestão de Pacientes

**Feature Branch**: `002-crm-pacientes`
**Created**: 2026-05-10
**Status**: Clarified (pronto para `/speckit.plan`)
**Input**: Implementar o módulo de CRM (cadastro, timeline, importação, funil de leads e segmentação) sobre a fundação multi-tenant entregue na Fase 0.

---

## 1. Visão Geral

Esta fase entrega o módulo central de gestão de pacientes do Paciente360: cadastrar, consultar e organizar pacientes dentro de cada clínica. É a primeira fase a tratar **dados pessoais sensíveis em volume** — todas as decisões aqui ecoam na conformidade LGPD e na arquitetura de privacidade.

A fase é deliberadamente cirúrgica: **só CRM**. Não há mensageria, IA, agenda, consultas, receituários ou campanhas — esses módulos virão em fases posteriores e **consumirão** os eventos de domínio que esta fase publica. O objetivo é deixar uma base de pacientes utilizável, auditável e segmentável antes de plugar canais de comunicação.

A linha do tempo é entregue como **esqueleto**: registra eventos próprios do CRM (criação, alteração de dados, mudança de status, tags, anotações, mesclagem) e fica aberta para que fases futuras (mensagens, consultas, receituários) injetem seus próprios eventos via contrato definido aqui.

---

## Clarifications

### Session 2026-05-10

Decisões aplicadas via `/speckit.clarify` resolvendo os 13 pontos originais de NEEDS_CLARIFICATION. Cada item está incorporado ao corpo do spec abaixo (FRs, ACs, Edge Cases, Key Entities). Esta seção mantém o rastro Q→A para auditoria de decisão.

- **Q1 — Unicidade**: Q: CPF único por tenant? Telefone único? Múltiplos números? → A: **CPF único por tenant** via UNIQUE composto `(cpf, tenant_id)` com NULL permitido; **telefone NÃO único** (famílias podem compartilhar); paciente tem **1 telefone primário + N secundários** em lista.
- **Q2 — Deduplicação**: Q: Mesclar auto, sugerir, criar paralelo, rejeitar? Reversível? → A: **Detectar e sugerir mesclar com confirmação**; criar paralelo se usuário explicitamente escolher; mesclagem **reversível por 30 dias**; em campos conflitantes, **prevalece o valor mais completo** (não-nulo, mais longo); registro em `MesclagemPaciente` com `reversivel_ate`.
- **Q3 — CPF**: Q: Obrigatório? DV? Estrangeiro? → A: **CPF opcional**; **valida DV** quando preenchido (sem consulta Receita Federal); campo `documento_estrangeiro` opcional (passaporte/RNE) como alternativa para menores, estrangeiros e pacientes em situação irregular.
- **Q4 — Origem do lead**: Q: Enum, livre, ou combo? Quem preenche por canal externo? → A: **Enum fixo** (`site`, `indicacao`, `whatsapp`, `instagram`, `telefone`, `presencial`, `outro`) + campo livre `origem_detalhe`. Cadastro manual: usuário escolhe enum. Canal externo (Fase 3+): **regra fixa por canal** seta automaticamente (ex.: WhatsApp → `whatsapp`, `origem_detalhe='Mensagem recebida via WhatsApp'`).
- **Q5 — Tags**: Q: Globais? Sistêmicas? Limite? Normalização? → A: **Globais por tenant** (todos os perfis veem mesma lista); **tags sistêmicas com prefixo reservado `sys:`** (ex.: `sys:em-tratamento`, `sys:inadimplente`, `sys:primeiro-contato`), não-removíveis por usuário comum; **limite soft de 10** tags/paciente com alerta UX (sem hard limit); normalização **case + accent-insensitive** (`Diabético` ≡ `diabetico`).
- **Q6 — Status do paciente**: Q: Lista exata? "Em tratamento"? Transições? Bloqueado? → A: **4 status** (`lead`, `ativo`, `inativo`, `bloqueado`); "em tratamento" vira **tag** `sys:em-tratamento` (não status — uma tag temporal). **Máquina de estados explícita**: `lead → ativo` (qualquer ability `paciente.update`); `ativo ↔ inativo` (idem); `* → bloqueado` (requer Admin Clínica); `bloqueado → ativo` (requer Admin Clínica). **Bloqueado significa AMBOS**: não recebe mensagens automáticas (campanhas, lembretes, etc., quando essas existirem nas Fases 3/5/7) **E** não pode agendar consulta (Fase 5).
- **Q7 — Funil de Leads**: Q: Colunas fixas/configuráveis? Quem move? Motivo Perdido? Funil × Status? → A: **Configurável a partir de template padrão** (Novo → Qualificado → Agendado → Compareceu → Perdido); tenant pode editar nomes/ordem/adicionar coluna; **movimentação manual** (drag-and-drop por atendente) **+ automação Fase 5** (ao agendar consulta move para Agendado, ao realizar move para Compareceu — disparada por evento de Fase 5 que consome contrato `LeadMovidoNoFunil` desta fase); **motivo obrigatório em Perdido** com lista controlada (`sem_interesse`, `sem_retorno`, `preco`, `outro` com texto livre); **funil e status são dimensões independentes** — um lead pode estar em qualquer coluna; status do paciente segue Q6.
- **Q8 — Linha do tempo**: Q: Granularidade? Tipos? Imutáveis? Visibilidade? → A: **Apenas eventos significativos** (status, tag, telefone/email primário, profissional responsável, anotação, mesclagem, importação — **não** cada campo); anotações **tipadas** em 4 valores: `geral`, `clinica`, `comportamental`, `financeira`; anotações são **imutáveis** após criadas; **retratação** é evento separado linkado à anotação original com texto explicativo; **visibilidade por perfil + tipo**:
  - `geral` → todos os perfis com `paciente.note.view`.
  - `clinica` → **apenas Médico + Admin Clínica**.
  - `comportamental` → todos.
  - `financeira` → **apenas Admin Clínica + Financeiro** (Financeiro recebe `paciente.note.view:financeira` em fase futura quando Financeiro for desbloqueado para clínica — por ora, só Admin Clínica vê).
- **Q9 — Importação em massa**: Q: Tamanho/linhas máx? Parcial vs tudo-ou-nada? Status inicial? Reimport? Worker matou? → A: **5 MB máx por arquivo / 10.000 linhas máx**; **modo parcial sempre** (processa linhas válidas, relatório lista erros por linha); usuário escolhe **status inicial por importação** (lead ou ativo, default lead); **reimportação atualiza apenas campos vazios** (preserva preenchidos), match por `CPF` com fallback para `telefone_primario` quando CPF ausente; em falha de worker, **retoma do último checkpoint** (granularidade: cada 100 linhas).
- **Q10 — Convênio**: Q: Catálogo ou livre? Carteirinha validada? Múltiplos? → A: **Catálogo CRUD por tenant** (entidade `Convênio` com nome, código ANS opcional, status ativo); paciente referencia convênio do catálogo; **até 2 convênios por paciente** (titular pode ter plano principal + secundário); **número de carteirinha sem validação por convênio** no MVP (apenas string até 30 chars).
- **Q11 — Profissional responsável**: Q: 0/1/múltiplos? Desativar profissional? → A: **0 ou 1** (paciente "da clínica" tem responsável = null); ao **desativar profissional**, todos os pacientes vinculados ficam **órfãos** (`profissional_responsavel_id` = null) **e** o sistema gera **tarefa de reatribuição** atribuída ao Admin Clínica na fila de tarefas internas (esta fase entrega apenas o registro; UI da fila de tarefas vem na Fase 10).
- **Q12 — LGPD e exportação**: Q: Ability separada? Audit completo? Direito ao esquecimento agora? → A: Ability **`paciente.export` separada**, **default apenas Admin Clínica**; **audit completo de exportação** com `executor_id, escopo (filtros aplicados), contagem, hash_arquivo (SHA-256), timestamp` — sem PII em payload; **stub mínimo de direito ao esquecimento** nesta fase: campo `anonimizado_em TIMESTAMPTZ NULL` na ficha, evento `PacienteAnonimizado` disparável via ação interna, queries de paciente automaticamente excluem registros anonimizados; **fluxo completo de portabilidade e esquecimento (US-13.x) fica para Fase LGPD dedicada**.
- **Q13 — Volume e performance**: Q: Volume MVP? Latência busca? Tipo de busca? → A: **50.000 pacientes por tenant** como dimensionamento de MVP (cobre clínica realista de 10 médicos × 5 anos de operação); **busca por nome ou telefone com p95 < 300 ms**; busca por **similaridade** (suporta erro de digitação, busca parcial, normalização de acentos) — implementação via índice trigram fica para `plan.md`, aqui é apenas comportamento esperado.

---

## 2. Contratos Herdados da Fase 0

Esta fase não duplica nem renegocia decisões da fundação. Os seguintes contratos são premissa, não escopo:

### 2.1 Multi-tenancy
- Toda entidade introduzida nesta fase pertence a exatamente um tenant.
- Identificação de tenant é resolvida por subdomínio e injetada antes de qualquer leitura/escrita.
- Escopo automático (global scope por tenant) é aplicado a todas as queries.
- Cross-tenant queries são proibidas exceto para perfil Super Admin no painel administrativo.

### 2.2 Autenticação e Sessão
- Sessão estabelecida pela infra de autenticação SPA stateful da Fase 0 (cookie HttpOnly + CSRF).
- Rate limit, brute force lock por usuário e logs estruturados com `request_id` já cobrem os endpoints novos sem retrabalho.

### 2.3 Auditoria
- Toda alteração sensível em paciente grava no log de auditoria com o schema existente: `tenant_id`, `user_id`, `actor_type`, `action`, `auditable_type`, `auditable_id`, `payload`, `ip`, `user_agent`, `request_id`, `created_at`.
- Registros de auditoria são imutáveis (trigger de banco + bloqueio no nível do modelo).
- Eventos desta fase usam a interface `Auditable` já existente — não há novo mecanismo de log.

### 2.4 Permissões (Spatie + team mode por tenant)
Esta fase introduz os seguintes novos abilities, atribuídos aos perfis conforme tabela:

| Ability               | Admin Clínica | Médico | Atendente | Recepcionista | Financeiro | Super Admin (sem dados clínicos) |
|-----------------------|:-------------:|:------:|:---------:|:-------------:|:----------:|:--------------------------------:|
| `paciente.view`       | ✅            | ✅     | ✅        | ✅            | ❌         | ❌                               |
| `paciente.create`     | ✅            | ✅     | ✅        | ✅            | ❌         | ❌                               |
| `paciente.update`     | ✅            | ✅     | ✅        | ✅            | ❌         | ❌                               |
| `paciente.delete`     | ✅            | ❌     | ❌        | ❌            | ❌         | ❌                               |
| `paciente.import`     | ✅            | ❌     | ❌        | ❌            | ❌         | ❌                               |
| `paciente.export`     | ✅            | ❌     | ❌        | ❌            | ❌         | ❌                               |
| `paciente.merge`      | ✅            | ❌     | ❌        | ❌            | ❌         | ❌                               |
| `paciente.note.write` | ✅            | ✅     | ✅        | ✅            | ❌         | ❌                               |
| `paciente.note.view:geral`         | ✅            | ✅     | ✅        | ✅            | ❌         | ❌                               |
| `paciente.note.view:clinica`       | ✅            | ✅     | ❌        | ❌            | ❌         | ❌                               |
| `paciente.note.view:comportamental`| ✅            | ✅     | ✅        | ✅            | ❌         | ❌                               |
| `paciente.note.view:financeira`    | ✅            | ❌     | ❌        | ❌            | ❌ *(reservado)* | ❌                          |

**Princípio:** Financeiro e Super Admin **não têm acesso a dados clínicos por padrão**. Super Admin enxerga métricas agregadas no painel administrativo, nunca PII de paciente.

### 2.5 Idioma e Localização
- Toda interface, mensagens de erro, e-mails e exports são em **pt-BR**.
- Formatos brasileiros para CPF (`000.000.000-00`), telefone (`(00) 00000-0000`), data (`DD/MM/AAAA`).
- Datas armazenadas em UTC; ciclos (mês de uso, retenção) calculados em `America/Sao_Paulo`.

---

## 3. User Scenarios & Testing

### User Story 1 — Cadastro Manual de Paciente (Priority: P1)

> Como Atendente ou Médico, quero cadastrar um paciente manualmente para que eu registre alguém que chegou por canal não automatizado.

**Por que P1**: é a porta de entrada do CRM. Sem cadastro manual, nenhum outro fluxo do produto faz sentido. É o MVP absoluto da fase.

**Independent Test**: Atendente preenche o formulário com nome, telefone e CPF válidos; após enviar, o paciente aparece em `/panel/pacientes` com status inicial configurável (lead ou ativo) e a criação fica registrada em log de auditoria.

#### Acceptance Scenarios (US-3.1)

- **AC-3.1.1 — Cadastro mínimo bem-sucedido**
  **Given** um usuário autenticado com ability `paciente.create` no tenant `clinica-x`
  **When** envia o formulário com nome, telefone e status inicial preenchidos
  **Then** o sistema cria o paciente com `tenant_id` igual ao tenant atual, registra evento `PacienteCriado` no log de auditoria e redireciona para a ficha do paciente.

- **AC-3.1.2 — Validação de CPF (quando preenchido) e CPF opcional**
  **Given** o cadastro inclui o campo CPF
  **When** o CPF informado tem dígito verificador inválido
  **Then** o sistema rejeita o envio com mensagem traduzida em pt-BR identificando o campo CPF, sem persistir nada.
  **E** quando o CPF está ausente: cadastro é aceito; campo `documento_estrangeiro` (passaporte/RNE) é oferecido como identificador alternativo opcional.

- **AC-3.1.3 — Deduplicação detectada na criação (apenas CPF)**
  **Given** já existe paciente no tenant atual com o mesmo CPF
  **When** o usuário tenta criar novo cadastro com mesmo CPF
  **Then** o sistema apresenta o cadastro existente e oferece três ações: (1) **mesclar** com o existente, (2) **criar paralelo** mesmo assim (com confirmação explícita do usuário) ou (3) **cancelar** e abrir o cadastro existente. Telefone duplicado **não** dispara deduplicação (famílias podem dividir telefone).

- **AC-3.1.4 — Isolamento entre tenants**
  **Given** dois tenants `clinica-a` e `clinica-b`, ambos com paciente de CPF `123.456.789-09`
  **When** um usuário do `clinica-a` busca ou consulta esse CPF
  **Then** vê apenas o paciente de `clinica-a`; o paciente de `clinica-b` é invisível e indetectável (não revela existência cross-tenant).

- **AC-3.1.5 — Status inicial configurável**
  **Given** o formulário oferece campo de status inicial entre `lead` e `ativo`
  **When** o usuário escolhe um dos dois
  **Then** o paciente é criado com aquele status e a escolha aparece na timeline como `PacienteStatusAlterado` com `status_anterior=null`. Status `inativo` e `bloqueado` não são selecionáveis na criação (transições futuras).

- **AC-3.1.6 — Profissional responsável (0 ou 1)**
  **Given** o formulário inclui campo "profissional responsável"
  **When** o usuário seleciona um profissional ativo do tenant
  **Then** o paciente fica vinculado a esse profissional (cardinalidade exata: zero ou um). Deixar o campo vazio é permitido — paciente "da clínica". Quando o profissional for futuramente desativado, o vínculo vira null e uma tarefa de reatribuição é gerada automaticamente para o Admin Clínica.

- **AC-3.1.7 — Origem do lead**
  **Given** o formulário inclui campo "origem" como dropdown de enum fixo (`site`, `indicacao`, `whatsapp`, `instagram`, `telefone`, `presencial`, `outro`) mais campo livre `origem_detalhe`
  **When** o usuário escolhe um valor do enum e opcionalmente preenche detalhe
  **Then** o valor persiste com ambos os campos. Quando o paciente é criado por canal externo (Fase 3+), a `origem` é setada automaticamente pela regra do canal e marca `origem_origem='canal'`; em cadastro manual, `origem_origem='manual'`.

- **AC-3.1.8 — Auditoria do cadastro**
  **Given** qualquer cadastro bem-sucedido
  **When** se consulta o log de auditoria filtrado por `action='paciente.criado'`
  **Then** existe uma entrada com `auditable_type=Paciente`, `user_id` do criador, payload contendo snapshot do paciente **sem dados sensíveis em texto livre** (ex.: CPF mascarado a últimos 3 dígitos).

**Dependências**: Fase 0 (auth, tenant scope, audit, papéis). Profissionais já existem como esqueleto desde Fase 0.

**Riscos**:
- Definição de unicidade (CPF, telefone) impacta diretamente a UX de deduplicação. Decisão errada gera retrabalho na importação (US-3.3) e na integração com canais (Fase 3).
- Validação de CPF: política precisa cobrir estrangeiros, menores e pacientes em situação irregular sem virar bloqueador.

**Decisões aplicadas via Clarifications**: Q1 (unicidade), Q2 (dedup), Q3 (CPF), Q4 (origem), Q6 (status), Q11 (profissional). Todas resolvidas — ver seção *Clarifications*.

---

### User Story 2 — Linha do Tempo Unificada (esqueleto) (Priority: P1)

> Como Médico ou Atendente, quero ver o histórico de interações de um paciente em uma única visão para que eu tenha contexto antes de atender.

**Por que P1**: a timeline é o que torna o "CRM" útil em vez de um cadastro morto. Sem ela, médico/atendente abre cadastro e não enxerga contexto.

**Independent Test**: Após cadastrar paciente, alterar telefone e adicionar uma anotação, abrir a timeline do paciente exibe 3 eventos em ordem cronológica reversa, cada um com autor, data e descrição traduzida em pt-BR.

#### Acceptance Scenarios (US-3.2)

- **AC-3.2.1 — Eventos próprios do CRM registrados**
  **Given** um paciente foi criado, depois teve telefone alterado, depois recebeu uma tag, depois recebeu uma anotação
  **When** o usuário abre a timeline desse paciente
  **Then** vê 4 eventos em ordem cronológica reversa (mais recente primeiro), cada um com tipo, autor, timestamp e descrição.

- **AC-3.2.2 — Granularidade da alteração de dados (apenas significativos)**
  **Given** um campo do paciente é alterado
  **When** se inspeciona a timeline
  **Then** o evento `PacienteAtualizado` aparece **apenas** quando o campo alterado pertence ao conjunto significativo: `status`, `tag`, `telefone_primario`, `email`, `profissional_responsavel_id`, `convenio_principal`. Alterações em outros campos (ex.: data de nascimento, endereço, observações curtas) são persistidas mas **não** geram entrada na timeline (evita poluição). A política é uniforme para todos os pacientes do tenant.

- **AC-3.2.3 — Filtros por tipo de evento**
  **Given** uma timeline com múltiplos tipos de evento
  **When** o usuário filtra por "anotações" (ou outro tipo)
  **Then** apenas eventos do tipo selecionado são exibidos; contador no topo reflete o total filtrado.

- **AC-3.2.4 — Anotações com tipos e visibilidade por perfil**
  **Given** os 4 tipos de anotação (`geral`, `clinica`, `comportamental`, `financeira`)
  **When** uma anotação é criada com tipo `clinica`
  **Then** ela é visível apenas para Médico e Admin Clínica; Atendente e Recepcionista não enxergam (a entrada na timeline é filtrada antes do render). Anotações `geral` e `comportamental` são visíveis a todos os perfis com `paciente.note.view:*`. Anotações `financeira` ficam reservadas para Admin Clínica (e Financeiro quando essa visibilidade for habilitada em fase futura).

- **AC-3.2.5 — Anotações são imutáveis; retratação como evento separado**
  **Given** uma anotação foi salva
  **When** o autor (ou outro usuário autorizado) tenta editar ou excluir
  **Then** o sistema impede a alteração e oferece **adicionar retratação** linkada à anotação original. A retratação é um novo evento `AnotacaoRetratada` na timeline com `anotacao_id_original`, autor, texto explicativo e timestamp. A anotação original continua visível com indicador "retratada por [data]".

- **AC-3.2.6 — Performance: timeline carrega em < 1s para até 1000 eventos**
  **Given** um paciente com até 1000 eventos na timeline
  **When** o usuário abre a tela
  **Then** o primeiro lote de eventos (ex.: 50 mais recentes) renderiza em menos de 1 segundo (p95 em condições normais de produção).

- **AC-3.2.7 — Anotações privadas não são reveladas ao paciente**
  **Given** anotações marcadas como internas
  **When** o paciente eventualmente recebe sua portabilidade de dados (Fase 8 — LGPD)
  **Then** as anotações marcadas como internas **não** integram o pacote de portabilidade.
  *(Esta fase apenas garante a marcação; o fluxo de portabilidade fica para fase LGPD.)*

- **AC-3.2.8 — Esqueleto pronto para integração futura**
  **Given** a infra de timeline desta fase
  **When** uma fase futura (mensagens, consultas, receituários) publica um evento com o shape contratual
  **Then** o evento aparece na timeline sem alteração de código do CRM — somente registro do tipo de evento e de seu renderer.

**Dependências**: US-3.1 (paciente existe); infra de eventos da Fase 0.

**Riscos**:
- Granularidade errada (cada campo gera evento) inunda a timeline e quebra a meta de < 1s.
- Visibilidade de anotações por perfil é decisão sensível (LGPD + clínica) — não pode vazar entre perfis.

**Decisões aplicadas via Clarifications**: Q8 (timeline e anotações). Resolvida — ver seção *Clarifications*.

---

### User Story 3 — Importação em Massa (Priority: P2)

> Como Admin Clínica em onboarding, quero importar a base atual de pacientes via planilha para que eu não precise recadastrar manualmente.

**Por que P2**: necessária para onboarding de clínicas com base existente, mas o produto é utilizável sem ela para clínicas novas. Deve estar pronta quando a primeira clínica em migração for onboardada.

**Independent Test**: Admin Clínica baixa template, preenche 50 linhas (10 válidas, 40 com erros variados), faz upload; em poucos minutos recebe relatório identificando exatamente quais linhas foram importadas, quais foram ignoradas como duplicatas e quais falharam com motivo.

#### Acceptance Scenarios (US-3.3)

- **AC-3.3.1 — Template baixável**
  **Given** o usuário com ability `paciente.import`
  **When** clica em "Baixar template"
  **Then** recebe arquivo no formato suportado (CSV/Excel) com cabeçalhos em pt-BR, colunas obrigatórias e opcionais identificadas, e exemplo de linha preenchida.

- **AC-3.3.2 — Upload e validação prévia (≤ 5 MB e ≤ 10.000 linhas)**
  **Given** um arquivo com até 5 MB e até 10.000 linhas
  **When** é enviado
  **Then** o sistema responde imediatamente confirmando recebimento e enfileirando para processamento assíncrono; retorna um identificador da importação para acompanhamento.

- **AC-3.3.3 — Arquivo acima do limite é rejeitado**
  **Given** um arquivo acima de 5 MB **ou** com mais de 10.000 linhas
  **When** é enviado
  **Then** o sistema rejeita com mensagem clara identificando qual limite foi excedido (tamanho ou contagem).

- **AC-3.3.4 — Processamento parcial sempre**
  **Given** um arquivo com 100 linhas, sendo 30 inválidas (CPF errado, telefone vazio etc.)
  **When** o processamento termina
  **Then** o relatório indica: **70 importadas**, **30 com erro** listadas por número da linha + motivo. **Modo é sempre parcial** — uma linha com erro nunca aborta as outras. Tudo-ou-nada não é oferecido (FR-017 endossa).

- **AC-3.3.5 — Deduplicação na importação (CPF; fallback telefone)**
  **Given** uma linha colide com paciente existente por CPF (ou por `telefone_primario` quando CPF ausente)
  **When** o sistema processa
  **Then** o paciente existente recebe **atualização apenas dos campos vazios** (campos preenchidos são preservados); o relatório distingue "atualizada por reimport" de "duplicata ignorada (sem dados novos)" e de "linha com erro".

- **AC-3.3.6 — Status inicial da importação**
  **Given** o usuário inicia importação
  **When** escolhe status inicial dos importados entre `lead` (default) ou `ativo` — uma escolha por importação inteira, não por linha
  **Then** todos os pacientes importados recebem aquele status; mudanças posteriores seguem a máquina de estados (`lead → ativo` / `ativo ↔ inativo` / `* → bloqueado` por Admin).

- **AC-3.3.7 — Reimportação atualiza apenas campos vazios**
  **Given** um arquivo foi importado anteriormente e o mesmo CPF aparece em nova importação com dados adicionais
  **When** o sistema processa
  **Then** os campos que estavam vazios no paciente recebem os valores da nova linha; campos preenchidos são preservados; o registro aparece no relatório como "atualizada por reimport" e gera evento `PacienteAtualizado` quando algum campo significativo foi alterado.

- **AC-3.3.8 — Auditoria da importação**
  **Given** uma importação completou
  **When** se inspeciona o log de auditoria
  **Then** existe entrada `paciente.imported` com `user_id` do executor, payload contendo contagens (importados/ignorados/com erro) e id da importação, **sem PII das linhas**.

- **AC-3.3.9 — Permissão restritiva**
  **Given** um usuário com perfil Médico, Atendente ou Recepcionista
  **When** tenta acessar a função de importação
  **Then** recebe negativa de autorização (a ability `paciente.import` é exclusiva de Admin Clínica nesta fase).

- **AC-3.3.10 — Importação não bloqueia o sistema**
  **Given** uma importação grande em andamento
  **When** outros usuários do mesmo tenant operam o sistema (consultar pacientes, cadastrar novos manualmente)
  **Then** as operações concorrentes não são bloqueadas; a importação ocorre em segundo plano sem degradar p95 de outras telas.

**Dependências**: US-3.1 (definição de paciente e validações), infra de jobs/fila da Fase 0.

**Riscos**:
- Sem limite explícito, um arquivo grande pode esgotar recursos.
- Política de reimportação ambígua é fonte clássica de bug: usuário reenvia "para atualizar" e nada muda (ou tudo é duplicado).
- Importação cria volume rápido de auditoria — relatórios e timeline podem ficar lentos em volumes não previstos.

**Decisões aplicadas via Clarifications**: Q2 (dedup), Q6 (status), Q9 (importação — todos os parâmetros). Resolvidas — ver seção *Clarifications*.

---

### User Story 4 — Funil de Leads em Kanban (Priority: P2)

> Como Admin Clínica ou Atendente, quero visualizar e mover leads em um funil Kanban para que eu acompanhe a conversão até a consulta.

**Por que P2**: importante para uso comercial do CRM, mas o cadastro e a timeline já entregam valor isoladamente. Funil acelera adoção pela equipe comercial.

**Independent Test**: Após cadastrar 3 leads, o Kanban exibe 3 cards na coluna inicial; arrastar um card para próxima coluna persiste a mudança e registra evento `LeadMovidoNoFunil` na timeline do paciente.

#### Acceptance Scenarios (US-3.4)

- **AC-3.4.1 — Colunas padrão a partir de template (configuráveis)**
  **Given** o Kanban é aberto pela primeira vez em um tenant
  **When** carrega
  **Then** apresenta o template padrão **Novo → Qualificado → Agendado → Compareceu → Perdido**. Admin Clínica pode editar nomes/ordem/adicionar coluna em tela de configuração separada. Mudanças no template afetam o tenant todo, não só pacientes futuros.

- **AC-3.4.2 — Cards exibem informação essencial**
  **Given** um lead no funil
  **When** o card é renderizado
  **Then** mostra: nome, canal de origem, data da última interação e (quando aplicável) valor estimado.

- **AC-3.4.3 — Drag-and-drop com persistência**
  **Given** um card em uma coluna
  **When** o usuário arrasta para outra coluna válida
  **Then** o servidor confirma a mudança, o card persiste a nova posição em refresh e a transição vira evento `LeadMovidoNoFunil` na timeline do paciente.

- **AC-3.4.4 — Motivo obrigatório em "Perdido" com lista controlada**
  **Given** um card é movido para a coluna "Perdido"
  **When** o usuário confirma a movimentação
  **Then** um modal exige seleção de motivo entre `sem_interesse`, `sem_retorno`, `preco`, `outro`. Quando `outro`, campo de texto livre obrigatório (mín. 10 chars). O motivo é gravado no evento `LeadMovidoNoFunil.motivo` e fica visível no card. Movimentação **não persiste** até que motivo seja preenchido.

- **AC-3.4.5 — Movimentação automática (gancho)**
  **Given** uma integração de fase futura sinaliza qualificação/agendamento (Fase 5)
  **When** o evento é recebido
  **Then** o card move automaticamente para a coluna apropriada, gerando evento idêntico ao drag-and-drop manual.
  *(Esta fase entrega o gancho; o disparo vem da Fase 5.)*

- **AC-3.4.6 — Filtros do funil**
  **Given** um Kanban com leads de múltiplos canais
  **When** o usuário aplica filtro por canal de origem, profissional ou intervalo de data
  **Then** apenas cards correspondentes aparecem; contadores por coluna refletem o filtro.

- **AC-3.4.7 — Funil e status são dimensões independentes**
  **Given** um lead no funil e o status do paciente
  **When** o lead muda de coluna (ex.: Novo → Qualificado)
  **Then** **o status do paciente não muda automaticamente**. Funil é o pipeline comercial; status é o estado de relacionamento da clínica com o paciente. Um paciente `ativo` pode estar em qualquer coluna; um lead na coluna "Compareceu" pode ter status `lead` (se a clínica não converteu para `ativo` ainda) ou `ativo`. A conversão de status é decisão manual ou regra de Fase 5.

- **AC-3.4.8 — Performance**
  **Given** um funil com até 500 cards
  **When** carrega
  **Then** o tempo até primeira renderização (p95) fica abaixo de 1,5 segundo; drag-and-drop responde em menos de 300 ms.

**Dependências**: US-3.1, US-3.5.

**Riscos**:
- Funil e status confundidos viram dor de manutenção: dois sources of truth.
- Movimentação automática (Fase 5) precisa de contrato estável agora, sob pena de retrabalho.

**Decisões aplicadas via Clarifications**: Q7 (funil — colunas configuráveis, motivo Perdido, dimensão independente do status). Resolvida — ver seção *Clarifications*.

---

### User Story 5 — Segmentação por Tags e Status (Priority: P2)

> Como Admin Clínica, quero aplicar tags e segmentar pacientes para que eu use os segmentos em campanhas e relatórios.

**Por que P2**: tags + status habilitam campanhas (Fase 7) e relatórios (Fase 10). Sem isso, segmentação vira filtro manual ad-hoc.

**Independent Test**: Aplicar tag "VIP" a 5 pacientes; filtrar lista por tag "VIP" retorna exatamente esses 5; alterar status de um paciente registra evento na timeline.

#### Acceptance Scenarios (US-3.5)

- **AC-3.5.1 — Criação livre de tags (globais por tenant)**
  **Given** um usuário autorizado
  **When** aplica uma tag inédita a um paciente
  **Then** a tag é criada no escopo do tenant (visível para todos os perfis com acesso a pacientes) e aparece para reuso em outros pacientes daquele tenant. Tags são únicas por nome **normalizado** (case + accent-insensitive): `Diabético`, `diabetico` e `DIABETICO` são a mesma tag.

- **AC-3.5.2 — Tags sistêmicas com prefixo `sys:`**
  **Given** o sistema dispara aplicação automática de tag (ex.: `sys:primeiro-contato` ao criar lead, `sys:inadimplente` ao subscriber falhar pagamento na Fase 7, `sys:em-tratamento` quando uma série de consultas estiver em andamento na Fase 5)
  **When** a tag entra em vigor
  **Then** ela é exibida com indicador visual de "sistêmica" (cor/ícone distintos) e **não pode ser removida por usuário comum** — apenas por ação automática do sistema que a aplicou. Usuários não podem criar tags com prefixo `sys:` (prefixo reservado).

- **AC-3.5.3 — Limite soft de 10 tags por paciente**
  **Given** um paciente com 10 tags aplicadas
  **When** o usuário tenta aplicar a 11ª tag
  **Then** o sistema **aplica a tag** mas exibe aviso UX "Recomendamos no máximo 10 tags por paciente para manter segmentação clara". Não há hard limit que rejeite a operação. Operação é registrada normalmente.

- **AC-3.5.4 — Múltiplas tags por paciente**
  **Given** um paciente
  **When** múltiplas tags são aplicadas
  **Then** todas persistem e aparecem na ficha; busca por qualquer uma delas retorna esse paciente.

- **AC-3.5.5 — Mudança de status registrada com máquina explícita**
  **Given** um paciente em status `lead`
  **When** o status muda para `ativo` (transição permitida a qualquer usuário com `paciente.update`)
  **Then** evento `PacienteStatusAlterado` é gravado na timeline com `status_anterior=lead`, `status_novo=ativo`, autor, motivo opcional. Transições permitidas: `lead → ativo` (qualquer), `ativo ↔ inativo` (qualquer), `* → bloqueado` (**apenas Admin Clínica**), `bloqueado → ativo` (**apenas Admin Clínica**).

- **AC-3.5.6 — Transições inválidas rejeitadas**
  **Given** um usuário comum (Médico/Atendente/Recepcionista) tenta `* → bloqueado` ou `bloqueado → *`
  **When** envia a alteração
  **Then** o sistema rejeita com mensagem traduzida explicando que apenas Admin Clínica pode mudar status de/para `bloqueado`.

- **AC-3.5.7 — Busca e filtro por tag + status**
  **Given** uma base com 10.000 pacientes
  **When** o usuário filtra por "tag=VIP AND status=ativo"
  **Then** o resultado retorna em menos de 500 ms (p95) e respeita o vínculo de tenant.

- **AC-3.5.8 — "Bloqueado" implica ambos (sem mensagens automáticas + sem agendar)**
  **Given** um paciente em status `bloqueado`
  **When** o sistema é integrado com fases futuras (Fase 3 — inbox, Fase 5 — agenda, Fase 7 — campanhas)
  **Then** **(a)** mensagens automáticas (lembretes, confirmações, campanhas) **não** são enviadas para este paciente; **(b)** tentativa de criar agendamento para este paciente retorna erro. Nesta fase entregamos apenas o **gate** (campo de status + getter/predicado `isBlocked()` no contrato público); o respeito ao gate é responsabilidade das fases futuras. Atendimento manual síncrono permanece permitido (paciente pode ligar e ser atendido).

**Dependências**: US-3.1.

**Riscos**:
- Tags virais (todo mundo cria tag para tudo) — sem limites e sem sistêmicas, a base poluiu rápido.
- Status `bloqueado` mal definido vira loophole de LGPD/anti-spam.

**Decisões aplicadas via Clarifications**: Q5 (tags), Q6 (status). Resolvidas — ver seção *Clarifications*.

---

### Edge Cases (transversais)

- **Paciente sem identificador**: cadastro com apenas nome e telefone, sem CPF e sem documento alternativo é **permitido**. Sistema marca paciente com tag `sys:sem-documento` para destacar em busca/relatório futuro (LGPD pode exigir completar dados antes de certos fluxos).
- **CPF com formato válido mas pessoa inexistente**: o sistema não consulta Receita Federal; valida apenas DV. Risco de fraude documental sai do escopo.
- **Mesmo paciente em duas clínicas independentes**: aceito por design (multi-tenant). Cada clínica vê seu próprio paciente; nenhuma cross-reference.
- **Importação rodando durante deploy**: jobs em andamento precisam ser idempotentes e retomáveis.
- **Mesclagem com dados conflitantes**: dois cadastros com mesmo CPF têm datas de nascimento diferentes — **prevalece o valor mais completo** (não-nulo, mais longo, mais recente como desempate). Tela de mesclagem mostra diff lado a lado; usuário pode override manual antes de confirmar. Reversão possível em 30 dias via registro `MesclagemPaciente`.
- **Tags com mesmo nome diferindo só por acento**: ("Diabético" vs "diabetico") são **considerados a mesma tag** via normalização **case + accent-insensitive**. Nome canônico = primeiro nome cadastrado; alias preservados para busca.
- **Anotação com PII excessivo**: usuário cola texto contendo número de cartão de crédito ou CPF de terceiros. Não há filtro automático nesta fase; **alerta UX** orienta usuário a não colar dados sensíveis sem necessidade.
- **Paciente cuja origem é "WhatsApp" mas WhatsApp ainda não existe** (Fase 3 não entregue): nesta fase, valor "WhatsApp" é selecionável mas marcado como manual; quando Fase 3 entrar, o canal externo passa a setar a origem automaticamente — campo `origem_origem` (manual/canal) controla.
- **Importação parcial interrompida** (worker matou): **retoma do último checkpoint** (granularidade: cada 100 linhas processadas). Estado salvo persiste `linhas_processadas, ultima_linha_validada, contadores_parciais`. Worker que retoma valida hash do arquivo (mesma importação) antes de continuar; se o arquivo mudou, importação é marcada como falha e usuário é orientado a refazer.

---

## 4. Requirements *(mandatory)*

### 4.1 Functional Requirements

#### Cadastro e identidade
- **FR-001**: Sistema MUST permitir cadastrar paciente com nome obrigatório, telefone obrigatório e demais campos opcionais (CPF, data de nascimento, e-mail, convênio, carteirinha, tags, origem do lead, profissional responsável).
- **FR-002**: Sistema MUST aceitar CPF como **campo opcional**; MUST validar dígito verificador quando informado (sem consulta Receita Federal); MUST oferecer campo opcional `documento_estrangeiro` (passaporte/RNE) como alternativa ao CPF.
- **FR-003**: Sistema MUST garantir **CPF único por tenant** via UNIQUE composto `(cpf, tenant_id)` aceitando NULL. Telefone NÃO é único. MUST detectar duplicata por CPF no cadastro manual e na importação.
- **FR-004**: Sistema MUST oferecer três ações ao detectar duplicata: (1) mesclar com confirmação, (2) criar paralelo com confirmação explícita, (3) abrir o existente. Mesclagem MUST ser **reversível por 30 dias** via registro `MesclagemPaciente`. Em conflito de campo durante mesclagem, MUST aplicar regra "prevalece o mais completo" (não-nulo, mais longo, mais recente como desempate) com possibilidade de override manual.
- **FR-005**: Sistema MUST associar cada paciente a **zero ou um** profissional responsável. Ao desativar profissional, todos os pacientes vinculados MUST ter `profissional_responsavel_id` setado para NULL **e** uma tarefa de reatribuição MUST ser criada automaticamente atribuída ao Admin Clínica.
- **FR-006**: Sistema MUST escopar todo paciente ao tenant atual; cross-tenant queries são proibidas (herdado da Fase 0).
- **FR-007**: Sistema MUST aceitar valores de "origem" via enum fixo (`site`, `indicacao`, `whatsapp`, `instagram`, `telefone`, `presencial`, `outro`) combinado com campo livre `origem_detalhe`. Cadastro manual: enum selecionável pelo usuário. Canais externos (Fase 3+): origem setada automaticamente por regra do canal.
- **FR-008**: Sistema MUST oferecer **CRUD de Convênios por tenant** (entidade `Convênio` com nome, código ANS opcional, status ativo); paciente referencia convênio do catálogo; suporta **até 2 convênios por paciente** (principal + secundário); número de carteirinha sem validação por convênio (apenas string até 30 chars).

#### Linha do tempo
- **FR-009**: Sistema MUST registrar na timeline do paciente apenas eventos **significativos**: criação, alteração dos campos `status`, `tag`, `telefone_primario`, `email`, `profissional_responsavel_id`, `convenio_principal`; aplicação/remoção de tag; anotação; retratação; mesclagem; reversão de mesclagem; importação; exportação; anonimização.
- **FR-010**: Sistema MUST exibir a timeline em ordem cronológica reversa, com paginação, filtros por tipo de evento, autor visível e timestamp em fuso `America/Sao_Paulo`.
- **FR-011**: Sistema MUST permitir anotações tipadas em 4 tipos: `geral`, `clinica`, `comportamental`, `financeira`; anotações são **imutáveis após criação**; **retratação** é evento separado linkado à anotação original com texto explicativo e autor.
- **FR-012**: Sistema MUST controlar visibilidade de anotação por perfil e tipo via abilities granulares `paciente.note.view:{tipo}`: `geral` e `comportamental` visíveis a todos com `paciente.note.view`; `clinica` visível apenas a Médico e Admin Clínica; `financeira` visível apenas a Admin Clínica (Financeiro reservado para fase futura).
- **FR-013**: Sistema MUST expor contrato público para fases futuras injetarem eventos na timeline (forma do payload, tipos permitidos, ordem).

#### Importação
- **FR-014**: Sistema MUST aceitar upload de arquivo CSV/Excel com cabeçalhos em pt-BR.
- **FR-015**: Sistema MUST oferecer template baixável.
- **FR-016**: Sistema MUST processar a importação assincronamente, sem bloquear o usuário.
- **FR-017**: Sistema MUST validar cada linha antes de persistir; relatório final lista importados, duplicatas ignoradas e linhas com erro (com motivo por linha).
- **FR-018**: Sistema MUST aplicar deduplicação por CPF (com fallback `telefone_primario` quando CPF ausente) na importação; **atualiza apenas campos vazios** do paciente existente, preservando campos preenchidos; relatório distingue "atualizada por reimport" de "duplicata ignorada".
- **FR-019**: Sistema MUST limitar arquivo de importação a **5 MB** e **10.000 linhas**; arquivos acima do limite são rejeitados com mensagem clara.
- **FR-020**: Sistema MUST suportar **reimportação** do mesmo arquivo (ou variação); match por CPF (fallback telefone) atualiza campos vazios; processamento parcial sempre (linha inválida não aborta lote). MUST suportar **retomada de importação interrompida** por falha de worker, com checkpoint a cada 100 linhas e validação de hash de arquivo.

#### Funil
- **FR-021**: Sistema MUST oferecer quadro Kanban com colunas **configuráveis a partir de template padrão** (`Novo → Qualificado → Agendado → Compareceu → Perdido`); Admin Clínica pode editar nomes/ordem/adicionar coluna em tela de configuração separada.
- **FR-022**: Sistema MUST permitir mover card entre colunas via drag-and-drop, com persistência imediata.
- **FR-023**: Sistema MUST registrar `LeadMovidoNoFunil` como evento da timeline a cada movimentação manual ou automática.
- **FR-024**: Sistema MUST aceitar disparos externos (Fase 5+) que movam cards automaticamente.
- **FR-025**: Sistema MUST exigir **motivo obrigatório** ao mover card para "Perdido" com lista controlada (`sem_interesse`, `sem_retorno`, `preco`, `outro`); quando `outro`, exigir texto livre de mín. 10 caracteres. Motivo é gravado no evento `LeadMovidoNoFunil.motivo` e fica visível no card.

#### Segmentação
- **FR-026**: Sistema MUST permitir criar e aplicar tags **globais por tenant** em pacientes; normalização **case + accent-insensitive** (`Diabético` ≡ `diabetico`); **soft limit de 10 tags por paciente** com alerta UX (sem hard limit que rejeite).
- **FR-027**: Sistema MUST distinguir **tags sistêmicas com prefixo reservado `sys:`** (aplicadas/removidas apenas por ações automáticas do sistema) de tags livres (criadas e removidas por usuários comuns); usuários NÃO podem criar tags com prefixo `sys:`.
- **FR-028**: Sistema MUST manter o status do paciente em lista controlada `{lead, ativo, inativo, bloqueado}` com **máquina de estados explícita**: `lead → ativo` (qualquer), `ativo ↔ inativo` (qualquer), `* → bloqueado` (apenas Admin Clínica), `bloqueado → ativo` (apenas Admin Clínica). "Em tratamento" é tag `sys:em-tratamento` (não status).
- **FR-029**: Sistema MUST permitir busca/filtro por tag, status, profissional responsável, origem e período de criação.
- **FR-030**: Sistema MUST registrar cada mudança de status como evento de timeline.

#### Auditoria e LGPD
- **FR-031**: Sistema MUST registrar em log de auditoria toda criação, alteração sensível, mesclagem, importação, exportação e mudança de status de paciente.
- **FR-032**: Sistema MUST sanitizar payloads de auditoria — CPF mascarado, senhas e tokens jamais persistidos (herdado da Fase 0).
- **FR-033**: Sistema MUST oferecer ability `paciente.export` separada, atribuída apenas a Admin Clínica por padrão.
- **FR-034**: Sistema MUST registrar toda exportação no log de auditoria com `executor_id`, `timestamp`, `escopo` (filtros aplicados), `contagem` de registros e `hash_arquivo` (SHA-256 do CSV gerado); payload NÃO inclui PII dos registros exportados.
- **FR-035**: Sistema MUST manter coluna `anonimizado_em TIMESTAMPTZ NULL` na ficha de paciente; quando setada, **paciente fica oculto** em todas as queries de listagem/busca; evento `PacienteAnonimizado` é disparável via ação interna (ainda sem UI nesta fase); fluxo completo de direito ao esquecimento (US-13.x) fica para fase LGPD dedicada.

#### Permissões e isolamento
- **FR-036**: Sistema MUST aplicar os abilities da seção 2.4 (paciente.view/create/update/delete/import/export/merge/note.write/note.view) via o sistema de permissões da Fase 0.
- **FR-037**: Sistema MUST recusar 403 a Financeiro e Super Admin para qualquer ability de paciente, exceto quando habilidades futuras de "métricas agregadas" forem explicitamente concedidas.
- **FR-038**: Sistema MUST proibir Super Admin de enxergar PII de paciente — apenas contagens agregadas no painel administrativo (a tela em si vem em fase futura; o gate vale agora).

#### Performance e UX
- **FR-039**: Lista de pacientes MUST suportar até **50.000 pacientes por tenant** sem degradação perceptível.
- **FR-040**: Busca por nome ou telefone MUST devolver primeiros resultados em **p95 < 300 ms**; busca usa **similaridade** (suporta erro de digitação, busca parcial, normalização de acentos).
- **FR-041**: Timeline MUST carregar primeiro lote em < 1 s para até 1000 eventos (RF-011).

### 4.2 Non-Functional Requirements (transversais herdados)

- **NFR-1**: Conformidade LGPD em todas as operações (Princípio I da constituição).
- **NFR-2**: Isolamento multi-tenant validado por teste de integração em **todos** os endpoints introduzidos (extensão do `TenantIsolationTest` da Fase 0).
- **NFR-3**: Auditoria imutável (trigger de banco + bloqueio no modelo) — herdado.
- **NFR-4**: Idioma pt-BR para toda interface, e-mail, export e log voltado a usuário.
- **NFR-5**: Cobertura de testes ≥ 75% nesta fase (≥ 70% global mantendo o gate da Fase 0).

### 4.3 Key Entities

- **Paciente**: identifica uma pessoa atendida pela clínica. Atributos relevantes: identificadores (CPF/documento alternativo), contatos, status, profissional responsável, origem, convênio. Pertence a um tenant. Possui timeline e tags.
- **Anotação**: registro textual atrelado ao paciente, com tipo e visibilidade. Imutável. Pode ter retratação.
- **Tag**: rótulo aplicável a pacientes dentro de um tenant. Pode ser sistêmica (gerada pelo CRM) ou livre.
- **Importação**: tentativa de carregar pacientes em massa. Tem estado (pendente, processando, concluída, falha) e relatório.
- **EventoTimeline**: registro genérico de algo que aconteceu com o paciente. Possui tipo, autor, payload, timestamp. Esqueleto preparado para fases futuras injetarem novos tipos.
- **Convênio**: catálogo por tenant (CRUD próprio). Atributos: `nome`, `codigo_ans` (opcional), `is_active`.
- **PacienteConvenio**: pivot que materializa "paciente tem 0..2 convênios" com `numero_carteirinha`, `papel` (`principal` | `secundario`).
- **MesclagemPaciente**: rastro da operação para reverter dentro de 30 dias. Atributos: `paciente_alvo_id`, `pacientes_origem_ids` (array), `executor_id`, `executada_em`, `reversivel_ate`, `revertida_em` (NULL até reverter), `snapshot_pre_merge` (JSONB para restaurar).
- **TarefaReatribuicao**: criada automaticamente quando profissional responsável é desativado. Atributos: `tenant_id`, `pacientes_orfaos_ids`, `profissional_desativado_id`, `criada_em`, `concluida_em`. UI desta fila vem na Fase 10; a criação automática é entregue aqui.

---

## 5. Fora de Escopo desta Fase

As capacidades abaixo **não** são entregues aqui. Foram listadas para evitar escopo confuso:

- **Mensagens omnichannel (WhatsApp / Instagram / Chat web)** — Fase 3.
- **IA matricial, classificação de intenção, roteamento de fluxo** — Fase 4.
- **Agenda, consultas, tipos de atendimento, confirmação automática** — Fase 5.
- **Receituários (comum, especial, controlada)** — Fase 6.
- **Campanhas, disparos em massa, reativação** — Fase 7.
- **Direito ao esquecimento (fluxo completo), portabilidade de dados** — Fase 8 (LGPD). Esta fase entrega apenas o stub `anonimizado_em`.
- **Relatórios e dashboards** — Fase 10. Esta fase entrega métricas mínimas para o painel admin do tenant.
- **Webhooks externos, API pública versionada** — Fase 11.
- **Anamnese, diagnóstico, prontuário eletrônico** — **fora do produto Paciente360 inteiro** (decisão estratégica do produto: não competir com PEPs/SDEs).

---

## 6. Eventos de Domínio Emitidos

Esta fase publica os eventos abaixo. Esse é **contrato público** — fases futuras podem subscrever sem coordenação prévia.

| Evento                       | Disparado em                                              | Payload (campos lógicos)                                                                                             | Audit action                  |
|------------------------------|-----------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|-------------------------------|
| `PacienteCriado`             | Cadastro manual ou importação                             | `paciente_id`, `origem`, `via` (manual/importacao), `status_inicial`, `profissional_responsavel_id`                  | `paciente.criado`             |
| `PacienteAtualizado`         | Alteração de campos significativos (status/tag/telefone_primario/email/profissional_responsavel/convenio_principal) | `paciente_id`, `campos_alterados`, `diff_sanitizado`                                                                 | `paciente.atualizado`         |
| `PacienteStatusAlterado`     | Mudança de status válido                                  | `paciente_id`, `status_anterior`, `status_novo`, `motivo?`                                                            | `paciente.status_alterado`    |
| `PacienteMesclado`           | Mesclagem (manual ou via importação) confirmada           | `paciente_alvo_id`, `pacientes_origem_ids`, `executor_id`, `reversivel_ate`                                          | `paciente.mesclado`           |
| `PacienteMesclagemRevertida` | Reversão de mesclagem dentro da janela de 30 dias         | `paciente_alvo_id`, `pacientes_restaurados_ids`                                                                       | `paciente.mesclagem_revertida` |
| `PacienteAnonimizado`        | Stub disparado quando `anonimizado_em` é setado           | `paciente_id`, `executor_id`                                                                                          | `paciente.anonimizado`        |
| `TagAplicada`                | Aplicação de tag (livre ou sistêmica)                     | `paciente_id`, `tag_nome`, `tipo` (livre/sistemica)                                                                  | `paciente.tag_aplicada`       |
| `TagRemovida`                | Remoção de tag                                            | `paciente_id`, `tag_nome`                                                                                             | `paciente.tag_removida`       |
| `LeadMovidoNoFunil`          | Mudança de coluna no Kanban (manual ou automática)        | `paciente_id`, `coluna_anterior`, `coluna_nova`, `motivo?`, `automatico` (bool)                                      | `lead.movido_no_funil`        |
| `AnotacaoCriada`             | Anotação adicionada à timeline                            | `paciente_id`, `anotacao_id`, `tipo`, `autor_id`                                                                     | `paciente.anotacao_criada`    |
| `AnotacaoRetratada`          | Retratação linkada a anotação existente                   | `paciente_id`, `anotacao_id`, `retratacao_id`, `motivo?`                                                              | `paciente.anotacao_retratada` |
| `PacientesImportados`        | Importação concluída                                      | `importacao_id`, `contagens` (importados/duplicatas/erros), `arquivo_hash`                                            | `paciente.imported`           |
| `PacientesExportados`        | Exportação concluída                                      | `executor_id`, `escopo` (filtros), `contagem`, `arquivo_hash`                                                        | `paciente.exported`           |

Todo evento implementa o contrato `Auditable` da Fase 0; o listener wildcard grava no log de auditoria sem código adicional.

---

## 7. Clarifications resolvidas

Os 13 pontos originais foram resolvidos via `/speckit.clarify` em 2026-05-10. Veja a seção `## Clarifications > ### Session 2026-05-10` no topo deste documento para a lista completa de Q→A; as decisões já estão materializadas nos FRs, ACs, Edge Cases e Key Entities acima.

Resumo das resoluções:

| #   | Tópico                          | Decisão                                                                                                          |
|-----|---------------------------------|------------------------------------------------------------------------------------------------------------------|
| Q1  | Unicidade                       | CPF único por tenant (NULL permitido); telefone não único; 1 primário + N secundários                            |
| Q2  | Deduplicação                    | Sugerir mesclar com confirmação; reversível 30 dias; campo mais completo prevalece                               |
| Q3  | CPF                             | Opcional; DV validado quando preenchido; `documento_estrangeiro` opcional                                        |
| Q4  | Origem                          | Enum fixo + `origem_detalhe` livre; canais externos setam por regra fixa                                         |
| Q5  | Tags                            | Globais por tenant; sistêmicas com prefixo `sys:`; soft limit 10; normalização case+accent-insensitive          |
| Q6  | Status                          | 4 status (lead/ativo/inativo/bloqueado); "em tratamento" vira tag; bloqueado = sem msg auto + sem agendar       |
| Q7  | Funil                           | Configurável a partir de template; manual + automação Fase 5; motivo obrigatório em Perdido; funil ≠ status     |
| Q8  | Timeline                        | Apenas eventos significativos; 4 tipos de anotação; imutáveis + retratação; visibilidade por perfil + tipo      |
| Q9  | Importação                      | 5 MB / 10k linhas; parcial sempre; status escolhido; reimport atualiza campos vazios; checkpoint a cada 100      |
| Q10 | Convênio                        | Catálogo CRUD por tenant; até 2 por paciente; carteirinha sem validação                                          |
| Q11 | Profissional responsável        | 0 ou 1; órfão + tarefa de reatribuição ao desativar                                                              |
| Q12 | LGPD/exportação                 | `paciente.export` separada (default Admin Clínica); audit completo; stub `anonimizado_em` + evento              |
| Q13 | Volume/performance              | 50.000 pacientes/tenant; busca p95 < 300 ms; similaridade (trigram)                                              |

---

## 8. Success Criteria *(mandatory)*

Critérios medíveis, agnósticos de tecnologia, verificáveis sem detalhes de implementação:

- **SC-001**: Atendente cadastra paciente novo (formulário completo válido) em **até 2 minutos**, sem ajuda externa, na primeira tentativa.
- **SC-002**: Médico abre a timeline de um paciente com 500 eventos e vê o primeiro lote em **menos de 1 segundo** (p95).
- **SC-003**: Admin Clínica importa uma planilha de 1000 linhas e recebe o relatório final (importados/duplicados/erros) em **até 5 minutos**.
- **SC-004**: Movimentação de card no Kanban responde em **menos de 300 ms** (p95) e a mudança persiste após refresh.
- **SC-005**: 100% dos endpoints autenticados desta fase passam no `TenantIsolationTest` expandido (cross-tenant retorna 404/403, nunca 200).
- **SC-006**: 100% das ações sensíveis (criação, mesclagem, importação, exportação, mudança de status, anotação) geram entrada em log de auditoria.
- **SC-007**: 0 vazamentos de PII em logs de aplicação (CPF, e-mail, telefone aparecem mascarados ou ausentes em logs estruturados; apenas no DB).
- **SC-008**: 0 strings hardcoded em outro idioma além de pt-BR nas telas, e-mails e relatórios novos.
- **SC-009**: Cobertura de testes da fase ≥ 75%; cobertura global após esta fase ≥ 70%.
- **SC-010**: Financeiro e Super Admin recebem **403** em **todas** as 100% das tentativas de acessar endpoint de paciente nos testes.
- **SC-011**: Busca por nome ou telefone em base de **50.000 pacientes** responde em **p95 < 300 ms**.

---

## 9. Definição de Pronto desta Fase

Checklist verificável antes de declarar Fase 2 entregue:

- [ ] Todas as US (3.1 a 3.5) implementadas, testadas e mescladas em `main`.
- [x] Todos os 13 NEEDS_CLARIFICATION resolvidos via `/speckit.clarify` (2026-05-10) e refletidos no `spec.md`; pendente apenas projeção em `plan.md`, `data-model.md` e `tasks.md`.
- [ ] Todos os critérios de aceitação numerados (AC-3.x.y) cobertos por pelo menos um teste automatizado.
- [ ] `TenantIsolationTest` expandido cobrindo 100% dos endpoints novos.
- [ ] Eventos de domínio (seção 6) emitidos e gravados em log de auditoria conforme contrato.
- [ ] Permissões da seção 2.4 aplicadas e validadas por teste (incluindo 403 para Financeiro/Super Admin).
- [ ] Cobertura de testes desta fase ≥ 75%.
- [ ] Pint clean; OpenAPI atualizado e validado pelo drift checker; Scribe gerado sem erros críticos.
- [ ] Quickstart atualizado com seção CRM (como cadastrar paciente, como rodar importação local).
- [ ] Pelo menos **1 jornada E2E** cobrindo o fluxo principal: login → cadastrar paciente → adicionar tag → adicionar anotação → mover no funil.
- [ ] Plano de migração de tags sistêmicas existentes na Fase 0 (se houver) executado e auditado.
- [ ] LGPD: stub `anonimizado_em` presente, evento `PacienteAnonimizado` emissível por ação interna; texto de UI deixa claro que fluxo completo de esquecimento chega em fase posterior.
- [ ] Documentação do contrato de evento da timeline publicada (para Fase 3+ consumir).

---

## 10. Princípios da Constituição Tocados por Cada US

| US     | Princípios da Constituição                                                                              | Como toca                                                                                                                                       |
|--------|---------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------|
| US-3.1 | **I (LGPD)**, **II (Multi-tenant)**, **IV (Spec-Driven)**, **VII (Segurança Operacional)**              | Coleta PII; tenant_id obrigatório; abilities granulares; CPF nunca em log claro.                                                                |
| US-3.2 | **I (LGPD)**, **III (Auditabilidade)**, **V (Observabilidade)**                                         | Timeline é o canal primário de auditoria visível ao usuário; anotações privadas materializam controle de acesso a dado clínico.                  |
| US-3.3 | **I (LGPD)**, **II**, **IV**, **V**                                                                     | Import em massa cria volume; auditoria precisa rastrear origem; idempotência (V) protege re-execução; jobs em fila isolados por tenant (II).     |
| US-3.4 | **II**, **IV**                                                                                          | Funil é tenant-scoped; movimentações automáticas (Fase 5+) precisam de contrato estável (IV).                                                    |
| US-3.5 | **I (LGPD)**, **II**, **IV**                                                                            | Tags e status orientam **futuras** campanhas (Fase 7) — opt-in/janela WhatsApp dependem desse modelo; status `bloqueado` é gate anti-spam.       |

A fase **não toca diretamente** os princípios **VI (Conformidade Meta nos Disparos)** (sem disparo nesta fase) e **III (IA)** (sem IA nesta fase). Esses princípios serão exercitados em fases subsequentes que dependem do CRM.

---

## 11. Índice de Critérios de Aceitação (referência para `tasks.md`)

Todo critério acima é rastreável. O `tasks.md` desta fase deve referenciar cada um e garantir cobertura de teste.

- **US-3.1**: AC-3.1.1 (cadastro mínimo) · AC-3.1.2 (CPF DV) · AC-3.1.3 (duplicata) · AC-3.1.4 (isolamento) · AC-3.1.5 (status inicial) · AC-3.1.6 (profissional) · AC-3.1.7 (origem) · AC-3.1.8 (auditoria).
- **US-3.2**: AC-3.2.1 (eventos próprios) · AC-3.2.2 (granularidade) · AC-3.2.3 (filtros) · AC-3.2.4 (tipos e visibilidade de anotações) · AC-3.2.5 (imutabilidade + retratação) · AC-3.2.6 (perf <1s) · AC-3.2.7 (anotações fora de portabilidade) · AC-3.2.8 (contrato para fases futuras).
- **US-3.3**: AC-3.3.1 (template) · AC-3.3.2 (upload async) · AC-3.3.3 (limite) · AC-3.3.4 (parcial vs tudo-ou-nada) · AC-3.3.5 (dedup na importação) · AC-3.3.6 (status inicial) · AC-3.3.7 (reimportação) · AC-3.3.8 (auditoria) · AC-3.3.9 (permissão) · AC-3.3.10 (não bloqueia sistema).
- **US-3.4**: AC-3.4.1 (colunas padrão) · AC-3.4.2 (card info) · AC-3.4.3 (drag-and-drop persistido) · AC-3.4.4 (motivo perdido) · AC-3.4.5 (movimento automático — gancho) · AC-3.4.6 (filtros) · AC-3.4.7 (funil vs status) · AC-3.4.8 (perf).
- **US-3.5**: AC-3.5.1 (criação livre) · AC-3.5.2 (sistêmicas vs livres) · AC-3.5.3 (limite) · AC-3.5.4 (multi-tag) · AC-3.5.5 (mudança status registrada) · AC-3.5.6 (transições inválidas) · AC-3.5.7 (busca por tag+status) · AC-3.5.8 (significado bloqueado).

**Total: 42 critérios de aceitação numerados.**

---

## 12. Assumptions

Itens decididos por inferência razoável (não levantados como NEEDS_CLARIFICATION):

- Fuso horário operacional: `America/Sao_Paulo`. Timestamps no banco em UTC.
- Idioma: pt-BR único nesta fase. Suporte a i18n estrutural fica para fase futura quando houver demanda de cliente internacional.
- Formato de telefone: aceito com ou sem máscara; canonicalizado para E.164 ao persistir; exibido formatado no formato BR.
- Data de nascimento: opcional; quando ausente, idade não é exibida na ficha.
- Foto de paciente: fora do escopo desta fase (entra como melhoria UX em fase posterior).
- Migração de dados de outras ferramentas: coberta pela US-3.3; ETL específico por cliente é projeto de serviço, não de produto.
- Soft delete de paciente: **anonimização preferencial sobre delete físico** para conformidade LGPD. Coluna `anonimizado_em` é o gate; paciente anonimizado permanece em audit_logs (imutáveis) mas é invisível em listagens. Delete físico não é exposto via UI nesta fase.
- Notificações em tempo real (ex.: novo lead chegou no funil): fora do escopo desta fase; tempo real é mais valioso na Fase 3 (inbox de mensagens).
