# Feature Specification: Gestão de Profissionais + Unlock Onboarding Step 2 (US-1.2.2)

**Feature Branch**: `012-professionals-management`
**Created**: 2026-05-23
**Status**: Draft
**Input**: User description: "Gestão de profissionais clínicos (médicos, dentistas, fisioterapeutas) pelo painel do tenant — CRUD completo no dashboard + desbloqueio do step 2 do wizard de onboarding (cadastro do primeiro profissional). Hoje o tenant não consegue cadastrar médico pelo dashboard; lacuna bloqueia onboarding completo e operação contínua da clínica."

## Clarifications

### Session 2026-05-23

- Q: Especialidade é texto totalmente livre, autocomplete contra histórico do tenant, ou lista fechada curada? → A: Autocomplete contra histórico do tenant. O campo aceita digitação livre, mas exibe sugestões dos valores já cadastrados naquele tenant (mesmo padrão usado para Tags em Pacientes). Reduz duplicação de variações como "Cardiologia"/"cardio"/"Cardiologia Clínica" sem engessar quando o admin precisa de uma especialidade nova. Sem catálogo nacional centralizado para manter.
- Q: Quando o admin cadastra profissional via convite por email e o email já pertence a um usuário existente do mesmo tenant, o sistema vincula automaticamente ou pede confirmação? → A: Pede confirmação explícita. O formulário detecta o email durante a digitação/submissão, exibe mensagem clara ("Esse email já pertence ao usuário X. Deseja vincular esse usuário ao novo profissional?") com botões "Vincular" e "Cancelar". Evita vinculação acidental e deixa a intenção do admin explícita.
- Q: Os tipos de conselho devem ser limitados a 5 (CRM, CRO, COREN, CRP, Outro) ou expandidos com mais profissões (CREFITO, CFN, CFFa, CRF, etc.)? → A: Manter 5 categorias no MVP — CRM, CRO, COREN, CRP e "Outro". Quando "Outro" é selecionado, o formulário exibe um campo de texto adicional para digitar o nome do conselho (ex.: "CREFITO", "CFN"). Cobre 90% dos casos clínicos no Brasil sem fazer manutenção de catálogo amplo; expansão entra em spec futura se feedback dos tenants indicar necessidade.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Cadastro do Primeiro Profissional via Onboarding (Priority: P1)

Um administrador de clínica recém-criada (acabou de completar o step 1 do wizard de onboarding) encontra o step 2 "Primeiro profissional" agora desbloqueado. Ele preenche os dados do primeiro médico/profissional da clínica (nome, conselho profissional, especialidade) e tem duas opções: vincular esse profissional a um usuário do sistema já existente OU enviar convite por email para o profissional criar sua própria conta. Após concluir, o wizard avança e o step 4 "Configurar agenda" é desbloqueado automaticamente.

**Why this priority**: Sem isto, todo tenant recém-cadastrado fica preso no onboarding após o step 1 — a clínica não consegue operar porque não tem profissional cadastrado para receber consultas. Esta é a continuação natural da jornada de setup; P1 porque destrava o uso do produto para todos os novos tenants.

**Independent Test**: Criar uma clínica nova via cadastro público; logar como admin; completar o step 1 do wizard; verificar que o step 2 ficou desbloqueado e clicável; preencher os dados de um profissional fictício; concluir; validar que o wizard mostra step 2 como "Concluído" e step 4 como "Pendente" (desbloqueado).

**Acceptance Scenarios**:

1. **Given** admin acabou de completar o step 1 do onboarding, **When** observa a lista de etapas, **Then** o step 2 "Primeiro profissional" está com status "Pendente" e clicável (não mais bloqueado).
2. **Given** admin clica em "Iniciar" no step 2, **When** o formulário do primeiro profissional abre, **Then** vê campos para nome, tipo de conselho, número do conselho, UF do conselho, especialidade e a escolha entre "Vincular a usuário existente" ou "Enviar convite por email".
3. **Given** admin preenche todos os campos válidos e escolhe "Vincular a usuário existente", **When** submete o formulário, **Then** o profissional é criado vinculado àquele usuário, o step 2 é marcado como concluído e o step 4 "Configurar agenda" fica desbloqueado.
4. **Given** admin preenche dados e escolhe "Enviar convite por email", **When** submete, **Then** um convite é enviado ao email informado, o profissional é criado em estado inativo aguardando aceite, e o wizard avança normalmente.
5. **Given** admin escolhe pular o step 2 (botão "Pular"), **When** confirma, **Then** o step 2 é marcado como "Pulado" mas o step 4 NÃO é desbloqueado automaticamente (faz sentido — sem profissional, não há agenda para configurar).
6. **Given** profissional é criado via convite e o convidado aceita posteriormente, **When** o aceite ocorre, **Then** o profissional é automaticamente ativado (sem ação manual do admin).

---

### User Story 2 — Cadastro de Profissional pelo Painel (Priority: P1)

Um administrador de clínica que JÁ TEM a operação rodando precisa cadastrar um novo médico contratado. Ele acessa o menu "Configurações → Profissionais" na barra lateral, vê a lista de profissionais já cadastrados e clica em "Novo profissional". Preenche os mesmos dados (nome, conselho, especialidade) e a mesma escolha (vincular ou convidar). O novo profissional aparece imediatamente na lista, pronto para receber agendamentos.

**Why this priority**: Mesmo após o onboarding inicial, contratações de novos médicos são evento recorrente. Sem esta UI, o admin precisaria pedir ao super-admin externamente ou usar workaround. P1 porque é a contraparte operacional do US-1 — desbloqueia gestão contínua, não só primeira configuração.

**Independent Test**: Logar como admin-clinica em tenant que já passou do onboarding; navegar até "Configurações → Profissionais" na sidebar; ver a lista (vazia ou populada); clicar "Novo profissional"; preencher dados de um novo médico; submeter; verificar que aparece na lista com status "Ativo".

**Acceptance Scenarios**:

1. **Given** admin-clinica logado em tenant ativo, **When** observa a barra lateral, **Then** vê o item "Profissionais" dentro do grupo "Configurações".
2. **Given** admin clica em "Profissionais" na sidebar, **When** a tela carrega, **Then** vê uma tabela com colunas Nome, Conselho, Especialidade, Status e Ações; toolbar com botão "Novo profissional", filtro de status (Todos/Ativos/Inativos) e busca por nome; empty state amistoso se ainda não há nenhum profissional cadastrado.
3. **Given** lista vazia, **When** o admin clica no CTA "Cadastrar primeiro profissional" do empty state, **Then** abre o mesmo formulário de criação.
4. **Given** admin com 8 profissionais cadastrados, **When** filtra por "Inativos", **Then** a tabela mostra apenas os profissionais com status inativo.
5. **Given** admin digita "Maria" na busca, **When** ocorre debounce, **Then** a tabela filtra para mostrar apenas profissionais com "Maria" no nome (case-insensitive).
6. **Given** admin clica em "Novo profissional", **When** o formulário abre, **Then** vê os mesmos campos do US-1 (nome, conselho, especialidade, vincular ou convidar).
7. **Given** admin submete formulário válido vinculando a um usuário existente, **When** a operação completa, **Then** o profissional aparece imediatamente no topo da lista com status "Ativo".
8. **Given** tenant com 3 profissionais já cadastrados nas especialidades "Cardiologia", "Pediatria" e "Clínica Geral", **When** admin abre o formulário de cadastro e começa a digitar "Card" no campo Especialidade, **Then** o sistema sugere "Cardiologia" como autocomplete; admin pode aceitar a sugestão ou continuar digitando para criar uma especialidade nova.
9. **Given** admin selecionou "Outro" no tipo de conselho, **When** o campo é selecionado, **Then** um campo adicional de texto "Nome do conselho" aparece como obrigatório (ex.: digitar "CREFITO" ou "CFN"); sem preencher, a submissão é bloqueada.
10. **Given** admin escolhe a opção "Enviar convite por email" e digita um email que já pertence a um usuário ativo do mesmo tenant, **When** submete, **Then** o sistema exibe modal de confirmação informando que aquele email pertence ao usuário X e pergunta se deseja vincular esse usuário ao novo profissional. Apenas após "Vincular" o profissional é criado vinculado àquele usuário existente.

---

### User Story 3 — Edição de Profissional Existente (Priority: P1)

Um administrador precisa atualizar dados de um profissional já cadastrado — o CRM mudou de estado, o nome civil foi atualizado, ou a especialidade foi refinada. Ele clica em "Editar" na linha do profissional, ajusta os campos, e salva.

**Why this priority**: Mudanças de dados profissionais são frequentes (transferência de CRM entre estados, casamento/divórcio com mudança de nome). Sem UI de edição, o admin fica preso em dados desatualizados ou precisa escalar para o super-admin. P1 porque é o complemento óbvio do cadastro.

**Independent Test**: Logar como admin; abrir lista de profissionais; clicar "Editar" em um profissional existente; alterar campo de especialidade; salvar; verificar que a tabela reflete o novo valor imediatamente.

**Acceptance Scenarios**:

1. **Given** lista de profissionais com pelo menos 1 ativo, **When** o admin clica em "Editar" naquela linha, **Then** abre o mesmo formulário de cadastro pré-populado com os dados atuais do profissional (exceto o vínculo de usuário, que não pode ser alterado nesta edição).
2. **Given** formulário de edição aberto, **When** o admin altera o número do conselho e salva, **Then** os dados são atualizados, o usuário recebe confirmação visual (toast) e a tabela mostra o novo valor sem precisar de recarregamento.
3. **Given** admin tenta gravar um número de conselho que já existe para outro profissional do mesmo tenant (mesmo tipo e mesma UF), **When** submete, **Then** recebe erro de validação com a mensagem explícita ("Já existe outro profissional com este conselho").

---

### User Story 4 — Desativação e Reativação de Profissional (Priority: P2)

Um administrador precisa desativar um profissional que saiu da clínica. Ao clicar em "Desativar", o sistema confirma a ação, marca o profissional como inativo (não exclui), dispara reatribuição automática dos pacientes que estavam sob sua responsabilidade (comportamento já existente), e o profissional desaparece da agenda. Caso o profissional retorne meses depois, pode ser reativado pelo mesmo botão (agora rotulado "Reativar").

**Why this priority**: Saídas e retornos de profissionais são comuns em clínicas (afastamento, retorno após licença). Sem UI, o admin precisaria de intervenção externa. P2 porque é menos frequente que cadastro/edição, mas crítico quando ocorre.

**Independent Test**: Cadastrar um profissional fictício; atribuir um paciente a ele; clicar "Desativar" no profissional; confirmar; verificar que (a) o status na tabela mudou para "Inativo"; (b) o paciente que estava sob aquele profissional foi marcado para reatribuição; (c) o profissional não aparece mais na lista de profissionais disponíveis para nova consulta.

**Acceptance Scenarios**:

1. **Given** profissional ativo na tabela, **When** o admin clica em "Desativar" na linha, **Then** vê um modal de confirmação descritivo (mostra nome do profissional + impacto: "Pacientes serão reatribuídos").
2. **Given** modal de confirmação aberto, **When** o admin confirma, **Then** o profissional fica com status "Inativo", pacientes órfãos entram em processo de reatribuição automática, e o item desaparece de listas de seleção em outras telas (criação de consulta, etc.).
3. **Given** profissional inativo, **When** o admin clica em "Reativar", **Then** o profissional volta ao status "Ativo" sem necessidade de reatribuir pacientes (pacientes já reatribuídos permanecem onde estão).
4. **Given** desativação concluída, **When** o admin observa a tabela, **Then** o profissional desativado aparece se o filtro for "Todos" ou "Inativos"; some se filtro for "Ativos" (default).

---

### User Story 5 — Permissões e Visibilidade do Menu (Priority: P1)

Um profissional comum (médico) ou recepcionista, sem permissão administrativa, NÃO deve ver o item "Profissionais" na barra lateral e, se tentar acessar a URL diretamente, deve ser barrado. Apenas administradores da clínica gerenciam os profissionais.

**Why this priority**: Gate de segurança. Sem isto, qualquer médico veria/editaria dados de colegas, violando o princípio de privilégio mínimo. P1 porque é regra de auth, não UX nice-to-have.

**Independent Test**: Cadastrar dois usuários no mesmo tenant — um com role `admin-clinica` e outro com role `medico`. Logar como cada um. Confirmar que o admin vê o item "Profissionais" na sidebar e acessa a página; confirmar que o médico NÃO vê o item, e ao tentar acessar a URL direta recebe negação (redirecionamento ou mensagem de acesso negado).

**Acceptance Scenarios**:

1. **Given** usuário com role `admin-clinica`, **When** carrega o painel, **Then** vê o item "Profissionais" no grupo "Configurações" da sidebar.
2. **Given** usuário com role `medico` ou `recepcionista`, **When** carrega o painel, **Then** o item "Profissionais" NÃO aparece na sidebar (ocultado pela regra de permissão).
3. **Given** usuário sem permissão de gerenciar profissionais, **When** acessa diretamente a URL `/panel/profissionais`, **Then** o sistema bloqueia o acesso e redireciona/exibe mensagem apropriada de permissão negada.
4. **Given** usuário sem permissão, **When** tenta chamar a API de criação/edição/desativação direto, **Then** recebe resposta 403 (proibido).

---

### User Story 6 — Listagem com Filtros e Busca (Priority: P2)

Em clínicas maiores com 20+ profissionais, o admin precisa filtrar rapidamente: ver apenas ativos, apenas inativos, ou buscar por nome. A lista deve responder de forma fluida sem recarregamentos completos.

**Why this priority**: Qualidade percebida em clínicas maiores. P2 porque clínicas pequenas (1-5 profissionais) vivem bem sem filtros sofisticados, mas escalabilidade do produto exige isso.

**Independent Test**: Cadastrar 12 profissionais fictícios (8 ativos, 4 inativos); abrir a lista; verificar default mostra os 8 ativos; trocar filtro para "Todos" e ver os 12; trocar para "Inativos" e ver os 4; buscar "João" e ver subset; remover busca e ver lista voltar.

**Acceptance Scenarios**:

1. **Given** tenant com 12 profissionais (8 ativos), **When** o admin abre a lista, **Then** vê 8 itens por default (filtro "Ativos" aplicado).
2. **Given** filtro de status visível, **When** muda para "Todos", **Then** vê os 12 itens.
3. **Given** busca por "Dr. Silva", **When** digita os caracteres com debounce, **Then** vê apenas profissionais cujo nome contém "Silva" (case-insensitive, sem acentos).
4. **Given** lista com 50+ profissionais, **When** rola até o final, **Then** carregamento incremental ("ver mais" ou rolagem infinita) traz a próxima página.

---

### Edge Cases

- **Email de convite que já é usuário do tenant**: NÃO vincula direto — sistema exibe modal de confirmação explícita ("Esse email já pertence ao usuário X — vincular?") e só efetiva a vinculação após o admin confirmar. Evita vínculo acidental por digitação repetida (Q2 da Clarifications).
- **Email de convite que é usuário de OUTRO tenant**: cadastro bloqueado com mensagem explicativa de isolamento.
- **Usuário do sistema com papel super-admin (global)**: não pode ser vinculado a profissional de tenant (isolamento).
- **Tentativa de cadastrar dois profissionais com mesmo número de conselho no mesmo tenant**: validação bloqueia (mesma tupla tipo+número+UF é única por tenant).
- **Profissional desativado com agendamentos futuros**: pacientes são reatribuídos via processo automático já existente; agendamentos NÃO são automaticamente cancelados (admin precisa decidir manualmente).
- **Reativação de profissional cujos pacientes já foram reatribuídos**: pacientes NÃO retornam automaticamente; admin pode reatribuir manualmente se desejar.
- **Admin tenta desativar o ÚNICO profissional ativo da clínica**: sistema permite (não bloqueia), mas mostra aviso explícito de que a clínica ficará sem profissionais ativos.
- **Convite expirado antes do aceite**: profissional permanece inativo; admin pode reenviar convite ou cancelar e refazer.
- **Profissional sem especialidade preenchida**: campo opcional; aceita vazio.
- **Conselho número com caracteres especiais (ponto, traço)**: aceitar dígitos, letras, pontos e traços; rejeitar outros caracteres.

## Requirements *(mandatory)*

### Functional Requirements

**Cadastro e gestão (CRUD)**

- **FR-001**: Administradores da clínica MUST poder cadastrar um novo profissional via formulário com os seguintes campos obrigatórios: nome (3–150 caracteres), tipo de conselho, número de conselho, UF do conselho. Especialidade é opcional e o campo MUST oferecer autocomplete contra os valores já cadastrados no tenant (sugestões aparecem conforme o admin digita), permitindo escolha de valor existente ou entrada de novo valor.
- **FR-002**: O tipo de conselho MUST ser limitado a uma lista fechada de 5 opções: CRM, CRO, COREN, CRP e "Outro". Quando "Outro" é selecionado, o formulário MUST exibir um campo de texto adicional obrigatório para o admin digitar o nome do conselho (ex.: "CREFITO", "CFN", "CFFa"). O valor digitado é persistido junto ao profissional para exibição nas listas.
- **FR-003**: A UF do conselho MUST aceitar somente unidades federativas brasileiras válidas (2 letras).
- **FR-004**: O sistema MUST impedir o cadastro de dois profissionais com a mesma tupla (tipo de conselho, número de conselho, UF) dentro do mesmo tenant, retornando mensagem explícita ao usuário.
- **FR-005**: Ao cadastrar, o admin MUST escolher uma das duas opções de vínculo de usuário do sistema: (a) vincular a um usuário já existente do tenant, ou (b) enviar convite por email para um novo usuário criar conta.
- **FR-005a**: Quando o admin escolhe a opção de convite por email e informa um email que já pertence a um usuário existente do mesmo tenant, o sistema MUST exibir uma confirmação explícita ("Esse email já pertence ao usuário {nome}. Deseja vincular esse usuário ao novo profissional?") com botões "Vincular" e "Cancelar". Apenas após confirmação explícita do admin, o sistema vincula o profissional ao usuário existente (sem criar convite duplicado).
- **FR-006**: Quando vinculado a usuário existente, o profissional MUST ser criado em estado ativo imediatamente.
- **FR-007**: Quando criado via convite por email, o profissional MUST ser criado em estado inativo até o convidado aceitar o convite; ao aceitar, o profissional é ativado automaticamente.
- **FR-008**: Tentativa de vincular a um usuário que pertence a outro tenant MUST ser bloqueada com mensagem explicativa.
- **FR-009**: Tentativa de vincular a um usuário com papel super-administrador global MUST ser bloqueada.
- **FR-010**: Administradores MUST poder editar dados de profissionais existentes — exceto o vínculo de usuário, que é imutável após a criação.
- **FR-011**: Administradores MUST poder desativar um profissional ativo (soft delete: o registro permanece no banco para auditoria).
- **FR-012**: Desativar um profissional MUST disparar o processo já existente de reatribuição automática de pacientes que estavam sob a sua responsabilidade.
- **FR-013**: Desativar um profissional NÃO MUST cancelar agendamentos futuros automaticamente — apenas remove o profissional de listas de seleção para novos agendamentos.
- **FR-014**: Profissionais desativados MUST poder ser reativados, retornando ao status ativo sem efeitos colaterais (pacientes já reatribuídos permanecem onde estão).
- **FR-015**: Antes de desativar, o sistema MUST exibir um modal de confirmação descritivo informando o impacto (reatribuição de pacientes), com nome do profissional e botões claros de confirmar/cancelar.

**Listagem e filtros**

- **FR-016**: A página de profissionais MUST exibir uma tabela com colunas: Nome, Conselho (concatenação de tipo + número + UF), Especialidade, Status (ativo/inativo) e Ações (Editar, Desativar/Reativar).
- **FR-017**: A toolbar MUST conter um botão "Novo profissional", um filtro de status (Todos/Ativos/Inativos com default "Ativos") e um campo de busca por nome.
- **FR-018**: A busca por nome MUST aplicar correspondência case-insensitive e ignorar acentos.
- **FR-019**: A listagem MUST suportar paginação incremental para tenants com 50+ profissionais.
- **FR-020**: Quando não há profissionais cadastrados, a página MUST exibir um empty state amistoso com chamada de ação para cadastrar o primeiro.

**Permissões**

- **FR-021**: Apenas administradores da clínica MUST poder ver e usar a página de gestão de profissionais.
- **FR-022**: Médicos e recepcionistas NÃO MUST ver o item "Profissionais" na barra lateral nem acessar a URL direta.
- **FR-023**: Tentativas de operações na API por usuário sem permissão MUST retornar resposta de proibido (403).

**Onboarding — Step 2**

- **FR-024**: O wizard de onboarding MUST desbloquear o step 2 "Primeiro profissional" automaticamente assim que o step 1 (dados da clínica) for concluído.
- **FR-025**: Após o step 2 ser concluído (cadastro do primeiro profissional), o sistema MUST desbloquear automaticamente o step 4 "Configurar agenda" do wizard.
- **FR-026**: Se o admin optar por pular o step 2 (botão "Pular"), o step 4 NÃO MUST ser desbloqueado, e o admin pode retornar ao step 2 mais tarde.
- **FR-027**: O formulário do step 2 no wizard MUST oferecer os mesmos campos e validações da página de cadastro standalone.
- **FR-028**: Ao concluir o step 2 com sucesso, o sistema MUST registrar no estado do onboarding o identificador do profissional criado e o método de vínculo escolhido (usuário existente vs convite).
- **FR-029**: Os steps 3 (`channel_connection`) e 5 (`ai_knowledge_base`) MUST permanecer bloqueados nesta versão — serão abertos em features futuras.

**Acessibilidade**

- **FR-030**: Formulários de cadastro/edição MUST usar estrutura semântica de formulário com rótulos associados aos campos.
- **FR-031**: Mensagens de erro de validação MUST ser anunciadas para tecnologias assistivas (regiões com `aria-live` apropriado).
- **FR-032**: Modais de confirmação de desativação MUST seguir o padrão de modal acessível: foco preso, tecla Esc fecha, retorno de foco ao trigger ao fechar.
- **FR-033**: Diferenciações visuais por cor (status ativo verde, inativo cinza) MUST ser acompanhadas de texto ou ícone explícitos.

**Auditoria e eventos**

- **FR-034**: Cada criação, edição e desativação de profissional MUST gerar registro de auditoria com identificação do ator, do tenant e do recurso afetado.
- **FR-035**: A desativação MUST disparar evento de domínio que aciona o processo de reatribuição de pacientes (comportamento já implementado nas fases anteriores).
- **FR-036**: A ativação automática de profissional ao aceite de convite MUST gerar registro de auditoria distinto, indicando que foi via evento (não ação humana direta).

### Key Entities

- **Profissional (Professional)**: já existe no banco. Atributos relevantes para esta feature: nome, tipo de conselho, número de conselho, UF do conselho, especialidade, status (ativo/inativo), vínculo opcional a usuário do sistema, vínculo obrigatório ao tenant. Possui histórico via soft delete.
- **Usuário do sistema (User)**: já existe. Pode ser vinculado a um profissional (relação 0-1). Pertence a um tenant.
- **Convite (Invitation)**: já existe (introduzido na Fase 4). Pode ser criado com o papel de médico e referenciar a criação pendente de um profissional.
- **Estado do Onboarding (Onboarding State)**: já existe no banco como campo JSON do tenant. Inclui status e payload de cada step do wizard.

Nenhuma nova entidade persistida é criada por esta feature. As alterações estruturais necessárias são limitadas a: garantir restrição de unicidade em conselho (tipo + número + UF + tenant) caso ainda não exista, e adicionar a permissão `professional.manage` na lista de permissões do sistema.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% dos tenants recém-criados conseguem completar o step 2 do onboarding pelo wizard (sem precisar de intervenção externa) em **menos de 5 minutos** desde o término do step 1.
- **SC-002**: Administradores de clínicas existentes conseguem cadastrar um novo profissional pela página de gestão em **menos de 90 segundos** desde abrir o menu até a confirmação visual.
- **SC-003**: Em **100% dos cenários de teste cross-tenant**, dados de profissionais de tenant A nunca aparecem para usuários de tenant B na lista, busca ou edição.
- **SC-004**: Em **100% dos cenários de teste de permissão**, usuários sem permissão administrativa nunca conseguem listar, criar, editar ou desativar profissionais (resposta de proibido em todas as tentativas).
- **SC-005**: Desativar um profissional com pacientes vinculados aciona reatribuição automática em **100% dos casos**, sem deixar pacientes órfãos por mais de 1 minuto.
- **SC-006**: Após concluir o step 2 do onboarding, o step 4 fica desbloqueado em **100% dos casos** (regra determinística).
- **SC-007**: Em auditoria automatizada de acessibilidade (axe/Lighthouse) na página de profissionais e no modal de cadastro, **0 violações sérias ou críticas** são reportadas.
- **SC-008**: O log de auditoria registra **100% das operações** de criação, edição, desativação e ativação automática por convite — com identificação completa do ator, do recurso e do timestamp.
- **SC-009**: Em pesquisa de usabilidade informal com 3 administradores de clínica, **pelo menos 2 conseguem cadastrar um novo profissional sem precisar consultar ajuda externa** no primeiro contato com a tela.
- **SC-010**: Validação de unicidade de conselho (tipo+número+UF) bloqueia **100% das tentativas** de cadastrar conselho duplicado no mesmo tenant, sem inserir o registro.

## Assumptions

- **Modelo Professional já existente**: a entidade está estabelecida desde a Fase 5 (Agendamento) e a presente feature consome/estende sem redesenhar a estrutura.
- **Reatribuição de pacientes ao desativar**: comportamento já implementado na Fase 2 (job `ReassignOrphansJob` disparado por evento de desativação). Esta feature confia nesse comportamento sem reimplementar.
- **Convite com role médico**: o fluxo de Invitation com aceite por email já existe (Fase 4). Esta feature reusa, apenas adicionando o trigger de ativação automática do profissional no aceite.
- **App Shell e onboarding wizard**: ambos entregues nas specs 009 e 010; esta feature integra-se sem mexer na infraestrutura visual base.
- **Permissão `professional.manage`**: nova permission name a ser registrada no seeder de roles. Aplicada por default ao role `admin-clinica`.
- **Tipos de conselho**: CRM (médico), CRO (dentista), COREN (enfermagem), CRP (psicologia), e "Outro" como fallback genérico para outros profissionais (ex.: nutricionista, fisioterapeuta — podem entrar como categorias específicas em spec futura).
- **Especialidade**: campo de texto com autocomplete contra valores já cadastrados no tenant (Q1 da Clarifications). Admin pode escolher sugestão existente ou digitar valor novo. Sem catálogo nacional — flexibilidade preserved.
- **Unicidade do conselho**: a tupla (tipo + número + UF) é única dentro de um tenant. Cross-tenant a unicidade não é enforçada (mesma conselho pode existir em clínicas diferentes — comum em médicos que atendem em múltiplas clínicas).
- **Step 4 desbloqueado após step 2**: regra explícita desta spec — clínica que cadastrou pelo menos um profissional pode começar a configurar agenda. Steps 3 (canal de mensageria) e 5 (base IA) ficam locked para specs futuras.
- **Out of scope explícito**: validação online em órgãos de conselho (CRM real existe?), foto de perfil do profissional, gestão de horários (já existe via outra rota), histórico granular de mudanças, permissões fine-grained por profissional, importação em massa (CSV), bulk actions, edição inline na tabela, abertura dos steps 3 e 5 do onboarding, integração com diretório nacional de profissionais.
- **i18n**: novas strings em português brasileiro, no arquivo de tradução já consumido pelo SPA.
- **Reusabilidade visual**: a UI segue os padrões já estabelecidos (tabela, modal de confirmação, formulário com validação inline, toast de sucesso/erro) — coerente com Pacientes, Receituários, Campanhas.
- **Identificador do profissional no payload do onboarding**: gravado para auditoria e possível inferência futura no wizard ou no Dashboard Home (não é usado por nenhuma outra feature ainda — gravação preventiva).
