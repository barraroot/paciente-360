# Feature Specification: Dashboard Home (US-1.5)

**Feature Branch**: `010-dashboard-home`
**Created**: 2026-05-23
**Status**: Draft
**Input**: User description: "Dashboard Home — conteúdo real da página inicial do painel autenticado em `/panel`. Foco operacional (o que precisa de atenção AGORA): KPIs do dia, próximas consultas, alertas, atividade recente. Diferente do Dashboard Executivo (que é analítico/histórico)."

## Clarifications

### Session 2026-05-23

- Q: Para um usuário com ambas as roles `admin-clinica` E `medico`, "Minha visão" escopa como profissional ou força a usar "Visão da clínica"? → A: Sempre escopa como profissional (filtra por consultas próprias, conversas/pacientes atribuídos), independente de outras roles. O usuário misto continua tendo o toggle disponível para alternar para "Visão da clínica" quando quiser supervisionar. A semântica de "minha" prevalece sobre o nível hierárquico da role.
- Q: A janela de 6 horas das "próximas consultas" é fixa ou deve ser configurável (por tenant ou por usuário)? → A: Fixa em 6 horas no MVP. Decisão deliberada de manter simplicidade — configurabilidade per-tenant ou per-usuário pode entrar em spec futura sem breaking change (default 6h fica como fallback). Janela cobre a maioria dos turnos clínicos sem inflar a lista.
- Q: O alerta "paciente em funil sem touch há 48h" considera quais estágios do funil? → A: Apenas estágios ativos não-terminais: `lead`, `qualificando`, `interessado`, `agendamento`. Excluídos: `agendado` (já tem ação futura prevista — a própria consulta), `concluído` e `perdido` (terminais, nada a fazer). Alerta foca em pacientes em movimento que pararam de receber atenção comercial.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — KPIs Operacionais do Dia (Priority: P1)

Ao chegar no painel (logo após login ou ao clicar "Dashboard" na sidebar), o usuário vê quatro cards-resumo no topo da tela com os números que mais importam para o dia-a-dia da clínica: quantas consultas tem hoje, quantas conversas precisam de atenção, quantos leads novos surgiram na semana, quantas receitas estão prestes a vencer. Cada card é clicável e leva diretamente para a tela detalhada do recurso filtrado.

**Why this priority**: É a primeira informação que o usuário recebe ao começar o trabalho. Sem esses números visíveis em 1 segundo, ele precisa abrir 4 telas diferentes para construir mentalmente o mesmo panorama. É o "tela inicial" no sentido literal — sem isto, `/panel` continua sendo placeholder.

**Independent Test**: Logar como qualquer usuário ativo de um tenant que tenha dados; verificar que os quatro cards renderizam com números corretos consistentes com as telas detalhadas (consultas do dia, inbox aberto, funil de leads, lista de receitas); clicar em cada card e validar que vai para a tela correspondente com filtro pré-aplicado.

**Acceptance Scenarios**:

1. **Given** usuário autenticado em um tenant com 12 consultas hoje (8 confirmadas, 4 pendentes), **When** acessa `/panel`, **Then** o card "Consultas hoje" exibe "12" e abaixo "8 confirmadas, 4 pendentes".
2. **Given** os quatro cards visíveis, **When** o usuário clica em "Consultas hoje", **Then** é redirecionado para a agenda com filtro de data igual a hoje.
3. **Given** card "Receitas vencendo (30d)" mostra "3", **When** o usuário clica, **Then** vai para a lista de receituários com filtro pré-aplicado de vencimento ≤ 30 dias.
4. **Given** tenant sem nenhuma consulta hoje, **When** o painel renderiza, **Then** o card "Consultas hoje" exibe "0" sem causar erro, e o sub-info exibe "Sem consultas hoje" em vez de "X confirmadas, Y pendentes".

---

### User Story 2 — Próximas Consultas em Tempo Útil (Priority: P1)

O usuário vê, em uma lista compacta, suas próximas até 5 consultas previstas para as próximas 6 horas (escopadas ao próprio profissional se o usuário é médico, ou todas as do tenant se é admin). Cada item exibe horário, nome do paciente, tipo de consulta, profissional e status (confirmada/pendente). Click no item abre a agenda no slot correspondente.

**Why this priority**: Diferente do KPI agregado "consultas hoje", esta lista mostra o que vem A SEGUIR — informação acionável para o profissional saber se precisa se preparar ou conferir um caso. Sem essa lista, o profissional precisa abrir a agenda completa só para conferir o próximo paciente.

**Independent Test**: Criar 3 consultas com horários nas próximas 2, 4 e 7 horas; logar como o profissional dono dessas consultas; verificar que as 2 primeiras aparecem na lista (a de 7h fica fora do recorte de 6h) com horários e nomes corretos; clicar em uma e validar que a agenda abre no dia/slot certo.

**Acceptance Scenarios**:

1. **Given** profissional com 3 consultas nas próximas 6 horas e 2 em mais de 6 horas, **When** acessa `/panel`, **Then** a seção "Próximas consultas" lista apenas as 3 dentro do recorte, ordenadas cronologicamente.
2. **Given** lista de próximas consultas com 5 itens já cheia, **When** existe uma 6ª consulta dentro de 6h, **Then** apenas as 5 mais próximas no tempo são exibidas; o usuário vê o botão "Ver agenda completa" para acesso ao restante.
3. **Given** usuário com nenhuma consulta nas próximas 6h, **When** a seção renderiza, **Then** exibe a mensagem "Sem consultas nas próximas 6 horas" com um ícone simpático e o botão "Ver agenda completa".
4. **Given** uma consulta na lista tem status "pendente" (não confirmada), **When** o usuário olha o item, **Then** vê um badge visual de status que distingue de "confirmada".

---

### User Story 3 — Alertas de Atenção Acionáveis (Priority: P1)

O usuário vê, em ordem de urgência, até 5 itens que requerem ação humana imediata: conversas que a IA escalou para humano há mais de 10 minutos, receitas vencendo nos próximos 7 dias, pacientes em estágio de funil sem follow-up há mais de 48h, confirmações de consulta pendentes, e (se tem permissão) webhooks que falharam nas últimas 24h. Cada alerta tem um ícone de severidade, descrição curta e link direto para a ação.

**Why this priority**: Esta é a seção que materializa o valor de "o que precisa da minha atenção AGORA". Sem ela, o usuário descobre problemas só ao abrir cada módulo individualmente. P1 porque é o coração operacional do dashboard — sem isto, o `/panel` é só uma vitrine de números.

**Independent Test**: Criar artificialmente 1 cenário de cada tipo (conversa escalada, receita expirando em 5 dias, paciente parado no funil, confirmação pendente, webhook em DLQ); logar como `admin-clinica`; verificar que os 5 alertas aparecem na lista, ordenados por severidade (danger > warn > info), cada um com link de ação que funciona.

**Acceptance Scenarios**:

1. **Given** uma conversa com IA escalada há 15 minutos, **When** o usuário acessa o painel, **Then** vê um alerta com severidade "atenção" + descrição "Conversa aguardando atendimento humano há 15 minutos" + link para a conversa específica.
2. **Given** uma receita vence em 3 dias (controlada), **When** a seção de alertas renderiza, **Then** exibe alerta de severidade "alta" + link para a receita.
3. **Given** existem 8 alertas potenciais para o tenant, **When** a seção renderiza, **Then** mostra apenas os 5 mais urgentes (severidade > recência), e exibe um indicador "Ver todos os alertas" para acesso ao restante (versão 1 pode apontar para uma central futura ou simplesmente para cada módulo relevante).
4. **Given** nenhum alerta ativo, **When** a seção renderiza, **Then** exibe a mensagem "Tudo em dia" com ícone positivo.
5. **Given** usuário sem permissão para gerenciar webhooks, **When** existem webhooks em DLQ, **Then** esses alertas NÃO aparecem na lista dele.
6. **Given** um paciente parado há 60h no estágio `lead`, outro parado há 60h no estágio `agendado` e outro parado há 60h no estágio `perdido`, **When** a seção de alertas renderiza, **Then** apenas o paciente em `lead` gera alerta — os outros dois são filtrados porque `agendado`/`perdido` não são estágios ativos não-terminais.

---

### User Story 4 — Timeline de Atividade Recente (Priority: P2)

O usuário vê, em ordem cronológica reversa, os últimos eventos do tenant nas últimas 24 horas: quem fez o quê e quando ("Maria criou paciente João Silva há 12 minutos", "Dr. Carlos marcou consulta como realizada há 1 hora"). Limitado a 8 eventos. Eventos sistêmicos (sem ator humano) são filtrados. O nome do recurso é clicável e leva à página do recurso.

**Why this priority**: Suporte de consciência de equipe — útil para admins acompanharem o trabalho da clínica sem precisar abrir audit logs. Não bloqueia trabalho mas adiciona contexto. P2 porque é informativo, não acionável.

**Independent Test**: Logar como `admin-clinica`; provocar 3 eventos auditáveis (criar paciente, atualizar consulta, deletar tag); verificar que aparecem na timeline em ordem cronológica reversa com timestamp relativo (ex.: "há 5 minutos"); clicar no nome do paciente criado e validar que vai para a página do paciente.

**Acceptance Scenarios**:

1. **Given** 8 ou mais eventos auditáveis nas últimas 24h, **When** a timeline renderiza, **Then** mostra os 8 mais recentes em ordem cronológica reversa.
2. **Given** um evento de 30 minutos atrás registrado por Maria, criando paciente João, **When** o usuário olha a entry, **Then** vê "Maria criou paciente João Silva" e "há 30 minutos" como timestamp relativo.
3. **Given** existem eventos do sistema (sem ator humano) entre os recentes, **When** a timeline renderiza, **Then** esses eventos NÃO são exibidos — apenas os com ator humano identificado.
4. **Given** nenhum evento humano nas últimas 24h, **When** a seção renderiza, **Then** exibe "Nenhuma atividade nas últimas 24 horas" como empty state.
5. **Given** uma entry "criou paciente João Silva", **When** o usuário clica no nome do paciente, **Then** é levado à página do paciente correspondente.

---

### User Story 5 — Toggle de Escopo (Minha Visão / Visão da Clínica) (Priority: P2)

Usuários com permissão de administração da clínica conseguem alternar a visão do dashboard entre "Minha visão" (escopada ao próprio usuário onde aplicável — ex.: só minhas consultas, só conversas atribuídas a mim) e "Visão da clínica" (agregada do tenant inteiro). A escolha é persistida por usuário+tenant. Usuários sem permissão de administração veem implicitamente apenas "Minha visão".

**Why this priority**: Admins precisam alternar entre operar (como qualquer outro profissional) e supervisionar (panorama da clínica). Sem o toggle, o admin não tem como ver o que está acontecendo nos demais profissionais sem abrir múltiplas telas. P2 porque o caso de uso primário no MVP é "minha visão" — visão da clínica é um nice-to-have que entra logo em seguida.

**Independent Test**: Logar como `admin-clinica` em tenant com 2 médicos cada com consultas/conversas diferentes; alternar o toggle para "Visão da clínica" e verificar que os números do KPI mudam para refletir o tenant inteiro; recarregar a página e validar que a escolha persiste; logar como `medico` (não admin) e validar que o toggle não está visível.

**Acceptance Scenarios**:

1. **Given** admin de clínica logado em tenant com 3 médicos, cada um com 5 consultas hoje (total 15), **When** seleciona "Visão da clínica" no toggle, **Then** o KPI "Consultas hoje" mostra 15.
2. **Given** o mesmo admin alterna para "Minha visão", **When** ele não tem consultas próprias hoje, **Then** o KPI exibe "0" e a lista de próximas consultas mostra empty state.
3. **Given** profissional sem role de admin, **When** acessa o painel, **Then** o toggle não está visível na tela.
4. **Given** admin escolheu "Visão da clínica", **When** faz logout e login na mesma máquina (mesmo browser), **Then** a escolha "Visão da clínica" é restaurada automaticamente.
5. **Given** admin com "Visão da clínica" salva, **When** acessa o painel pelo navegador de outra máquina, **Then** abre com o default "Minha visão" (não há sincronização cross-device — preferência é local).
6. **Given** usuário com **ambas** as roles `admin-clinica` E `medico` (ex.: dono da clínica que também atende), **When** seleciona "Minha visão", **Then** os dados são escopados como se fosse médico (consultas próprias, conversas atribuídas, pacientes responsáveis) — a role administrativa não infla o escopo. Para ver o tenant inteiro, ele alterna para "Visão da clínica".

---

### User Story 6 — Atualização Manual e Automática (Priority: P3)

O usuário pode forçar uma atualização do dashboard a qualquer momento via botão "Atualizar" no cabeçalho. Adicionalmente, enquanto a aba está visível em primeiro plano, o dashboard atualiza-se automaticamente a cada 2 minutos. Quando a aba fica em segundo plano (oculta), o auto-refresh é pausado para não desperdiçar requests.

**Why this priority**: Qualidade percebida de "informação fresca" sem precisar reload manual. Auto-refresh pausado em background economiza recursos e respeita o usuário. P3 porque o usuário pode sempre dar refresh no browser; é polish.

**Independent Test**: Carregar `/panel`; aguardar 2 minutos sem interagir; observar que a rede mostra uma nova chamada à API (DevTools); mudar para outra aba e voltar após 5 minutos; observar que o painel chama a API uma vez ao retornar ao foco; clicar no botão "Atualizar"; observar uma chamada imediata + spinner visual no botão durante a operação.

**Acceptance Scenarios**:

1. **Given** painel carregado há 2 minutos com a aba em primeiro plano, **When** os 2 minutos se completam, **Then** o sistema dispara uma nova consulta dos dados e atualiza a tela sem intervenção do usuário.
2. **Given** painel aberto em uma aba que perdeu o foco há 10 minutos, **When** a aba não está visível, **Then** nenhuma consulta automática é feita.
3. **Given** aba retornando ao foco após 10 minutos sem refresh, **When** o evento de visibilidade ocorre, **Then** uma atualização imediata é disparada para sincronizar dados.
4. **Given** usuário clica no botão "Atualizar", **When** a operação está em curso, **Then** o botão exibe spinner e desabilita-se temporariamente para impedir clicks múltiplos; quando termina, volta ao estado normal.

---

### Edge Cases

- **Tenant recém-criado sem nenhum dado**: cada uma das 4 seções (KPIs, próximas, alertas, atividade) exibe seu empty state correto; nenhuma seção quebra com lista vazia.
- **Resposta lenta da API (> 3s)**: skeletons das seções continuam visíveis; após 10s, exibir banner não-bloqueante "Demorando mais que o normal..." sem cancelar a request.
- **Falha total da API**: banner de erro no topo do painel "Não foi possível atualizar" + botão "Tentar novamente"; seções mantêm último estado bem-sucedido se houver, ou skeletons com texto "Não disponível".
- **Auto-refresh durante navegação do usuário**: se o usuário está com um popover/tooltip aberto, o refresh não pode tirar foco ou fechar o popover (apenas reescrever conteúdo no DOM).
- **Toggle de escopo enquanto refresh em andamento**: nova requisição cancela a anterior; usuário não vê dados misturados de escopos diferentes.
- **Usuário muda de tenant** (raro mas válido): cache local é invalidado; KPIs e listas re-buscam para o novo tenant.
- **Resposta com `tenant_scope_applied` diferente da escolha local**: significa que o usuário perdeu permissão de admin desde o último carregamento — sistema atualiza o toggle e mostra apenas "Minha visão".
- **Quantidade enorme de dados no tenant** (10k consultas hoje): contagens são feitas via query agregada; lista de próximas/alertas/atividade é sempre limitada (5/5/8); performance não degrada.
- **PII em atividade recente**: descrições humanizadas exibem nome do paciente (já visível em telas individuais), mas NUNCA CPF, telefone completo, email completo, ou conteúdo clínico.

## Requirements *(mandatory)*

### Functional Requirements

**Cards de KPI**

- **FR-001**: O painel MUST exibir quatro cards de KPI no topo: Consultas hoje, Conversas pendentes, Leads novos (7d), Receitas vencendo (30d).
- **FR-002**: Cada card MUST exibir o número total destacado, uma label descritiva, e uma linha de sub-informação contextual (ex.: "8 confirmadas, 4 pendentes").
- **FR-003**: Cada card MUST ser clicável e levar à tela correspondente com filtro pré-aplicado.
- **FR-004**: Quando a contagem de um card é zero, a sub-informação MUST refletir o estado vazio de forma legível (ex.: "Sem consultas hoje") em vez de "0 confirmadas, 0 pendentes".

**Próximas consultas (recorte 6 horas)**

- **FR-005**: A seção MUST listar até 5 consultas previstas para as próximas 6 horas (janela fixa neste MVP — configurabilidade por tenant ou por usuário fica para spec futura), em ordem cronológica crescente.
- **FR-006**: Cada item MUST mostrar horário (HH:mm), nome do paciente (truncado se longo), tipo de consulta, profissional responsável e badge visual do status (confirmada ou pendente).
- **FR-007**: Click em um item MUST levar à agenda no dia da consulta com posicionamento visual no slot correspondente.
- **FR-008**: A seção MUST oferecer atalho "Ver agenda completa" que leva à tela principal de agenda.
- **FR-009**: Sem consultas no recorte, a seção MUST exibir mensagem amistosa de empty state e manter visível o atalho para a agenda completa.

**Alertas de atenção**

- **FR-010**: A seção MUST exibir até 5 itens que requerem ação humana imediata, ordenados por urgência (severidade primeiro, recência depois).
- **FR-011**: Os tipos de alertas considerados MUST incluir: conversas escaladas há mais de 10 minutos, receitas vencendo em até 7 dias, pacientes nos estágios ativos não-terminais do funil (`lead`, `qualificando`, `interessado`, `agendamento`) sem touch há mais de 48 horas (estágios `agendado`, `concluído` e `perdido` MUST ser excluídos), confirmações de consulta pendentes (ação manual), webhooks em fila morta nas últimas 24 horas.
- **FR-012**: Cada alerta MUST ter ícone de severidade (visualmente distinto entre danger, atenção e info), título curto, descrição complementar e link de ação para o recurso ou tela correspondente.
- **FR-013**: Alertas relacionados a recursos para os quais o usuário não tem permissão MUST ser omitidos automaticamente da sua visão (ex.: webhooks em DLQ só aparecem para quem pode gerenciar integrações).
- **FR-014**: Sem nenhum alerta ativo, a seção MUST exibir empty state positivo (ex.: "Tudo em dia").

**Timeline de atividade recente**

- **FR-015**: A seção MUST listar até 8 eventos auditáveis ocorridos nas últimas 24 horas, em ordem cronológica reversa.
- **FR-016**: Apenas eventos com ator humano identificado MUST ser exibidos; eventos puramente sistêmicos (sem ator) MUST ser filtrados.
- **FR-017**: Cada entry MUST conter avatar ou iniciais do ator, frase humanizada da ação ("X fez Y a Z"), e timestamp relativo legível.
- **FR-018**: Os nomes de recursos mencionados na descrição (ex.: nome do paciente, número da receita) MUST ser links que levam à página do recurso correspondente quando aplicável.
- **FR-019**: A seção MUST NÃO exibir CPF, telefone completo, email completo ou conteúdo clínico em nenhuma descrição.
- **FR-020**: Sem eventos no período, a seção MUST exibir empty state.

**Toggle de escopo (Minha visão / Visão da clínica)**

- **FR-021**: Um controle de escopo MUST ser visível no cabeçalho da página apenas para usuários com permissão de administração da clínica.
- **FR-022**: Quando "Minha visão" está selecionada, as contagens e listas MUST ser escopadas ao usuário corrente onde a noção de "minha" se aplica (consultas do profissional, conversas atribuídas, pacientes em que o usuário é responsável). Esta regra MUST valer também para usuários com role dupla `admin-clinica + medico` — "Minha visão" sempre escopa como profissional, independente da role administrativa adicional. O usuário misto continua com o toggle disponível para alternar para "Visão da clínica" quando quiser.
- **FR-023**: Quando "Visão da clínica" está selecionada, as contagens e listas MUST cobrir o tenant inteiro, sem filtro por usuário.
- **FR-024**: A escolha entre "Minha visão" e "Visão da clínica" MUST persistir por usuário e tenant entre sessões, recuperando o valor salvo no próximo acesso.
- **FR-025**: Usuários sem permissão de administração MUST nunca ver o toggle, e o escopo aplicado MUST ser sempre "Minha visão" (implícito).

**Atualização manual e automática**

- **FR-026**: O cabeçalho do painel MUST oferecer um botão "Atualizar" que dispara uma nova consulta de todos os dados ao ser clicado.
- **FR-027**: Enquanto a aba está em primeiro plano (visível ao usuário), o painel MUST disparar uma atualização automática a cada 2 minutos.
- **FR-028**: Quando a aba está em segundo plano (oculta), o auto-refresh MUST ser pausado; ao retornar ao foco após mais de 2 minutos, MUST disparar uma atualização imediata.
- **FR-029**: Durante uma atualização em curso, o botão "Atualizar" MUST exibir indicador visual de carregamento e estar desabilitado para clicks múltiplos.

**Performance e cache**

- **FR-030**: O endpoint que serve os dados do painel MUST responder em tempo p95 inferior a 500 ms em condições normais de carga.
- **FR-031**: O endpoint MUST aplicar cache de curta duração (30 segundos por padrão) escopado por usuário, tenant e escopo selecionado; configuração de TTL MUST ser ajustável por ambiente.
- **FR-032**: As contagens de KPI MUST usar queries agregadas no banco — nunca contar registros materializados em memória.
- **FR-033**: As consultas que retornam listas (próximas consultas, alertas, atividade) MUST aplicar eager loading rigoroso de relacionamentos necessários para a renderização — nenhuma query adicional por item após o batch inicial.

**Isolamento multi-tenant**

- **FR-034**: Todas as consultas executadas pelo endpoint MUST estar escopadas ao tenant do usuário corrente.
- **FR-035**: Não MUST haver caminho de código que retorne dados de outro tenant, mesmo na presença de bugs de input — escopo de tenant é gate de design.

**Estados visuais**

- **FR-036**: Cada uma das quatro seções (KPIs, próximas consultas, alertas, atividade) MUST exibir seu próprio skeleton de loading durante a primeira carga.
- **FR-037**: Em caso de falha total da API, o painel MUST exibir banner não-bloqueante com mensagem de erro e botão de retry; seções MUST manter último estado bem-sucedido se disponível.
- **FR-038**: Cada seção MUST ter seu próprio empty state textual e visualmente coerente com o tom do produto.

**Acessibilidade**

- **FR-039**: Cards de KPI MUST ser links com atributo de rótulo acessível descritivo incluindo o número e o detalhamento (ex.: "Consultas hoje, 12 total, 8 confirmadas").
- **FR-040**: A lista de próximas consultas MUST usar estrutura semântica de lista.
- **FR-041**: A timeline de atividade MUST usar estrutura semântica de lista ordenada para refletir cronologia.
- **FR-042**: O botão "Atualizar" MUST refletir seu estado de carregamento através de atributo acessível padrão (ex.: ocupado/busy) que tecnologias assistivas reconheçam.
- **FR-043**: Toda interação visual de severidade (ícones colorido em alertas, badges de status) MUST ser acompanhada de texto que comunique a mesma informação sem depender exclusivamente de cor.

### Key Entities

Esta feature consome entidades existentes sem criar novas tabelas. Resumo de uso (todos com isolamento por tenant já existente):

- **Consulta agendada (Appointment)**: filtros por data, status, profissional. Usado em KPI "Consultas hoje" e "Próximas consultas".
- **Conversa de inbox (Conversation)**: filtros por status (aberta), atribuição (sem dono ou para o usuário), estado de IA. Usado em KPI "Conversas pendentes" e em alertas de escalonamento.
- **Paciente (Paciente)**: filtros por estágio de funil, data de criação, data do último contato. Usado em KPI "Leads novos (7d)" e em alertas de inatividade no funil.
- **Receita (Prescription)**: filtros por status e proximidade de vencimento. Usado em KPI "Receitas vencendo (30d)" e em alertas de receitas críticas (7d).
- **Disparo de confirmação (ConfirmationDispatch)**: filtro por status pendente. Usado em alertas de confirmação manual.
- **Webhook em fila morta**: filtro por janela de 24h. Usado em alertas para administradores de integração.
- **Log de auditoria (AuditLog)**: filtro por janela de 24h e presença de ator humano. Usado em timeline de atividade.

Nenhuma nova entidade persistida é criada por esta feature. A preferência de escopo do usuário ("Minha visão" / "Visão da clínica") é armazenada como preferência local do navegador, escopada por usuário+tenant.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Após login, o usuário consegue identificar o estado operacional do dia (4 KPIs + alertas críticos) em **menos de 5 segundos** de tempo total na tela.
- **SC-002**: A página `/panel` carrega o conteúdo visível em **menos de 1 segundo** em condições típicas de rede (3 Mbps, latência 80 ms).
- **SC-003**: O endpoint de dados do painel responde com tempo p95 **inferior a 500 ms** em condições de carga normal.
- **SC-004**: Cada click em um card de KPI ou item de lista leva o usuário à tela correspondente filtrada em **no máximo 1 navegação** (sem etapas intermediárias).
- **SC-005**: Um administrador alternando o toggle "Minha visão" ↔ "Visão da clínica" enxerga os dados atualizados em **menos de 1 segundo** após a escolha.
- **SC-006**: Em isolamento multi-tenant, **100% dos cenários de teste** confirmam que dados de tenant A nunca aparecem para usuários de tenant B no endpoint do painel.
- **SC-007**: Em auditoria de acessibilidade automatizada (ferramentas tipo axe/Lighthouse) na rota `/panel`, **0 violações sérias ou críticas** são reportadas.
- **SC-008**: O número de chamadas à API por seção é **exatamente 1** para a primeira carga (todos os dados vêm em um único request consolidado).
- **SC-009**: Auto-refresh em background reduz a **zero** o número de chamadas enquanto a aba está oculta.
- **SC-010**: 90% dos usuários conseguem identificar e clicar no item certo para resolver um alerta de atenção em **menos de 15 segundos** após avistar o alerta (teste de usabilidade).

## Assumptions

- **Auth e tenant**: O usuário já está autenticado e o tenant está resolvido pelo subdomínio antes desta tela ser acessada (infraestrutura entregue nas fases 1 e 4).
- **App Shell**: A página `/panel` está envolvida pelo shell entregue no spec 009 — sidebar e topbar permanecem visíveis durante o uso.
- **Definição de "admin de clínica"**: o controle de toggle aparece para usuários com a permissão administrativa de tenant; perfis sem essa permissão veem implicitamente apenas a "Minha visão".
- **Definição de "minha visão"**: aplica filtro pelo usuário corrente onde o conceito é semanticamente válido — consultas (profissional = eu), conversas (atribuída a mim), pacientes (responsável = eu), receitas (emissor = eu). Leads novos no funil de 7d e atividade recente exibem dados do tenant, mas só os onde o usuário tem visibilidade efetiva (passam pelas mesmas policies das telas individuais). Regra vale também para usuários com role dupla `admin-clinica + medico` (Q1 da seção Clarifications).
- **Dados disponíveis**: as entidades (Appointment, Conversation, Paciente, Prescription, ConfirmationDispatch, WebhookDelivery, AuditLog) já existem e têm os campos necessários — esta feature consome, não modela.
- **Auto-refresh interval**: 2 minutos é o equilíbrio entre frescor de informação e custo de servidor. Pode ser ajustável em configuração de ambiente se necessário no futuro.
- **Cache TTL**: 30 segundos é o equilíbrio entre frescor percebido e redução de carga no banco. Configurável por ambiente.
- **Localização do toggle**: preferência salva no navegador (não no servidor) — escolhas não sincronizam entre máquinas, mas são restauradas após logout/login na mesma máquina.
- **Mockup visual**: tipografia, paleta e espaçamento seguem o mockup do Dashboard Executivo já presente no projeto, adaptados ao conteúdo operacional do Home (que é diferente do conteúdo analítico do Executivo).
- **Idioma**: pt-BR único conforme infraestrutura existente; novas strings em arquivo de tradução próprio.
- **Out of scope explícito** (não implementar nesta feature): conteúdo do Dashboard Executivo (spec 011), customização de cards (arrastar/esconder), notificações em tempo real via WebSocket, filtros adicionais por profissional ou período, exportação como PDF/imagem, comparativos período-a-período, gráficos de tendência e sparklines, integração com central de notificações global.
