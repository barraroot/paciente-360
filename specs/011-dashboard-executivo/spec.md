# Feature Specification: Dashboard Executivo (US-10.1)

**Feature Branch**: `011-dashboard-executivo`
**Created**: 2026-05-23
**Status**: Draft
**Input**: User description: "Dashboard Executivo — polish visual e funcional do `/panel/relatorios/executivo`. Foco analítico/histórico: 8 KPIs agregados em janelas (24h/7d/30d/90d) com sparklines de tendência, variação vs período anterior, top procedimentos, ocupação por profissional e exportação. Diferente do Dashboard Home (que é operacional)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Visão Analítica do Período (Priority: P1)

O administrador da clínica abre o Dashboard Executivo e vê uma tela visualmente organizada com os 8 indicadores mais relevantes do negócio (leads por canal, taxa de conversão, no-show, receita estimada, tempo de resposta, IA autônoma, ocupação, top procedimentos), cada um destacando o valor atual da janela escolhida, a tendência ao longo desse período e a variação versus o período anterior equivalente. A informação está disponível em segundos, sem precisar abrir cada módulo individualmente.

**Why this priority**: É o ponto de partida para tomada de decisão estratégica — sem esta tela, o admin precisa solicitar relatórios manuais ou abrir 8 módulos diferentes. P1 porque destrava todo o uso analítico do produto e materializa o trabalho de agregação horária feito pelo backend.

**Independent Test**: Logar como admin com `report.view`; abrir `/panel/relatorios/executivo`; verificar que os 8 KPIs aparecem com números formatados (% com 1 decimal, BRL para receita, s para tempo), sparklines visíveis e variação % comparativa exibida com seta colorida.

**Acceptance Scenarios**:

1. **Given** admin com permissão de relatórios em tenant com dados de pelo menos 30 dias, **When** acessa `/panel/relatorios/executivo`, **Then** vê 8 cards de KPI em grid responsivo com valor atual, sparkline de tendência do período corrente e variação % vs período anterior.
2. **Given** card de "Taxa de conversão" com tendência crescente, **When** o admin olha o card, **Then** vê o valor atual em destaque, sparkline com inclinação ascendente e seta verde apontando para cima com a variação numérica explícita ("↑ 12%").
3. **Given** card de "Taxa de no-show" (métrica onde menos é melhor), **When** o no-show diminuiu vs período anterior, **Then** a seta aponta para baixo e a cor é verde (polaridade invertida explícita).
4. **Given** KPI sem dados suficientes no período (ex.: tenant novo com 3 dias), **When** o card renderiza, **Then** exibe estado "Sem dados suficientes" em vez de zeros ou números errados.
5. **Given** sparkline do card de receita, **When** o usuário passa o mouse sobre um ponto do gráfico, **Then** vê um tooltip com o valor formatado em BRL e a data/hora correspondente.

---

### User Story 2 — Filtro de Período Persistente (Priority: P1)

O admin alterna rapidamente entre as quatro janelas analíticas disponíveis (Últimas 24h, 7 dias, 30 dias, 90 dias) usando um filtro fixo no cabeçalho da página. A escolha é mantida entre sessões — ao retornar ao dashboard no dia seguinte, a última janela usada é restaurada automaticamente.

**Why this priority**: A janela de análise muda conforme o cenário (24h para verificar evento de pico do dia anterior; 30 dias para revisão mensal; 90 dias para apresentação trimestral). Sem persistência, o admin perde tempo reselecionando a janela toda vez. P1 porque é o controle central que define o que todos os 8 cards mostram.

**Independent Test**: Logar como admin; abrir o dashboard; clicar em "30 dias"; confirmar que os valores e sparklines atualizam em menos de 2 segundos; fazer logout/login; reabrir o dashboard; verificar que "30 dias" continua selecionado.

**Acceptance Scenarios**:

1. **Given** dashboard aberto na janela default "7 dias", **When** o admin clica em "30 dias", **Then** todos os 8 cards atualizam (valores, sparklines, variação) em menos de 2 segundos e o tab "30 dias" fica visualmente destacado.
2. **Given** o admin escolheu "90 dias" e fez logout, **When** retorna em sessão posterior (mesmo navegador), **Then** o dashboard abre direto na janela "90 dias" sem precisar reselecionar.
3. **Given** filtro de período visível, **When** o admin pressiona Tab e depois as setas de direção, **Then** consegue navegar entre as 4 janelas pelo teclado com indicação de foco visível.
4. **Given** trocar de janela enquanto a request anterior ainda está em curso, **When** o admin clica em outra janela durante o loading, **Then** a request anterior é cancelada e só os dados da nova janela aparecem (sem flicker de dados intermediários).

---

### User Story 3 — Banner de Frescor dos Dados (Priority: P2)

Quando os dados pré-agregados estão atrasados por mais de duas horas (job de agregação horária falhou ou está atrasado), um banner informativo aparece no topo do dashboard avisando "Dados atualizados há X horas". O usuário sabe que está vendo informação um pouco antiga sem precisar adivinhar.

**Why this priority**: Decisões executivas baseadas em dados estale podem ser ruins. Sinalização clara protege a confiança do usuário no produto. P2 porque o caso "atraso > 2h" é raro em produção saudável; quando acontece, o aviso é essencial.

**Independent Test**: Em ambiente de teste, atrasar artificialmente o cron de agregação para mais de 2h; abrir o dashboard; verificar que o banner aparece com timestamp relativo correto ("há 3 horas"); rodar o cron novamente; recarregar; banner some.

**Acceptance Scenarios**:

1. **Given** última agregação ocorreu há 45 minutos, **When** o admin abre o dashboard, **Then** banner de frescor NÃO aparece (dentro da tolerância).
2. **Given** última agregação ocorreu há 3 horas, **When** o admin abre o dashboard, **Then** banner informativo aparece no topo com texto "Dados atualizados há 3 horas" e estilo neutro/atenção (não bloqueante).
3. **Given** banner visível, **When** o cron de agregação executa e o admin recarrega a página, **Then** o banner some.

---

### User Story 4 — Comparativos Específicos de Volume (Priority: P2)

Abaixo dos 8 KPIs principais, o admin vê duas seções complementares: os top 5 tipos de procedimento por contagem no período e o percentual de ocupação de cada profissional ativo. Essas seções respondem perguntas executivas concretas ("Em que estamos focando?" e "Quem está mais carregado?").

**Why this priority**: KPIs em cards mostram tendências; estas seções mostram distribuição e ranking. Sem elas, o admin precisa abrir relatórios separados para responder essas perguntas comuns. P2 porque os 8 KPIs já cobrem a "saúde geral" do negócio, e as duas seções enriquecem a análise.

**Independent Test**: Criar artificialmente em ambiente de teste um histórico com 10 tipos de procedimento e 5 profissionais com cargas diferentes; abrir dashboard na janela "30 dias"; verificar que aparecem os top 5 procedimentos com nome + contagem + % do total, e os 5 profissionais ordenados por ocupação decrescente com barras de progresso.

**Acceptance Scenarios**:

1. **Given** tenant com histórico de 10+ tipos de procedimento, **When** o dashboard renderiza, **Then** a seção "Top tipos de procedimento" mostra exatamente os 5 mais frequentes, cada um com nome, contagem absoluta e porcentagem do total.
2. **Given** seção de ocupação por profissional, **When** há 8 profissionais ativos, **Then** todos aparecem ordenados por % de ocupação em ordem decrescente, com barra visual de progresso (0–100%).
3. **Given** uma barra de ocupação acima de 90%, **When** o admin olha o item, **Then** percebe visualmente (cor de destaque + ícone opcional) que aquele profissional está com carga alta.
4. **Given** admin clica em um procedimento da lista, **When** o click é registrado, **Then** o usuário é levado ao relatório operacional pré-filtrado por aquele tipo (rota destino existe a partir da Fase 8).
5. **Given** profissional ativo sem nenhuma consulta no período, **When** a seção renderiza, **Then** o profissional NÃO aparece na lista (apenas profissionais com atividade são listados).

---

### User Story 5 — Exportação do Snapshot (Priority: P2)

O admin precisa compartilhar o dashboard com um stakeholder (sócio, diretor, contador) ou guardar um snapshot para histórico. Um botão "Exportar" no cabeçalho oferece a opção de gerar um PDF formatado da janela atual. O download começa automaticamente assim que o arquivo está pronto.

**Why this priority**: Compartilhar é caso real e recorrente — em reuniões mensais, apresentações ao conselho, auditorias internas. Sem export, o admin precisa fazer screenshots manuais. P2 porque o feature core é a visualização interativa; export é workflow auxiliar.

**Independent Test**: Abrir dashboard na janela "7 dias"; clicar em "Exportar" → "PDF"; verificar que o spinner aparece no botão; em até 10 segundos o navegador recebe o PDF para download; abrir o PDF e validar que contém os 8 KPIs, top procedimentos, ocupação e a indicação da janela.

**Acceptance Scenarios**:

1. **Given** dashboard renderizado, **When** o admin clica em "Exportar" e escolhe "PDF", **Then** o botão entra em estado de carregamento (spinner + desabilitado) e a janela do navegador inicia o download em menos de 10 segundos.
2. **Given** export em curso, **When** ocorre falha de servidor, **Then** o usuário vê um toast/banner discreto "Falha ao exportar" e o botão volta ao estado normal para nova tentativa.
3. **Given** menu de exportação aberto, **When** o admin vê a opção "Exportar CSV", **Then** essa opção está visível mas marcada como "em breve" e desabilitada (placeholder consciente — funcionalidade futura).
4. **Given** janela "30 dias" selecionada, **When** o admin exporta PDF, **Then** o conteúdo do PDF reflete EXATAMENTE essa janela (não a default).

---

### User Story 6 — Estados de Loading, Erro e Vazio (Priority: P3)

Ao acessar o dashboard pela primeira vez ou ao trocar de período, o admin vê esqueletos visuais (skeletons) imediatamente — sem flash de tela em branco. Se a API falhar, um banner não-bloqueante surge no topo com botão "Tentar novamente"; o último estado bom permanece visível enquanto isso. Se o tenant é novo e ainda não há dados suficientes, uma mensagem central explica "Ainda não temos dados suficientes" em vez de mostrar zeros confusos.

**Why this priority**: Polish percebido — sem skeletons o dashboard pisca; sem empty state inteligente, o admin pensa que o produto está quebrado. P3 porque os casos negativos são minoritários e os estados positivos cobrem 95% das sessões.

**Independent Test**: (a) Acessar dashboard com network throttling — ver skeletons aparecendo imediatamente; (b) bloquear temporariamente o endpoint (DevTools) — ver banner de erro com retry; (c) criar tenant novo (zero dados) — ver empty state amigável.

**Acceptance Scenarios**:

1. **Given** dashboard sendo carregado, **When** os dados ainda não chegaram, **Then** cada uma das 8 áreas de KPI exibe um skeleton com formato similar (placeholder do número e do sparkline) e as 2 seções inferiores também exibem placeholders.
2. **Given** uma falha de rede ao carregar, **When** a request termina em erro, **Then** o usuário vê um banner não-bloqueante no topo "Não foi possível atualizar" com botão "Tentar novamente"; o conteúdo anterior permanece se houver.
3. **Given** tenant novo sem nenhuma agregação produzida, **When** o admin abre o dashboard, **Then** vê uma mensagem central "Ainda não temos dados suficientes para gerar o dashboard. Volte em algumas horas." em vez de KPIs zerados.
4. **Given** banner de erro visível, **When** o admin clica em "Tentar novamente", **Then** a request é reexecutada e o banner some se a chamada tiver sucesso.

---

### Edge Cases

- **Janela "Últimas 24h"**: dados vêm de queries live (não agregados), então o banner de frescor NÃO se aplica nessa janela (dados são sempre frescos por definição).
- **Variação % indefinida**: se o período anterior equivalente não tem dados (tenant novo demais para comparar), a seta de variação é omitida e exibe-se traço "—" no lugar do número.
- **Sparkline com menos pontos que o esperado**: se a janela tem menos pontos disponíveis (ex.: 5 dias em vez de 7), o sparkline renderiza só os pontos existentes sem distorcer a escala.
- **Tooltip do sparkline em dispositivo touch**: em mobile/tablet, o tooltip é acionado por tap (não hover) e fecha ao tap fora.
- **Trocar período durante exportação**: o export deve refletir a janela ATIVA no momento do click; trocar de período no meio do export NÃO altera o PDF em geração — usa a janela do request inicial.
- **Permissão revogada durante sessão**: se o admin perde a permissão `report.view` enquanto o dashboard está aberto, a próxima chamada retorna 403; frontend deve redirecionar para `/panel` com banner explicativo.
- **Tenant com 1 único profissional**: seção de ocupação mostra apenas esse profissional (sem ranking vazio).
- **Procedimentos com mesmo nome em tenants diferentes**: irrelevante aqui — isolamento multi-tenant já garante separação.
- **Click em card durante loading**: clicks devem ser ignorados até carregamento terminar (cards desabilitados visualmente quando em estado de loading).

## Requirements *(mandatory)*

### Functional Requirements

**Cabeçalho e filtro de período**

- **FR-001**: O sistema MUST exibir um filtro de período fixo no cabeçalho da página com exatamente quatro opções: "Últimas 24h", "7 dias", "30 dias", "90 dias".
- **FR-002**: O sistema MUST destacar visualmente a opção de período atualmente selecionada.
- **FR-003**: A escolha de período MUST persistir por usuário+tenant entre sessões — ao reabrir o dashboard, a última escolha é restaurada.
- **FR-004**: A escolha de período padrão para um usuário sem preferência salva MUST ser "7 dias".
- **FR-005**: Trocar o período MUST disparar nova consulta ao backend e atualizar todos os KPIs e seções; requests anteriores em curso MUST ser canceladas para evitar dados intermediários visíveis.
- **FR-006**: O filtro de período MUST ser navegável pelo teclado (Tab + setas de direção), com indicador de foco visível e seleção ativa marcada para tecnologias assistivas.

**Banner de frescor (stale indicator)**

- **FR-007**: O sistema MUST exibir um banner informativo (não-bloqueante) quando o atraso da agregação for superior a 2 horas, indicando o tempo relativo desde a última atualização ("há X horas").
- **FR-008**: O banner de frescor MUST estar OCULTO para a janela "Últimas 24h", pois nessa janela os dados são consultados em tempo real.
- **FR-009**: O banner de frescor MUST desaparecer automaticamente assim que uma nova agregação dentro do limiar for detectada (recarregamento ou refresh manual).

**KPI cards (8 indicadores)**

- **FR-010**: O sistema MUST exibir 8 cards de KPI dispostos em grade responsiva: leads por canal, taxa de conversão, taxa de no-show, receita estimada, tempo de resposta inicial (p95), taxa de resolução autônoma da IA, ocupação por profissional, top tipos de procedimento.
- **FR-011**: Cada card MUST mostrar o valor atual em destaque tipográfico, formatado conforme o tipo da métrica: porcentagem com 1 casa decimal, moeda brasileira para receita, segundos com 1 casa decimal para tempo.
- **FR-012**: Cada card MUST exibir uma representação visual de tendência (sparkline) com 8 a 24 pontos conforme a janela selecionada, sem eixos numerados, com curva simples.
- **FR-013**: Cada card MUST exibir a variação percentual versus o período anterior equivalente, com seta visual (↑/↓), cor (verde/vermelho) e texto numérico explícito.
- **FR-014**: A polaridade da cor da variação MUST ser invertida para métricas onde valores menores são desejados (ex.: taxa de no-show, tempo de resposta) — diminuir é verde, aumentar é vermelho.
- **FR-015**: Se o período anterior equivalente não tem dados disponíveis para comparação, o card MUST omitir a seta e exibir "—" como variação.
- **FR-016**: Se uma métrica não tem dados suficientes na janela atual, o card MUST exibir estado "Sem dados suficientes" em vez de zeros.
- **FR-017**: Passar o cursor (ou tocar em mobile) sobre um ponto do sparkline MUST exibir um tooltip com o valor exato formatado e a data/hora do ponto.

**Seções complementares**

- **FR-018**: Abaixo dos KPIs, o sistema MUST exibir a seção "Top tipos de procedimento" listando os 5 procedimentos mais frequentes no período, cada um com nome, contagem absoluta e porcentagem do total.
- **FR-019**: Abaixo dos KPIs, o sistema MUST exibir a seção "Ocupação por profissional" listando todos os profissionais ATIVOS COM ATIVIDADE no período, ordenados por percentual de ocupação em ordem decrescente, cada um com barra de progresso visual de 0 a 100%.
- **FR-020**: Profissionais ativos sem nenhuma atividade no período MUST ser omitidos da seção de ocupação.
- **FR-021**: Barras de ocupação acima de 90% MUST ter destaque visual (cor/ícone) que comunique "carga alta".
- **FR-022**: Click em um procedimento na lista MUST levar o usuário ao relatório operacional pré-filtrado por aquele tipo (rota destino já existe).
- **FR-023**: Click em um profissional na lista de ocupação MUST levar à agenda daquele profissional.

**Exportação**

- **FR-024**: O cabeçalho da página MUST oferecer um botão "Exportar" que abre um menu com as opções "Exportar PDF" e "Exportar CSV".
- **FR-025**: A opção "Exportar PDF" MUST gerar um arquivo PDF formatado refletindo a janela atualmente selecionada, contendo os 8 KPIs, as 2 seções complementares e a identificação da janela e do tenant.
- **FR-026**: Durante a geração do PDF, o botão "Exportar" MUST exibir indicador de carregamento (spinner) e ficar desabilitado para impedir clicks múltiplos.
- **FR-027**: Em caso de falha na exportação, o sistema MUST exibir mensagem discreta de erro ("Falha ao exportar") e o botão MUST voltar ao estado normal para nova tentativa.
- **FR-028**: A opção "Exportar CSV" MUST ser visível mas marcada como "em breve" e desabilitada nesta versão.
- **FR-029**: Trocar de período após iniciar uma exportação NÃO MUST alterar o conteúdo do arquivo em geração — o PDF reflete a janela ativa no momento do click.

**Estados visuais**

- **FR-030**: Durante o carregamento inicial e durante troca de período, cada KPI card e cada seção complementar MUST exibir seu próprio esqueleto (skeleton), evitando flash de tela em branco.
- **FR-031**: Em caso de falha na API, o sistema MUST exibir um banner não-bloqueante com texto descritivo do erro e botão "Tentar novamente"; o último conteúdo bem-sucedido MUST permanecer visível se disponível.
- **FR-032**: Para tenants sem dados suficientes para qualquer KPI, o sistema MUST exibir uma mensagem central amigável explicando que dados ainda estão sendo coletados, em vez de uma grade de zeros.

**Comportamento**

- **FR-033**: O dashboard NÃO MUST executar atualização automática periódica — refresh é manual ou disparado por troca de período (diferentemente do Dashboard Home).
- **FR-034**: Permissão revogada durante sessão (perda de `report.view`) MUST levar a redirecionamento educado para a tela inicial do painel com aviso explicativo.

**Acessibilidade**

- **FR-035**: Cards de KPI MUST usar estrutura semântica apropriada e expor `aria-label` descritivo que comunique o valor + tendência sem depender do gráfico (ex.: "Taxa de conversão: 42,3%, tendência estável, alta de 5% vs período anterior").
- **FR-036**: Sparklines MUST ter `aria-label` descritivo de tendência geral em linguagem natural.
- **FR-037**: Barras de ocupação por profissional MUST usar estrutura semântica com `aria-valuenow`, `aria-valuemin`, `aria-valuemax` para tecnologias assistivas.
- **FR-038**: O filtro de período MUST seguir o padrão `tablist` com `aria-selected` e navegação por teclado.
- **FR-039**: TODA distinção comunicada por cor (variação positiva/negativa, carga alta) MUST ser também comunicada por ícone (↑/↓) e/ou texto explícito.

### Key Entities

Esta feature consome entidades e endpoint já existentes (Fase 8) sem criar novas tabelas. Resumo das fontes de dados usadas (todos com isolamento por tenant garantido pelo backend):

- **Agregação de Métricas (entidade pré-computada da Fase 8)**: 8 métricas pré-calculadas por janela; fonte primária para janelas ≥ 24h.
- **Janela "Últimas 24h"**: consultas live nas tabelas operacionais (Consulta, Conversa, Lead, etc.).
- **Tipo de Procedimento**: nome e contagem por período.
- **Profissional**: nome e percentual de ocupação calculado (slots ocupados ÷ slots disponíveis).
- **Janela analítica**: enum fechado `'24h' | '7d' | '30d' | '90d'`. Persistido no cliente como preferência do usuário+tenant.

Nenhuma entidade nova é criada por esta feature. Toda a lógica de agregação, cache e isolamento multi-tenant vive no backend já entregue.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Após login, o admin consegue identificar o estado analítico do negócio (8 KPIs + tendências) em **menos de 10 segundos** desde abrir o link do dashboard até interpretação completa.
- **SC-002**: A página renderiza o conteúdo visível em **menos de 1,2 segundos** em rede típica (3 Mbps, latência 80 ms).
- **SC-003**: Alternar entre as 4 janelas de período atualiza os dados em **menos de 2 segundos** cada.
- **SC-004**: Em **100% dos cenários de variação positiva/negativa**, a direção (verde vs vermelho) está correta de acordo com a natureza da métrica (incluindo polaridade invertida para no-show e tempo de resposta).
- **SC-005**: Em **100% dos cenários de teste de isolamento multi-tenant**, dados de tenant A nunca aparecem para usuários de tenant B no dashboard.
- **SC-006**: Em auditoria automatizada de acessibilidade (axe/Lighthouse) na rota do dashboard, **0 violações sérias ou críticas** são reportadas.
- **SC-007**: Exportar PDF da janela atual finaliza com download iniciado em **menos de 10 segundos** para janela "7 dias".
- **SC-008**: O usuário consegue compreender pelo `aria-label` de um card a métrica + tendência + variação SEM precisar ver o sparkline (validado por teste com leitor de tela em pelo menos 2 KPIs).
- **SC-009**: Em ambiente onde o cron de agregação está atrasado por mais de 2 horas, o banner de frescor aparece em **100% das aberturas** do dashboard até a próxima agregação dentro do limiar.
- **SC-010**: Em pesquisa de usabilidade informal com 3 administradores de clínica, **pelo menos 2 conseguem extrair uma decisão acionável** ("preciso aumentar a presença no canal X" ou "preciso reduzir o no-show") em **menos de 60 segundos** observando o dashboard.

## Assumptions

- **Backend**: endpoint principal do dashboard, agregações horárias, drill-down e exportação de PDF já foram entregues na Fase 8 (spec 008). Esta feature CONSOME esses contratos sem alterá-los.
- **App Shell**: a rota `/panel/relatorios/executivo` já vive dentro do shell entregue no spec 009 (sidebar + topbar visíveis).
- **Permissão de acesso**: usuários sem `report.view` não entram nesta rota (router guard já existente). A política de auth do endpoint backend cobre o restante.
- **Janela default**: "7 dias" é o equilíbrio entre frescor e volume de dados para uma visão semanal de clínica média.
- **Tolerância de atraso de agregação**: 2 horas é o limiar para considerar dados "estale" (já estabelecido na Fase 8 — gate R-8-5).
- **Polaridade das métricas**: definição de "menos é melhor" aplica-se a `taxa de no-show` e `tempo de resposta inicial`; demais métricas seguem polaridade direta (mais é melhor).
- **Variação comparativa**: o "período anterior equivalente" é definido pelo backend (ex.: 7d atual = dias -7 a 0; 7d anterior = dias -14 a -7). Esta spec não redefine isso.
- **Sparkline simples**: representação minimalista sem eixos, sem legendas — apenas a curva. Tooltip ao hover/tap revela detalhes pontuais.
- **CSV export**: deferred deliberadamente — botão presente mas funcionalidade fica para spec futura quando backend tiver endpoint correspondente.
- **Sem auto-refresh**: dashboard analítico não é live; agregação é horária; refresh manual via troca de período ou recarga do navegador é suficiente.
- **i18n**: novas strings desta tela vão para o arquivo de tradução já em uso pelo SPA (locale principal pt-BR).
- **Reusabilidade de UI**: padrões visuais (cores de tendência, skeleton, banner de erro, botão refresh) devem ser visualmente consistentes com o Dashboard Home (spec 010) para coerência do produto.
- **Out of scope explícito** (não implementar nesta feature): novas métricas além das 8 existentes, drill-down interno ao dashboard, comparativos customizados entre 2 períodos arbitrários, CSV export real, filtros adicionais por profissional/tipo (escopo do relatório Operacional), auto-refresh, alertas baseados em KPIs, dark mode, personalização de cards (esconder/reordenar), animações elaboradas além de transições suaves padrão.
