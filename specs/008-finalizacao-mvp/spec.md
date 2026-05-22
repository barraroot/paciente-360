# Feature Specification: Finalização do MVP (Fase 8 — Épicos 9, 10, 11, 12 e 13)

**Feature Branch**: `008-finalizacao-mvp`
**Created**: 2026-05-21
**Status**: Clarified — 29/29 NEEDS CLARIFICATION resolvidos com defaults recomendados em 2026-05-21; pronto para `/speckit-plan`
**Input**: User description — Fase final do MVP. Cinco módulos distintos entregues em uma única feature: Campanhas e Reativação (Épico 9), Relatórios e Dashboard (Épico 10), Integrações/Webhooks/API Pública (Épico 11), Painel Super Admin (Épico 12) e Privacidade/Segurança/LGPD (Épico 13). Cobertura completa de RF-054 a RF-064 + RNF-006 a RNF-012.

---

## Clarifications

> Os 29 pontos abertos foram resolvidos em lote com **defaults recomendados** baseados em (i) princípios da constituição vigente (v1.4.0), (ii) padrões estabelecidos pelas Fases 0–7 (especialmente Fase 4 Bearer, Fase 5 PARTIAL UNIQUE / cron `withoutOverlapping`, Fase 7 `ContainsNoClinicalData` marker + idempotency dual-layer Redis+DB), (iii) menor exposição/menor superfície regulatória, (iv) escopo MVP. Qualquer item pode ser reaberto em sessão futura se a operação real revelar gap.

### Sessão 2026-05-21 — Defaults aplicados

#### Módulo 1 — Campanhas

- **Q1 (segmentação "inativo")** → **A** — "Inativo há N meses" é calculado por **última consulta com status `realizada`** (vinda do evento `ConsultaRealizada` da Fase 5). Última mensagem recebida e última interação NÃO contam — apenas presença clínica confirmada. *Justificativa*: o objetivo de reativação é trazer paciente que parou de ser atendido; mensagens trocadas (mesmo só "obrigado") superestimam engajamento real. Métrica de eficácia ("reativação efetiva") fica auditável: input = `last_realized_consultation < D-N`, output = nova `ConsultaRealizada` em ≤ 60 dias.

- **Q2 (limite de envio diário)** → **C** — Limite definido pelo **plano de assinatura** (default sugerido: básico 200/dia, pro 1000/dia, enterprise 5000/dia — números fechados no `/speckit-plan` da Fase 0 do tenant). Admin Clínica pode **reduzir** o limite via configuração, mas não aumentar acima do plano. *Justificativa*: protege quality rating do número WhatsApp + alinha a monetização (volume é vetor cobrável); proteção dual contra "noisy neighbor" (Princípio II).

- **Q3 (campanha multi-canal)** → **A canal único por campanha** — Cada campanha tem exatamente UM canal alvo (WhatsApp OU Instagram OU SMS futuro). Para falar com base completa em 2 canais, Admin Clínica cria 2 campanhas. *Justificativa*: deduplicação cross-canal + métricas split + lógica de fallback aumentam superfície do MVP sem ROI claro. Modelo simples (1:1) cobre 95% dos casos reais (clínicas usam WhatsApp como canal primário).

- **Q4 (aprovação interna de campanha)** → **A sem etapa de aprovação** — Admin Clínica cria e dispara em fluxo único; sem revisor intermediário. Pré-visualização obrigatória antes do disparo (AC-9.2.4) é a "última checagem" embutida no fluxo. *Justificativa*: workflow approval adiciona estado + papel novo (revisor) + UX de filas — fora do tempo do MVP. Pode ser introduzido como feature opcional pós-MVP sem breaking change.

- **Q5 (re-envio para quem não respondeu)** → **A disparo único no MVP** — Campanha tem 1 step. Nurturing multi-step (D+3 / D+7 / D+14) fica para fase futura. *Justificativa*: dropoff entre re-envios degrada quality rating do número e aumenta complexidade de orquestração; 1 envio resolve 80% da reativação.

- **Q6 (atualização do relatório de campanha)** → **B atualização ao final do batch + polling cliente** — Relatório consolida ao terminar o disparo. Durante o disparo, o cliente faz polling HTTP a cada 30s para mostrar contadores (sem broadcast Reverb). *Justificativa*: WebSocket exige autorização de canal + reconnect — custo de implementação alto para um caso de uso de leitura simples; polling 30s é suficiente para batches de minutos.

- **Q7 (blackout period)** → **A deduzido do horário comercial do tenant** — Não há blackout sistêmico extra. Disparo respeita o `business_hours` já configurado pela clínica na Fase 5. Fora do horário comercial → mensagens enfileiradas com `scheduled_for=próximo_inicio_horário_comercial`. *Justificativa*: blackout duplicado (sistêmico + tenant) gera dupla regra confusa para suporte explicar. Tenant é dono do seu horário comercial.

#### Módulo 2 — Relatórios

- **Q8 (NPS no MVP)** → **C placeholder** — Card de NPS no Dashboard Executivo exibe estado "Em breve — disponível em fase futura" + link para roadmap. Sem coleta automática nesta fase. *Justificativa*: NPS exige timer pós-consulta, UI de pesquisa multi-canal, scoring, agregação — módulo separado de tamanho não trivial. Implementar agora atrasaria go-live sem ROI imediato.

- **Q9 (período de atualização dos relatórios)** → **B atualização horária via job + queries live para janelas ≤24h** — Agregações pré-computadas a cada hora para janelas ≥7d; queries on-demand para janelas curtas (hoje, ontem, últimas 24h). *Justificativa*: tradeoff entre frescor e performance; janelas curtas têm volume pequeno e podem ser calculadas live, janelas longas exigem materialização.

- **Q10 (drill-down no dashboard)** → **A abre lista filtrada** — Clicar em qualquer KPI abre a lista correspondente (pacientes / conversas / agendamentos) com filtros já aplicados. *Justificativa*: drill-down é o maior multiplicador de valor do dashboard — sem ele, KPI vira "número bonito sem ação". Aproveita listas existentes das Fases 2–7.

- **Q11 (comparativo entre períodos)** → **B variação percentual contra período anterior** — Cada KPI mostra `Δ%` versus período imediatamente anterior de mesmo tamanho (ex.: 30d atual vs. 30d anteriores). Modo "lado a lado" fica para pós-MVP. *Justificativa*: variação % é a leitura mais comum em dashboard executivo e cobre o caso de uso "estou melhorando ou piorando?".

- **Q12 (exportação PDF do dashboard)** → **B layout formatado próprio** — PDF tem cabeçalho da clínica, sumário, gráficos vetoriais, rodapé com filtros aplicados e timestamp. Não é screenshot. *Justificativa*: screenshot é frágil (resolução, viewport), e PDF formatado é apresentável a stakeholders externos da clínica (sócios, investidores).

- **Q13 (acesso aos relatórios por perfil)** → **A escopo por papel** — Médico vê **apenas dados da própria agenda/pacientes** (consultas em que é `professional_id`, pacientes onde é `profissional_responsavel`). Admin Clínica e proprietário veem dados completos do tenant. Atendente/Recepcionista veem dados completos do tenant **exceto** receitas controladas (Fase 7). *Justificativa*: princípio do menor privilégio (Princípio I); evita vazamento entre médicos no mesmo tenant; alinha com regras já estabelecidas na Fase 7 para controladas.

#### Módulo 3 — Integrações

- **Q14 (escopo da API pública v1)** → **A escopo mínimo seguro** — Endpoints expostos: (i) pacientes (read + write tenant-scoped, sem campo `pseudonimization_state`), (ii) agendamentos (read + write tenant-scoped), (iii) mensagens (read-only — sem write para evitar bypass dos guardrails da Fase 3), (iv) receituários básicos (read-only, controladas SEMPRE mascaradas independente do escopo do token), (v) tipos de atendimento (read-only), (vi) profissionais (read-only). **Excluídos**: campanhas, métricas internas, billing/cashier, audit_logs, decisões da IA, configurações de webhook. *Justificativa*: superfície mínima protege LGPD + Meta + integridade da plataforma; faltantes podem ser adicionados em v1.1 sem breaking change.

- **Q15 (rate limit da API pública)** → **A por token de tenant, com limites por plano** — Limites primários: básico 100 req/min, pro 1000 req/min, enterprise 5000 req/min. Rate limit secundário por IP (defesa anti-DDoS, 10k req/min cap rígido) aplicado em cima. Headers `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After` em 100% das respostas. *Justificativa*: por token alinha cobrança ao consumo real; IP é hard cap defensivo.

- **Q16 (webhook retry)** → **A 5 tentativas + backoff exponencial + DLQ acessível** — Cadência: 30s, 2min, 10min, 1h, 6h (entrega final em até ~7,5h). Após esgotar, evento vai para **Dead Letter Queue** acessível ao Admin Clínica via painel "Webhooks → Falhas" com retenção de **30 dias** e botão "Reenviar manualmente". *Justificativa*: 5 tentativas é o padrão Stripe; DLQ + reenvio manual + 30d de retenção atende o "operacional não bloqueante".

- **Q17 (catálogo de eventos webhook)** → **A catálogo expandido** — Lista MVP: `PacienteCriado`, `PacienteAtualizado`, `AgendamentoCriado`, `AgendamentoConfirmado`, `AgendamentoCancelado`, `AgendamentoReagendado`, `ConsultaRealizada`, `PrescricaoCriada`, `PrescricaoCancelada`, `ReceitaRenovada`, `ReceitaProximaDoVencimento`, `RetornoDisparado`, `EscalonamentoParaHumano`. Excluídos do catálogo público: eventos de campanha (Módulo 1), webhooks (auto-referência), audit logs, decisões da IA com prompt. *Justificativa*: cobre os eventos materiais que sistemas externos esperam consumir (BI, ERP, automação); excluídos evitam loop/vazamento.

- **Q18 (autenticação de chamadas externas à API pública)** → **A Sanctum estático no MVP + OAuth 2.0 Client Credentials opt-in para enterprise** — Default é token Sanctum hashado (modelo da Fase 4, reaproveitado), gerado pelo Admin Clínica em "Configurações → Tokens de API". OAuth Client Credentials disponível como opt-in para tenants enterprise — `client_id` + `client_secret` emite JWT de 1h. *Justificativa*: Sanctum cobre 90% dos parceiros técnicos com simplicidade máxima; OAuth fica como upgrade para integrações enterprise que exigem fluxo padrão (BI corporativo, ERP de hospital).

#### Módulo 4 — Painel Super Admin

- **Q19 (escopo de impersonate)** → **C acesso total com banner persistente + audit granular** — Super Admin impersonando tem acesso a TUDO que Admin Clínica daquele tenant veria (incluindo receitas controladas), banner amarelo persistente "MODO IMPERSONATE — tenant {nome} — você está visualizando como suporte" em todas as telas, e cada tela visitada gera audit_log granular (`ImpersonateTelaVisitada{super_admin_id, tenant_id, route, visited_at}`). *Justificativa*: suporte precisa diagnosticar incidentes reais (não vai virar "suporte cego"); transparência via banner + audit granular endereça o risco LGPD (Super Admin como operador-com-acesso-total é juridicamente aceito desde que rastreado).

- **Q20 (retenção pós-cancelamento)** → **D política diferenciada por categoria** — (i) Billing/financeiro: 5 anos (obrigação fiscal Lei 12.682/2012). (ii) Receitas controladas (Fase 7): 2 anos (Portaria 344/98). (iii) Audit logs: 1 ano (LGPD Art. 16). (iv) Demais dados de paciente (cadastro, mensagens, agendamentos): anonimizados em 90 dias após cancelamento (janela "undo" de 30 dias + grace de 60 dias). (v) Dados de configuração do tenant (planos, integrações): deletados após 30 dias. *Justificativa*: política única (ex.: tudo 90d) violaria obrigações fiscais ou regulatórias específicas; política diferenciada é a única defensável juridicamente.

- **Q21 (definição de churn)** → **D conservador para core, com revenue churn separado** — Churn rate primário = `(tenants_cancelados_no_período) / (tenants_ativos_início_período)`. Métrica complementar "Revenue Churn" = perda de MRR por cancelamento + downgrades de plano. Suspensões não entram em churn (suspenso pode reativar). *Justificativa*: definição estrita é o padrão SaaS e evita inflação artificial; revenue churn separado captura downgrades sem confundir a métrica principal.

- **Q22 (alertas automáticos para Super Admin)** → **C ambos (notificação + painel) com threshold configurável** — Anomalias detectadas: (i) queda de conversão trial→pago > 20% semana sobre semana, (ii) consumo de IA de tenant > 10x da média histórica do próprio tenant, (iii) taxa de falha de webhook > 50% em 1h, (iv) tenant em inadimplência > 30 dias. Canal: **inbox interna do Super Admin + e-mail crítico** (para o e-mail de plataforma cadastrado no perfil). Sem Slack/PagerDuty no MVP. *Justificativa*: inbox interna garante visibilidade no painel; e-mail crítico cobre o caso de "ninguém está logado no painel agora"; sem Slack evita dependência externa do MVP.

- **Q23 (criação manual de tenant pelo Super Admin)** → **A permitido com billing_mode flexível** — Super Admin pode criar tenant manualmente via painel; campo `billing_mode ∈ {stripe, offline_invoice}`. Modo `stripe` segue o fluxo Cashier da Fase 0; modo `offline_invoice` marca o tenant como "enterprise — billing externo" e ignora gates de cobrança automática (suporta clientes que pagam via NF/boleto bancário fora da plataforma). *Justificativa*: enterprise sales é caso real do funil; sem modo offline, time comercial não consegue fechar contratos enterprise sem hack no DB.

#### Módulo 5 — Privacidade / LGPD

- **Q24 (granularidade de consentimento)** → **C modelo hierárquico** — Três níveis: (i) **transacional** (sempre obrigatório — confirmação de consulta, alerta de receita, resposta a mensagens iniciadas pelo paciente) — implícito ao se cadastrar; (ii) **marketing** (campanhas, lembretes proativos) — opt-in explícito requerido; (iii) **pesquisa** (NPS, surveys) — opt-in separado (preparado para quando Q8 evoluir). Compartilhamento com convênio fica fora do MVP. *Justificativa*: hierárquico equilibra UX (sem 4 perguntas no primeiro contato) com conformidade LGPD (marketing é claramente segregado de transacional).

- **Q25 (revogação parcial)** → **A suportada granularmente** — Paciente pode revogar marketing mantendo transacional. Comando `/sair` em canal revoga **apenas marketing** (default conservador — paciente pode estar pedindo para parar de receber campanhas mas ainda querer receber lembrete da próxima consulta). Para revogar tudo, paciente envia `/sair tudo` ou usa formulário de privacidade. *Justificativa*: revogação total via `/sair` quebraria o canal transacional (paciente que não recebe confirmação de consulta volta a ligar — anula o produto); granularidade preserva utilidade do canal sem violar a vontade do paciente.

- **Q26 (mapa de anonimização do Direito ao Esquecimento)** →
  - **Anonimizados com placeholder** (preservados na tabela, valores substituídos):
    - `nome` → `"Paciente Anonimizado #{id}"`
    - `cpf` → `"000.000.000-00"`
    - `rg` → `null`
    - `telefone` → `"00000000000"`
    - `email` → `null`
    - `data_nascimento` → `1900-01-01`
    - `convenio_carteirinha` → `null`
  - **Deletados fisicamente** (storage removido + coluna nullada):
    - `foto_url` (arquivo + URL)
    - `endereco_completo` (rua, número, complemento, bairro)
    - `anotacoes_livres` (corpo das anotações da Fase 2)
    - `mensagens_corpo` (corpo das mensagens — metadados de conversa preservados: timestamps, canal, direção)
  - **Preservados por obrigação legal** (com banner "Dados preservados por obrigação legal — retenção até DD/MM/AAAA"):
    - `prescricoes_controladas` — 2 anos (Portaria SVS/MS nº 344/98) a partir da data de emissão
    - `registros_financeiros` (billing, faturas, transações) — 5 anos (Lei 12.682/2012) a partir da data da transação
    - `audit_logs` — 1 ano (LGPD Art. 16) a partir da data do log
    - `consentimentos` (registro do consent + evidência) — 5 anos após revogação (prova de conformidade)
  - *Justificativa*: mapa explícito é única forma de tornar a execução auditável; sem mapa, "anonimizar" vira interpretação ad-hoc do desenvolvedor.

- **Q27 (notificação de proximidade do prazo LGPD)** → **C inbox + e-mail em D-5 e D-2** — Sistema notifica Admin Clínica em duas ondas: **D-5** (5 dias úteis antes do deadline) via inbox interna; **D-2** (2 dias úteis) via inbox interna + e-mail + alerta visual persistente no topo do painel até execução. Após D+0 vencido sem ação, alerta crítico ao Super Admin (Q22). *Justificativa*: dois pontos de contato com escalonamento progressivo; e-mail garante alcance fora do painel; alerta visual persistente em D-2 impede "esquecer no rodapé".

- **Q28 (portabilidade de dados)** → **A implementada no MVP** — Solicitação de portabilidade gera **arquivo JSON estruturado** com escopo: dados cadastrais (nome, CPF, telefone, e-mail, endereço, convênio), timeline pública (consultas realizadas com data/tipo/profissional, mensagens com data/canal/direção SEM corpo se opt-out aplicável), agendamentos (passados e futuros), receituários (mascarados se controladas — Fase 7). Entrega via download autenticado com URL assinada de 7 dias e e-mail de notificação. Prazo de 15 dias úteis (mesmo deadline do esquecimento). *Justificativa*: LGPD Art. 18 inciso V é direito explícito; deixar para pós-MVP transfere risco regulatório; JSON estruturado é o padrão mais portável; URL assinada protege contra interceptação.

- **Q29 (auditoria retroativa de pseudonimização)** → **C ambos (varredura estática + replay de eventos)** — (i) **Varredura estática via reflection**: gate de CI estende o padrão da Fase 7 (`ContainsNoClinicalData` marker interface) — qualquer evento consumido pela IA deve implementá-la + lista explícita de propriedades; teste falha o build se propriedade nova não declarada. (ii) **Replay contra detector de PII**: job semanal processa amostra dos eventos persistidos (1% randômico, com peso para eventos recentes) contra regex de CPF (`\d{3}\.?\d{3}\.?\d{3}-?\d{2}`), telefone (`\(?\d{2}\)?\s?\d{4,5}-?\d{4}`), e-mail (`[\w.-]+@[\w.-]+`) — qualquer hit gera ticket crítico. Relatório consolidado no painel de Privacidade. *Justificativa*: estática garante prevenção em design; replay valida em runtime — defesa em profundidade alinhada ao padrão Fase 7.

---

## 0. Contexto e Visão Geral

A **Fase 8 fecha o MVP** do CRM Médico SaaS. Ela entrega cinco módulos **independentes em domínio** mas **codependentes operacionalmente** — sem campanhas o produto não cresce, sem relatórios o cliente não enxerga valor, sem API pública não existe parceria de canal, sem painel Super Admin a plataforma não opera, e sem o módulo de privacidade explícito **o produto não pode ser vendido em produção** (gate regulatório).

A fase é deliberadamente abrangente porque cada módulo isoladamente é "pequeno" em superfície funcional, mas todos compartilham a mesma janela de go-live: o produto só atinge "vendável" quando os cinco estão prontos simultaneamente.

### Princípio organizador

Cada módulo abaixo:

- Possui **escopo claro** (RF herdadas, US do PRD, ACs próprios numerados).
- Emite eventos de domínio próprios (listados na seção de cada módulo).
- Mapeia para princípios específicos da constituição (§7 deste spec).
- Tem critérios de pronto independentes (§6 — Definição de Pronto do MVP consolida o conjunto).

### Encaixe na plataforma

- **Campanhas (Módulo 1)** consome o serviço de mensageria da Fase 3, o conceito de tags/segmentação da Fase 2, e respeita os guardrails de conformidade Meta do Princípio VI. É domínio próprio, **distinto** da cadência de retornos (Fase 6) e dos alertas de receituário (Fase 7).
- **Relatórios (Módulo 2)** é estritamente read-only — agrega entidades de Fases 2 a 7 (pacientes, agenda, retornos, receituários, decisões da IA). Não modifica nada. Materializações por job para grandes volumes.
- **Integrações (Módulo 3)** expõe externamente endpoints já existentes (Fases 1–7 elegíveis) e abre canal de notificação de eventos para sistemas externos. Não cria endpoints de domínio novos além dos meta-endpoints de configuração/observabilidade.
- **Painel Super Admin (Módulo 4)** opera **sobre** as entidades multi-tenant existentes — tenants, planos, billing (Stripe da Fase 0). Adiciona ações administrativas (suspender, reativar, impersonate) e métricas globais consolidadas.
- **Privacidade/LGPD (Módulo 5)** formaliza, instrumenta e audita políticas já parcialmente aplicadas em fases anteriores (pseudonimização da Fase 4/5/7, audit logs Fase 1, consentimento embrionário Fase 3). Adiciona painel de privacidade, fluxo formal de direito ao esquecimento, registro granular de consentimento e auditoria retroativa.

---

## 1. User Scenarios & Testing *(mandatory)*

Os cinco módulos abaixo são apresentados como **User Stories independentes**, cada uma com prioridade e seu próprio bloco de ACs numerados (`AC-{épico}.{us}.{seq}`) e marcadores de criticidade.

> **Convenção de criticidade nos ACs**
> 🔴 **Crítico** — bloqueia go-live; falha violaria princípio NON-NEGOTIABLE da constituição ou expectativa contratual mínima.
> 🟡 **Importante** — bloqueia experiência completa do MVP; falha entrega produto fragilizado mas operável.
> 🟢 **Nice-to-have** — refinamento; falha não bloqueia go-live, fica em backlog pós-MVP.

---

### Módulo 1 — Campanhas e Reativação (Épico 9, Prioridade P2)

A clínica precisa reativar pacientes ociosos e aproveitar sazonalidade clínica sem depender de ferramentas externas de marketing. Este módulo entrega campanhas em massa com guardrails de conformidade LGPD + Meta embutidos.

#### User Story 1.1 — Campanha de Reativação de Inativos (US-9.1) — Prioridade P2

**Como** Admin Clínica
**Quero** disparar uma campanha para pacientes inativos há 6 meses ou 1 ano
**Para que** eu reative base ociosa e gere agendamentos sem dependência de ferramenta externa

**Por que essa prioridade**: campanhas são o motor de receita recorrente da clínica e a primeira métrica vendida no pitch comercial; sem campanhas, o produto fica "CRM passivo".

**Independent Test**: criar uma campanha definindo "inativos há 6m+" + tag "vacinação", visualizar pré-visualização do público, disparar para subset de 5 pacientes consentidos e validar que apenas pacientes com opt-in válido recebem, que mensagens fora da janela 24h usam template aprovado, e que o relatório de campanha atualiza estados (enviado/entregue/lido/respondido).

**Acceptance Scenarios**:

- **AC-9.1.1** 🔴 **Given** estou autenticado como Admin Clínica com `campaign.create`, **When** crio uma campanha de reativação definindo critério de inatividade (6m / 1a / personalizado em meses) + filtros adicionais (tags, último profissional), **Then** o sistema persiste a campanha tenant-scoped com status `draft` e calcula o público estimado server-side usando o critério **última consulta com status `realizada`** (vindo de `ConsultaRealizada`/Fase 5) — Q1. A pré-visualização exibe apenas a contagem (`N pacientes elegíveis`) sem PII de pacientes individuais.
- **AC-9.1.2** 🔴 **Given** uma campanha `draft` pronta para disparo, **When** clico em "Disparar agora", **Then** o sistema valida, **antes** de qualquer envio: (i) opt-in **de marketing** válido por destinatário (Q24/Q25 — transacional não autoriza), (ii) template aprovado pela Meta para canal WhatsApp fora da janela 24h, (iii) `business_hours` do tenant (Fase 5) — fora do horário comercial, mensagens são enfileiradas para o próximo início válido (Q7), (iv) limite de envio diário do plano do tenant não excedido (Q2). Destinatários que falharem qualquer validação são **automaticamente excluídos do disparo**, registrados em `campaign_dispatch_log` com motivo de bloqueio.
- **AC-9.1.3** 🔴 **Given** o disparo está em curso, **When** qualquer envio é executado, **Then** o sistema emite `MensagemCampanhaEnviada{patient_id, campaign_id, channel, status, blocked_reason?}` por destinatário; cada mensagem outbound contém link/comando de descadastro (`/sair` em chat ou link de unsubscribe em e-mail) — conformidade VI da constituição.
- **AC-9.1.4** 🟡 **Given** o disparo terminou, **When** abro o relatório da campanha, **Then** vejo as métricas: total elegível, enviados, bloqueados por motivo (sem opt-in / sem template / fora de horário / falha técnica), entregues, lidos (quando o canal reporta), respondidos, **agendamentos atribuíveis** (paciente respondeu e agendou em ≤7d). Durante o disparo, o cliente faz **polling a cada 30s** para atualizar contadores em tempo quase real (Q6); ao final do batch, o relatório consolida e fixa.
- **AC-9.1.5** 🟡 **Given** o disparo encontrou pacientes sem opt-in de marketing, **When** processo o disparo, **Then** **nenhum fallback** é tentado — destinatário é excluído e o motivo `no_marketing_opt_in` aparece no relatório. O Admin Clínica vê CTA "Coletar consentimento" que abre o painel de privacidade (Módulo 5).
- **AC-9.1.6** 🟡 **Given** uma campanha em curso, **When** clico em "Pausar/Cancelar", **Then** o sistema interrompe o batch — envios já em fila ainda podem ser entregues (race aceitável), mas nenhum novo envio é despachado. Evento `CampanhaCancelada{campaign_id, canceled_at, canceled_by}` é emitido.
- **AC-9.1.7** 🟢 **Given** o disparo terminou, **When** olho a métrica de "agendamentos atribuíveis", **Then** o número reflete pacientes que responderam à campanha E criaram agendamento ≤7d após a mensagem (vinculação via campo `appointment.attributed_campaign_id` populado pela conversa).
- **AC-9.1.8** 🔴 **Given** uma campanha tem canal único definido (WhatsApp OU Instagram OU SMS futuro) — Q3, **When** o Admin Clínica seleciona o canal, **Then** apenas pacientes com esse canal conectado entram no público elegível. Para alcançar base completa em 2 canais, Admin Clínica cria 2 campanhas distintas.

---

#### User Story 1.2 — Campanha Sazonal (US-9.2) — Prioridade P3

**Como** Admin Clínica
**Quero** agendar campanhas para datas específicas (vacinação anual, check-up, datas comemorativas)
**Para que** eu aproveite sazonalidade clínica sem ter que disparar manualmente no dia

**Por que essa prioridade**: complementa US-9.1 com disparo agendado; é "extra" do módulo de campanhas, valor incremental sobre o disparo imediato.

**Independent Test**: criar campanha com `scheduled_for = D+7`, verificar que aparece em "campanhas agendadas", esperar (ou simular o relógio) o gatilho da data, e validar que o disparo executa com as mesmas validações de US-9.1.

**Acceptance Scenarios**:

- **AC-9.2.1** 🔴 **Given** estou em "Nova campanha sazonal", **When** preencho data/hora futuro, segmentação (idade, sexo, tags, último procedimento) e template, **Then** o sistema persiste com status `scheduled` e exibe na lista "Campanhas agendadas" com countdown.
- **AC-9.2.2** 🔴 **Given** uma campanha sazonal atinge sua data agendada, **When** o relógio cruza `scheduled_for`, **Then** o disparo executa com as mesmas validações de AC-9.1.2 (opt-in marketing, template Meta, horário comercial, limite diário do plano, canal único). Emite evento `CampanhaDisparada{campaign_id, dispatched_at}`.
- **AC-9.2.3** 🟡 **Given** uma campanha está `scheduled`, **When** edito a configuração antes da execução, **Then** as alterações são persistidas e geram entrada em audit_logs `CampanhaAtualizada`. Campanha já disparada é **imutável** — alteração exige cancelamento + nova campanha.
- **AC-9.2.4** 🟡 **Given** vou disparar campanha sazonal, **When** clico em "Pré-visualizar", **Then** vejo (i) mensagem renderizada com merge de variáveis em 1 destinatário fictício, (ii) público estimado com filtros aplicados, (iii) lista de avisos: "12 pacientes sem opt-in serão excluídos", "template requer aprovação Meta — status atual: approved/rejected/pending". Pré-visualização é a "última checagem" do fluxo sem etapa de aprovação separada (Q4).
- **AC-9.2.5** 🟢 **Given** campanha sazonal com público >limite diário do plano, **When** disparo, **Then** o sistema fragmenta o batch em sub-lotes respeitando o limite (Q2) — sobras vão para o dia seguinte automaticamente e o relatório mostra "X enviados hoje / Y agendados para D+1".

---

#### User Story 1.3 — Conformidade de Disparo LGPD + Meta (US-9.3) — Prioridade P1

**Como** Plataforma
**Quero** garantir conformidade automática em todos os disparos de campanha
**Para que** clínicas não percam acesso ao WhatsApp Business e não sofram penalidades LGPD

**Por que essa prioridade**: é o gate NON-NEGOTIABLE (Princípio VI). Sem este componente, qualquer falha humana em US-9.1/9.2 vira incidente regulatório.

**Independent Test**: forçar cenários de violação (paciente sem opt-in, template não aprovado, disparo fora do horário comercial, comando "/sair" recebido) e verificar que o dispatcher bloqueia/reverte conforme regra.

**Acceptance Scenarios**:

- **AC-9.3.1** 🔴 **Given** qualquer campanha em curso, **When** o dispatcher avalia cada destinatário, **Then** **bloqueia em runtime** se: opt-in de marketing ausente OU template não aprovado pela Meta OU fora de `business_hours` do tenant OU comando `/sair` recebido nas últimas 24h pelo destinatário. Cada bloqueio gera audit_log com motivo explícito.
- **AC-9.3.2** 🔴 **Given** o paciente envia `/sair` em qualquer canal vinculado à clínica, **When** o sistema processa a mensagem, **Then** o opt-in **de marketing** é **imediatamente** revogado (Q25 — sem afetar transacional); emite `PacienteDescadastradoDeCampanhas{patient_id, channel, finalidade='marketing', revoked_at}`, a clínica recebe notificação na inbox interna, e qualquer campanha em fila exclui esse paciente antes do envio. Para revogar tudo (incluindo transacional), paciente deve enviar `/sair tudo` ou usar formulário de privacidade.
- **AC-9.3.3** 🔴 **Given** todo envio outbound de campanha, **When** o conteúdo é renderizado, **Then** **toda** mensagem não-transacional inclui link/comando de descadastro visível no corpo do template (gate Meta + LGPD). Templates sem esse elemento são rejeitados na validação de cadastro do template no sistema.
- **AC-9.3.4** 🟡 **Given** uma campanha foi bloqueada por motivo de conformidade, **When** o Admin Clínica olha o relatório, **Then** vê linha por motivo de bloqueio (sem opt-in: 23, sem template: 5, fora de horário: 12) com link de ação para resolver (coletar opt-in, solicitar template, ajustar horário).
- **AC-9.3.5** 🟢 **Given** um template HSM enviado anteriormente foi reprovado/expirado pela Meta, **When** uma campanha tenta usá-lo, **Then** o dispatcher rejeita com motivo `template_no_longer_approved` e notifica o Admin Clínica via inbox interna com link para gerenciar templates.

---

#### Eventos de Domínio emitidos pelo Módulo 1

- `CampanhaCriada{campaign_id, created_by, scheduled_for?, audience_filters, channel}`
- `CampanhaAtualizada{campaign_id, changed_fields, updated_by}` (somente status `scheduled`)
- `CampanhaDisparada{campaign_id, dispatched_at, total_eligible, total_blocked}`
- `CampanhaCancelada{campaign_id, canceled_at, canceled_by, reason}`
- `MensagemCampanhaEnviada{patient_id, campaign_id, channel, status, blocked_reason?}`
- `PacienteDescadastradoDeCampanhas{patient_id, channel, finalidade, revoked_at}`

#### Edge Cases — Módulo 1

- Paciente com 0 canais conectados → excluído com motivo `no_reachable_channel`.
- Paciente que já recebeu uma mensagem desta campanha (re-disparo acidental) → deduplicação por `(campaign_id, patient_id)`; segunda execução é idempotente.
- Tenant com WhatsApp quality rating "RED" (rebaixado pela Meta) → dispatcher emite warning visível antes do disparo; Admin Clínica precisa confirmar explicitamente.
- Campanha agendada para data passada (UI clock dessincronizado) → rejeita na criação com erro de validação.
- Templates HSM aprovados em idioma diferente do tenant → rejeita o template no momento da seleção.
- Tenant com `business_hours` não configurado → dispatcher recusa com erro "Configure horário comercial antes de criar campanha".

---

### Módulo 2 — Relatórios e Dashboard (Épico 10, Prioridade P2)

A clínica precisa de visibilidade quantitativa do negócio sem depender de planilhas paralelas. Este módulo entrega três visões: executiva (KPIs estratégicos), operacional (gargalos de atendimento) e clínica (ocupação e mix de procedimentos).

#### User Story 2.1 — Dashboard Executivo (US-10.1) — Prioridade P1

**Como** Admin Clínica ou Médico (proprietário)
**Quero** uma visão consolidada de KPIs estratégicos do negócio
**Para que** eu tome decisões baseadas em dados em vez de sensação

**Por que essa prioridade**: é o primeiro item que o cliente abre depois do login. Sem dashboard, o produto "não tem o que mostrar" no day 1.

**Independent Test**: gerar dados de teste com leads/agendamentos/cancelamentos em um período de 30d, abrir o dashboard e verificar que os 5 cards principais (leads/canal, conversão, no-show, NPS placeholder, faturamento estimado) refletem os números esperados; trocar o filtro de período e validar que recálculo ocorre.

**Acceptance Scenarios**:

- **AC-10.1.1** 🔴 **Given** estou autenticado como Admin Clínica com `dashboard.view`, **When** abro o dashboard executivo, **Then** vejo cinco cards principais: (i) leads por canal, (ii) taxa de conversão lead → consulta realizada, (iii) taxa de no-show, (iv) **NPS exibido como placeholder "Em breve — disponível em fase futura"** com link para roadmap (Q8), (v) faturamento estimado (soma de `appointment_type.preco` por consulta `realizada` no período).
- **AC-10.1.2** 🔴 **Given** o dashboard está aberto, **When** seleciono filtro de período (7d / 30d / 90d / customizado), **Then** todos os cards e gráficos recalculam. Janelas ≤24h usam queries on-demand; janelas ≥7d usam **agregações pré-computadas a cada hora** (Q9) — timestamp da última agregação aparece no rodapé do dashboard.
- **AC-10.1.3** 🟡 **Given** estou em um card, **When** clico nele, **Then** abre **lista filtrada** correspondente (clicar em "23 leads por Instagram" abre lista de leads desse canal no período) — Q10. As listas usam as views já existentes das Fases 2–7.
- **AC-10.1.4** 🟡 **Given** o dashboard mostra gráficos de tendência mensal, **When** acesso o relatório, **Then** vejo série temporal dos últimos 12 meses para cada KPI principal, com eixo Y começando em 0 (não truncado — evita distorção visual).
- **AC-10.1.5** 🟡 **Given** quero compartilhar o dashboard, **When** clico em "Exportar PDF", **Then** o sistema gera **PDF em layout próprio** com cabeçalho da clínica (nome + logo opcional do tenant), sumário executivo, gráficos vetoriais, rodapé com filtros aplicados + timestamp (Q12). Grava entrada em audit_logs `RelatorioExportado{tipo='dashboard', formato='pdf', exported_by, filters_applied}`.
- **AC-10.1.6** 🔴 **Given** sou Médico (não proprietário), **When** abro o dashboard, **Then** vejo apenas dados onde sou `professional_id` da consulta ou `profissional_responsavel` do paciente (Q13). Admin Clínica e proprietário veem dados completos do tenant. Tentativa de manipular parâmetro de URL para ver outro escopo retorna 403.
- **AC-10.1.7** 🟢 **Given** cada KPI mostra Δ%, **When** comparo com período anterior, **Then** vejo a variação percentual contra o período imediatamente anterior de mesmo tamanho (ex.: "30d atuais vs. 30d anteriores: +12%") — Q11. Modo "lado a lado" fica para pós-MVP.

---

#### User Story 2.2 — Relatórios Operacionais (US-10.2) — Prioridade P2

**Como** Admin Clínica
**Quero** acompanhar performance da operação de atendimento (tempo de resposta, volume por atendente, performance da IA)
**Para que** eu identifique gargalos e oportunidades de treinamento

**Por que essa prioridade**: foco em produtividade — segundo passo depois do dashboard executivo; necessário para clínicas que usam o produto com 3+ atendentes.

**Independent Test**: simular 100 conversas com tempos de resposta variados em uma semana, abrir o relatório operacional e validar percentis (p50, p95, máx) por atendente e canal; comparar período atual contra anterior.

**Acceptance Scenarios**:

- **AC-10.2.1** 🔴 **Given** estou em "Relatórios → Operacional", **When** o relatório carrega, **Then** vejo: (i) tempo médio de primeira resposta (humano + IA combinados, separados), (ii) tempo médio até resolução da conversa, (iii) volume por atendente (mensagens enviadas, conversas atendidas, conversas concluídas), (iv) performance da IA (taxa de resolução autônoma sem escalonamento, taxa de escalonamento, score médio de confiança — vindos de `ai_decision_logs` da Fase 4).
- **AC-10.2.2** 🟡 **Given** quero detalhar, **When** clico em "drill-down por atendente", **Then** vejo tabela com atendentes em linhas e métricas em colunas, ordenável; o mesmo padrão para "drill-down por canal" (WhatsApp/Instagram/Web).
- **AC-10.2.3** 🟡 **Given** quero comparar evolução, **When** olho cada métrica, **Then** vejo variação percentual contra o período anterior (Q11) — mesma convenção do Dashboard Executivo.
- **AC-10.2.4** 🟡 **Given** a métrica "performance da IA" depende de `ai_decision_logs` da Fase 4, **When** o relatório agrega, **Then** apenas dados com `decision_confidence` registrado (>= 0) são contabilizados; decisões com null por erro de telemetria são excluídas mas reportadas em log de qualidade dos dados.
- **AC-10.2.5** 🟢 **Given** quero exportar, **When** clico em "Exportar CSV", **Then** o CSV contém linhas de detalhe por conversa (sem corpo das mensagens — apenas identificadores anonimizados, timestamps e métricas) e cabeçalho com filtros aplicados.

---

#### User Story 2.3 — Relatórios Clínicos (US-10.3) — Prioridade P2

**Como** Admin Clínica
**Quero** ver ocupação por profissional, mix de procedimentos e completude de retornos
**Para que** eu otimize a agenda e a oferta de serviços

**Por que essa prioridade**: foco em produtividade clínica — diferencial do CRM **médico** vs. CRM genérico.

**Independent Test**: gerar dados em 30d com diferentes profissionais e tipos de atendimento, abrir o relatório e validar (i) taxa de ocupação por profissional, (ii) top 5 procedimentos por volume, (iii) retornos completados vs. perdidos da Fase 6.

**Acceptance Scenarios**:

- **AC-10.3.1** 🔴 **Given** estou em "Relatórios → Clínico", **When** o relatório carrega, **Then** vejo: (i) ocupação por profissional (`slots_disponíveis_no_período` / `slots_consumidos` por consulta com status `scheduled`/`confirmed`/`realizada`), (ii) ranking de tipos de procedimento por volume e por faturamento, (iii) retornos completados vs. perdidos (vinculado às cadências da Fase 6).
- **AC-10.3.2** 🟡 **Given** o relatório de ocupação, **When** existem múltiplos profissionais no tenant, **Then** vejo gráfico de barras horizontal com nome do profissional + percentual de ocupação + total absoluto de consultas. Profissionais inativos são listados separadamente.
- **AC-10.3.3** 🟡 **Given** o relatório de retornos, **When** carrega, **Then** vejo (i) taxa de retorno completado por cadência configurada na Fase 6, (ii) lista de retornos vencidos sem contato manual nas últimas 7d (highlight em vermelho), (iii) CTA "Disparar campanha de reativação para vencidos" (link para Módulo 1).
- **AC-10.3.4** 🟡 **Given** quero exportar, **When** clico em "CSV" ou "PDF", **Then** a exportação inclui filtros aplicados como cabeçalho, grava entrada em audit_logs (`RelatorioExportado`) e respeita restrições de acesso a receitas controladas (mesma regra da Fase 7 AC-8.4.5) se o relatório incluir prescrições.
- **AC-10.3.5** 🟢 **Given** sou Médico, **When** abro relatório clínico, **Then** vejo apenas dados onde sou `professional_id` (Q13). Para Admin Clínica e proprietário, dados completos do tenant.
- **AC-10.3.6** 🟢 **Given** taxa de ocupação cai abaixo de threshold configurável (ex.: <50%), **When** o relatório é exibido, **Then** highlight visual sugere ação ("Agenda com baixa ocupação — considere campanha sazonal").

---

#### Eventos de Domínio emitidos pelo Módulo 2

- `RelatorioExportado{tipo, formato, exported_by, filters_applied, exported_at}` (audit-only, grava em audit_logs)

> **Nota**: este módulo é majoritariamente read-only. O único "evento" relevante é a exportação, que precisa ser auditável por Princípio V.

#### Edge Cases — Módulo 2

- Tenant com 0 consultas no período → cards exibem "0" (não vazio); gráficos mostram estado vazio com mensagem amigável.
- Filtro customizado com janela > 365d → rejeita com mensagem ("período máximo de relatório: 12 meses"); evita queries pesadas.
- Médico inativo durante o período → aparece com taxa de ocupação proporcional aos dias ativos (não diluído pelo período inteiro).
- Janela de relatório que cruza mudança de plano do tenant (downgrade) → métricas refletem dados reais; nota informativa no rodapé.
- Job de agregação horária atrasou > 90min → dashboard exibe banner "Dados podem estar com até X minutos de atraso" + última agregação no rodapé.

---

### Módulo 3 — Integrações: Webhooks e API Pública (Épico 11, Prioridade P3)

A clínica de maior porte e parceiros técnicos precisam integrar o CRM com sistemas próprios (ERP, prontuário externo, BI). Este módulo entrega o canal de saída (webhooks de eventos) e o canal de entrada (API pública versionada, autenticada e rate-limited).

#### User Story 3.1 — Webhooks de Eventos (US-11.1) — Prioridade P3

**Como** Admin Clínica com sistemas próprios
**Quero** configurar webhooks que recebem eventos do CRM
**Para que** eu integre o CRM com ferramentas externas (BI, automação, prontuário externo)

**Por que essa prioridade**: é o primeiro item de "API friendly" — sem ele, integração é manual via export CSV. P3 porque o público é minoria das clínicas (as enterprise).

**Independent Test**: cadastrar webhook URL + segredo, criar agendamento no CRM, validar que o webhook foi disparado, payload chegou ao destinatário com assinatura HMAC válida, e que falha temporária aciona retry com backoff.

**Acceptance Scenarios**:

- **AC-11.1.1** 🔴 **Given** estou em "Configurações → Webhooks" como Admin Clínica, **When** cadastro URL alvo + segredo + lista de eventos a assinar, **Then** o sistema persiste configuração tenant-scoped, valida que a URL responde HTTPS (rejeita HTTP em produção) e exibe a configuração na lista com status "ativo".
- **AC-11.1.2** 🔴 **Given** um evento da lista assinada ocorre no tenant (ex.: `AgendamentoCriado`), **When** o domínio emite, **Then** o sistema enfileira a entrega ao webhook configurado com payload JSON contendo metadata (`event_type`, `tenant_id`, `correlation_id`, `occurred_at`) + corpo do evento + header `X-CRM-Signature: sha256=<hmac>` calculado com o segredo cadastrado.
- **AC-11.1.3** 🔴 **Given** o webhook destinatário responde com status 5xx ou timeout, **When** a entrega falha, **Then** o sistema reprograma a entrega seguindo a política de **5 tentativas com backoff exponencial (30s, 2min, 10min, 1h, 6h)** — Q16. Cada tentativa gera entrada em log de entregas com `attempt_number`, `response_status`, `latency_ms`.
- **AC-11.1.4** 🟡 **Given** a entrega foi bem-sucedida, **When** olho o log, **Then** vejo entrada com payload, headers, response do destinatário (status + body truncado em 1KB) e latência. Eventos auditáveis: `WebhookEntregue{webhook_id, event_type, attempt, latency_ms}`.
- **AC-11.1.5** 🟡 **Given** os 5 retries esgotaram, **When** a entrega falha definitivamente, **Then** o evento vai para **Dead Letter Queue** acessível ao Admin Clínica em "Webhooks → Falhas", com retenção de **30 dias** (Q16). Evento `WebhookFalhou{webhook_id, event_type, final_attempt, last_error}` é emitido. Após 30d, descarte automático com audit log.
- **AC-11.1.6** 🟡 **Given** quero re-enviar uma entrega falhada da DLQ, **When** clico em "Reenviar" na linha do log, **Then** o sistema enfileira nova tentativa com novo `correlation_id`; emite `WebhookReagendado{webhook_id, original_attempt_id, new_attempt_id}`.
- **AC-11.1.7** 🟡 **Given** o payload contém referências a entidades do CRM, **When** o sistema serializa, **Then** o payload **não contém PII clínica de pacientes que não consentiram compartilhamento externo** (campo `patient.communication_preferences.share_with_integrations` consultado por destinatário). Pacientes que negaram esse consentimento aparecem como `{patient_id, name: "<consent_withheld>"}`.
- **AC-11.1.8** 🔴 **Given** o catálogo de eventos disponíveis para subscrição, **When** o Admin Clínica escolhe quais assinar, **Then** as opções incluem (Q17): `PacienteCriado`, `PacienteAtualizado`, `AgendamentoCriado`, `AgendamentoConfirmado`, `AgendamentoCancelado`, `AgendamentoReagendado`, `ConsultaRealizada`, `PrescricaoCriada`, `PrescricaoCancelada`, `ReceitaRenovada`, `ReceitaProximaDoVencimento`, `RetornoDisparado`, `EscalonamentoParaHumano`. **Excluídos** do catálogo público: eventos de campanha (Módulo 1), webhooks (auto-referência), audit logs, decisões da IA com prompt completo.

---

#### User Story 3.2 — API Pública Documentada (US-11.2) — Prioridade P3

**Como** Parceiro técnico
**Quero** consumir a API pública do CRM
**Para que** eu construa integrações sob medida

**Por que essa prioridade**: complemento de US-11.1 — entrega o canal de entrada. Importante para ecossistema mas não bloqueia uso do produto para o cliente médio.

**Independent Test**: gerar token de API para um tenant, fazer requisição autenticada a um endpoint público, validar rate limit, ler documentação OpenAPI publicada e confirmar versionamento.

**Acceptance Scenarios**:

- **AC-11.2.1** 🔴 **Given** documentação da API, **When** acesso o endpoint público de docs, **Then** vejo especificação OpenAPI/Swagger renderizada com: autenticação requerida, endpoints versionados (`/v1/...`), schemas de request/response, exemplos, códigos de erro padronizados.
- **AC-11.2.2** 🔴 **Given** estou autenticado como Admin Clínica, **When** vou em "Configurações → Tokens de API", **Then** posso gerar tokens com escopo de leitura/escrita e nomeá-los; ver tokens emitidos (com hash, sem plain text — padrão Fase 4), revogar tokens; ações geram audit_logs.
- **AC-11.2.3** 🔴 **Given** uma requisição externa autenticada chega à API pública, **When** o sistema autentica, **Then** aceita **token Sanctum estático** no header `Authorization: Bearer <token>` (default — Q18) OU JWT emitido via **OAuth 2.0 Client Credentials** (opt-in para tenants enterprise — `POST /oauth/token` com `client_id` + `client_secret` retorna JWT de 1h). Em qualquer caso, tenant é resolvido pelo token, **nunca** por parâmetro de URL.
- **AC-11.2.4** 🔴 **Given** múltiplas requisições, **When** o consumo ultrapassa o limite do plano, **Then** o sistema responde 429 com headers `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`. Limites primários **por token de tenant** (Q15): básico 100 req/min, pro 1000 req/min, enterprise 5000 req/min. Hard cap secundário **por IP** (10k req/min) como defesa anti-DDoS.
- **AC-11.2.5** 🔴 **Given** versionamento, **When** é introduzida uma mudança breaking, **Then** o sistema mantém `/v1/` operacional e introduz `/v2/`. Endpoints obsoletos retornam `Deprecation: true` e `Sunset: <data>` em headers. Política de deprecation: mínimo 6 meses antes do shutdown.
- **AC-11.2.6** 🟡 **Given** o escopo da API pública v1, **When** o Admin Clínica explora endpoints, **Then** apenas recursos do catálogo aprovado são acessíveis (Q14): pacientes (read+write), agendamentos (read+write), mensagens (read-only), receituários (read-only, controladas mascaradas), tipos de atendimento (read-only), profissionais (read-only). Recursos fora do escopo retornam 404 (não 401) para não vazar existência.
- **AC-11.2.7** 🟡 **Given** receita controlada (Fase 7), **When** consultada via API pública, **Then** payload retorna **mascarada** (apenas tipo, status, datas — sem medicamento/posologia) independentemente do escopo do token. Política única e estrita aplicada: API pública nunca expõe conteúdo de controladas.
- **AC-11.2.8** 🟡 **Given** uma requisição POST/PUT/DELETE bem-sucedida via API pública, **When** persistida, **Then** o registro carrega `created_via='public_api'` + `api_token_id` em audit metadata. Permite rastrear ações de integração vs. ações de usuário interno.
- **AC-11.2.9** 🟢 **Given** sandbox para parceiros, **When** o parceiro precisa testar, **Then** existe ambiente de sandbox com dados sintéticos; tokens de sandbox identificam-se por prefixo distinto e nunca acessam dados de produção.

---

#### Eventos de Domínio emitidos pelo Módulo 3

- `WebhookConfigurado{webhook_id, tenant_id, events_subscribed, created_by}`
- `WebhookEntregue{webhook_id, event_type, attempt_number, latency_ms, response_status}`
- `WebhookFalhou{webhook_id, event_type, final_attempt, last_error}`
- `WebhookReagendado{webhook_id, original_attempt_id, new_attempt_id, scheduled_for}`
- `TokenApiEmitido{token_id, tenant_id, scope, emitted_by, auth_method}` (sem plain-text; `auth_method ∈ {sanctum, oauth_client_credentials}`)
- `TokenApiRevogado{token_id, tenant_id, revoked_by, reason}`

#### Edge Cases — Módulo 3

- Webhook URL retorna 200 mas demora 30s+ → considerar timeout (limite configurável, default 10s); aciona retry.
- Webhook em destinatário malicioso que tenta acessar IP interno → URLs com IP privado/localhost rejeitadas no cadastro.
- Token de API revogado mas requisição ainda em vôo → bloqueio aplicado quando a requisição chega ao backend (válido um curto período de race).
- Tenant suspenso → API pública responde 503 `tenant_suspended` para todas as requisições.
- Versão `/v1/` em endpoint não existente (typo) → 404 com mensagem "endpoint not in this version" + sugestão de versões disponíveis.
- OAuth Client Credentials habilitado mas `client_secret` rotacionado → JWTs emitidos antes da rotação continuam válidos até expiração natural (1h); chamadas subsequentes exigem novo `client_secret`.

---

### Módulo 4 — Painel Super Admin (Épico 12, Prioridade P1)

A operação da plataforma multi-tenant exige um painel administrativo global para a equipe de plataforma (Super Admin) — gerir tenants, planos, billing e visualizar saúde do SaaS. Este módulo entrega esse painel.

#### User Story 4.1 — Gestão de Tenants (US-12.1) — Prioridade P1

**Como** Super Admin
**Quero** listar e gerenciar todos os tenants da plataforma
**Para que** eu opere o SaaS centralizadamente

**Por que essa prioridade**: sem painel Super Admin, a equipe de plataforma só consegue operar via SQL direto (inaceitável). É P1 absoluto para go-live.

**Independent Test**: logar como Super Admin, listar tenants, filtrar por status, suspender um tenant inadimplente, reativar outro, impersonate em um para suporte — validar audit_logs em cada ação.

**Acceptance Scenarios**:

- **AC-12.1.1** 🔴 **Given** estou autenticado como Super Admin, **When** acesso a listagem de tenants, **Then** vejo todos os tenants com colunas: nome, slug, plano atual, status (`trial` / `ativo` / `inadimplente` / `suspenso` / `cancelado`), data de cadastro, MRR estimado.
- **AC-12.1.2** 🔴 **Given** a listagem, **When** aplico filtros (status, plano, data de cadastro entre, com/sem inadimplência), **Then** o resultado reflete interseção AND com paginação determinística.
- **AC-12.1.3** 🔴 **Given** estou em um tenant da lista, **When** clico em "Suspender", **Then** o tenant transita para status `suspenso`, sessões ativas dos usuários do tenant são revogadas, jobs em fila do tenant ficam pausados, e o evento `TenantSuspenso{tenant_id, suspended_by, reason}` é emitido + audit_log (motivo ≥10 chars obrigatório).
- **AC-12.1.4** 🔴 **Given** um tenant suspenso, **When** clico em "Reativar", **Then** o tenant volta para `ativo`, jobs em fila são retomados, usuários podem fazer login novamente; evento `TenantReativado{tenant_id, reactivated_by}` é emitido.
- **AC-12.1.5** 🔴 **Given** quero dar suporte direto, **When** clico em "Impersonate", **Then** o sistema gera sessão com identidade de Super Admin **mas com contexto do tenant escolhido**, com **acesso total** ao que Admin Clínica daquele tenant veria (incluindo receitas controladas — Q19), exibe **banner amarelo persistente** "MODO IMPERSONATE — tenant {nome} — você está visualizando como suporte" em todas as telas, e emite `ImpersonateIniciado{super_admin_id, tenant_id, started_at, scope='full'}` em audit_logs. Adicionalmente, cada tela visitada durante a sessão gera audit_log granular `ImpersonateTelaVisitada{super_admin_id, tenant_id, route, visited_at}`.
- **AC-12.1.6** 🔴 **Given** sessão de impersonate ativa, **When** encerro (logout ou clique em "Sair do impersonate"), **Then** o evento `ImpersonateEncerrado{super_admin_id, tenant_id, ended_at, duration, screens_visited_count}` é emitido + audit_log. Sessão de Super Admin é restaurada.
- **AC-12.1.7** 🟡 **Given** o tenant, **When** abro a aba "Métricas", **Then** vejo (i) profissionais ativos, (ii) consumo de mensagens IA no mês corrente, (iii) MRR estimado, (iv) últimos 30 dias de uso (logins, conversas, agendamentos).
- **AC-12.1.8** 🟡 **Given** um tenant inadimplente >30d, **When** abro a ficha, **Then** vejo alerta visual + sugestão de "Suspender + notificar". A política de cancelamento automático após N dias de inadimplência fica fora do escopo desta fase (pode ser feature futura).
- **AC-12.1.9** 🟡 **Given** quero criar um tenant manualmente, **When** clico em "Novo tenant", **Then** o formulário aceita (Q23): nome, slug, e-mail do Admin Clínica inicial, plano, e **`billing_mode ∈ {stripe, offline_invoice}`**. Modo `offline_invoice` marca o tenant como "enterprise — billing externo" e ignora gates de cobrança automática (suporta clientes que pagam via NF/boleto fora da plataforma). Emite `TenantCriadoPorSuperAdmin{tenant_id, created_by, billing_mode}` em audit_logs.
- **AC-12.1.10** 🔴 **Given** cancelo um tenant, **When** confirmo o cancelamento, **Then** o tenant transita para `cancelado`, e a política de retenção pós-cancelamento (Q20) é aplicada por categoria de dado: (i) billing/financeiro preservado 5 anos, (ii) receitas controladas preservadas 2 anos (Portaria 344/98), (iii) audit logs preservados 1 ano, (iv) dados de paciente (cadastro, mensagens, agendamentos) anonimizados em **90 dias** após cancelamento (30d de janela "undo" + 60d de grace), (v) configurações do tenant deletadas em 30d. Evento `TenantCancelado{tenant_id, canceled_at, canceled_by, retention_policy='differentiated_per_category'}` é emitido.

---

#### User Story 4.2 — Configuração de Planos Globais (US-12.2) — Prioridade P1

**Como** Super Admin
**Quero** criar e editar planos comerciais
**Para que** eu evolua a oferta sem deploy de código

**Por que essa prioridade**: sem planos editáveis, time comercial não pode lançar promoções/novos tiers sem ticket de eng — bloqueia go-to-market.

**Independent Test**: criar plano novo com 5 profissionais inclusos e 10.000 msgs IA inclusas, criar tenant nesse plano, fazer upgrade depois, validar que tenant existente em plano antigo não foi impactado (snapshot versioning).

**Acceptance Scenarios**:

- **AC-12.2.1** 🔴 **Given** estou em "Planos", **When** clico em "Novo plano", **Then** preencho: nome, preço base (mensal), profissionais inclusos, mensagens IA inclusas, valor por mensagem excedente, **limite de envio diário de campanha** (Q2 — default sugerido por tier: básico 200, pro 1000, enterprise 5000), **limite de rate da API pública** (Q15 — básico 100 req/min, pro 1000, enterprise 5000), recursos habilitados (toggle de módulos disponíveis), flag `ativo` para visibilidade no onboarding.
- **AC-12.2.2** 🔴 **Given** um plano em uso por tenants existentes, **When** edito o plano, **Then** a alteração cria **nova versão do plano** (snapshot versioning); tenants existentes **continuam vinculados à versão original**; novos tenants veem a versão mais recente.
- **AC-12.2.3** 🟡 **Given** o Super Admin altera o plano de um tenant específico, **When** confirma a alteração, **Then** o sistema (i) recalcula proration via integração com Stripe (já existente da Fase 0), (ii) gera evento `PlanoAlteradoPeloSuperAdmin{tenant_id, old_plan_id, new_plan_id, changed_by, effective_at}`, (iii) registra audit_log com motivo da alteração (texto livre obrigatório, ≥10 chars).
- **AC-12.2.4** 🟡 **Given** um plano marcado como `ativo=false`, **When** o fluxo público de onboarding lista planos, **Then** este plano **não aparece**; mas continua existindo para tenants que já o usam.
- **AC-12.2.5** 🟡 **Given** quero descontinuar um plano, **When** marco como `ativo=false` E peço migração de tenants, **Then** o sistema lista todos os tenants no plano e oferece bulk-action "Migrar para plano X" (com confirmação por tenant).
- **AC-12.2.6** 🟢 **Given** um plano com recursos habilitados, **When** o tenant tenta usar uma feature **não habilitada** pelo seu plano, **Then** o sistema retorna 402 (`payment_required` / `feature_not_in_plan`) com sugestão de upgrade — gate de uso já é da Fase 0; este AC apenas garante que a UI de planos reflete isso.

---

#### User Story 4.3 — Métricas Globais da Plataforma (US-12.3) — Prioridade P2

**Como** Super Admin
**Quero** ver KPIs globais do SaaS (MRR, ARR, churn, conversão trial→pago)
**Para que** eu acompanhe a saúde estratégica do negócio

**Por que essa prioridade**: P2 porque é insight, não operação — útil mas não bloqueia transações.

**Independent Test**: gerar histórico de 90 dias com tenants em trial / ativo / cancelado, abrir o dashboard global e validar MRR/ARR, churn rate e conversão trial → pago.

**Acceptance Scenarios**:

- **AC-12.3.1** 🔴 **Given** sou Super Admin, **When** abro "Métricas globais", **Then** vejo: MRR, ARR, número de tenants ativos, **churn rate primário** = `tenants_cancelados_no_período / tenants_ativos_início_período` (Q21 — apenas cancelamentos explícitos), **revenue churn** complementar (incluindo downgrades de plano), conversão trial → pago, consumo mensal total de mensagens IA (custo de plataforma).
- **AC-12.3.2** 🔴 **Given** a apresentação das métricas globais, **When** sistema agrega, **Then** **nenhuma métrica expõe dados individuais de paciente** — apenas agregados por tenant. Princípio II (Isolamento Multi-Tenant) garante que esse painel não vira atalho para vazamento entre tenants.
- **AC-12.3.3** 🟡 **Given** quero ver tendência, **When** seleciono período, **Then** vejo séries temporais (12 meses) para cada KPI principal.
- **AC-12.3.4** 🟡 **Given** anomalias detectáveis, **When** ocorrem, **Then** o sistema (Q22): (i) registra no painel "Anomalias detectadas" com threshold configurável por categoria, (ii) envia **notificação para inbox interna do Super Admin** + (iii) **e-mail crítico** ao endereço de plataforma cadastrado. Categorias monitoradas: queda de conversão trial→pago > 20% WoW, consumo de IA de tenant > 10x média histórica, taxa de falha de webhook > 50% em 1h, tenant em inadimplência > 30 dias.
- **AC-12.3.5** 🟢 **Given** quero exportar, **When** clico em "Exportar", **Then** CSV com todos os KPIs por mês para análise externa.

---

#### Eventos de Domínio emitidos pelo Módulo 4

- `TenantCriadoPorSuperAdmin{tenant_id, created_by, billing_mode}`
- `TenantSuspenso{tenant_id, suspended_by, reason, suspended_at}`
- `TenantReativado{tenant_id, reactivated_by, reactivated_at}`
- `TenantCancelado{tenant_id, canceled_by, canceled_at, retention_policy}`
- `PlanoAlteradoPeloSuperAdmin{tenant_id, old_plan_id, new_plan_id, changed_by, effective_at, reason}`
- `ImpersonateIniciado{super_admin_id, tenant_id, started_at, scope}`
- `ImpersonateTelaVisitada{super_admin_id, tenant_id, route, visited_at}` (audit-only — granular)
- `ImpersonateEncerrado{super_admin_id, tenant_id, ended_at, duration, screens_visited_count}`
- `PlanoCriado{plan_id, plan_version, created_by}`
- `PlanoEditado{plan_id, old_version, new_version, changed_by}`
- `AnomaliaDetectada{categoria, tenant_id?, severity, threshold_breached, observed_value}`

#### Edge Cases — Módulo 4

- Super Admin tenta impersonate em tenant suspenso → permitido (necessário para diagnosticar antes de reativar), mas com banner adicional "ATENÇÃO: tenant suspenso".
- Tentativa de duas sessões simultâneas de impersonate no mesmo tenant por Super Admins distintos → permitido (cada um tem audit log próprio); banner mostra "Outro membro da equipe também está em modo impersonate aqui".
- Plano deletado fisicamente (não apenas `ativo=false`) → bloqueado pelo sistema; tenants vinculados ficariam órfãos.
- Métricas globais com >5000 tenants → dashboard usa materialized views atualizadas a cada hora (não query ao vivo).
- Anomalia detectada em consumo de IA pode indicar prompt injection / abuse → notificação automática prioritária (severity=critical).
- Tenant criado manualmente em `billing_mode=offline_invoice` → Cashier não emite invoice nem cobra; suspensão por inadimplência fica a critério do Super Admin (manual).

---

### Módulo 5 — Privacidade, Segurança e LGPD (Épico 13, Prioridade P1)

A plataforma trata dados pessoais sensíveis (saúde) e está sujeita à LGPD desde o primeiro tenant. Fases anteriores aplicaram controles parciais (pseudonimização Fases 4/5/7, audit logs Fase 1, consentimento embrionário Fase 3). Este módulo formaliza, instrumenta e audita esses controles + adiciona painel de privacidade, fluxo de direito ao esquecimento e registro granular de consentimento.

#### User Story 5.1 — Consentimento e Opt-in (US-13.1) — Prioridade P1

**Como** Paciente
**Quero** dar e revogar consentimento explícito para uso de dados e comunicações
**Para que** meus direitos LGPD sejam respeitados

**Por que essa prioridade**: NON-NEGOTIABLE — sem registro estruturado de consentimento, **toda campanha do Módulo 1 vira risco regulatório** e a plataforma não pode operar.

**Independent Test**: paciente envia primeira mensagem em qualquer canal, recebe pergunta de consentimento, responde "aceito" para marketing especificamente, validar que `consentimento_registrado` foi persistido com canal/finalidade=marketing/data; depois envia "/sair", validar que apenas marketing foi revogado (transacional continua ativo); Admin Clínica abre painel de privacidade e exporta registros.

**Acceptance Scenarios**:

- **AC-13.1.1** 🔴 **Given** novo paciente envia primeira mensagem em qualquer canal vinculado à clínica, **When** a Fase 3 cria o registro de paciente, **Then** o sistema (i) registra **consentimento transacional implícito** (Q24 — confirmação de consulta, alerta de receita, resposta a mensagens iniciadas pelo paciente são permitidos), (ii) envia mensagem solicitando **opt-in explícito de marketing** (campanhas, lembretes proativos), (iii) aguarda resposta antes de qualquer envio de marketing.
- **AC-13.1.2** 🔴 **Given** paciente responde aceitando marketing, **When** o sistema processa, **Then** emite `ConsentimentoRegistrado{patient_id, channel, finalidade='marketing', granted_at, evidence_message_id, terms_version}` e persiste em audit_logs. Caso responda "não", emite `ConsentimentoRecusado` com mesma estrutura — bloqueia futuras campanhas mas mantém transacional ativo.
- **AC-13.1.3** 🔴 **Given** paciente envia `/sair` em canal, **When** o sistema processa, **Then** **revoga apenas marketing** (Q25 — preserva transacional para evitar quebrar canal de utilidade) e emite `ConsentimentoRevogado{patient_id, channel, finalidade='marketing', revoked_at, evidence_message_id}`. Para revogação total (incluindo transacional), paciente envia `/sair tudo` ou usa formulário de privacidade.
- **AC-13.1.4** 🔴 **Given** o paciente não respondeu o pedido de opt-in marketing ainda, **When** qualquer campanha (Módulo 1) tenta enviar, **Then** o dispatcher **bloqueia** com motivo `no_marketing_opt_in`. Comunicações transacionais (Fase 3, 5, 7) seguem normalmente — consentimento implícito ao cadastro cobre.
- **AC-13.1.5** 🟡 **Given** sou Admin Clínica, **When** acesso "Privacidade → Consentimentos", **Then** vejo painel listando pacientes com: status de consentimento por finalidade (transacional / marketing / pesquisa — placeholder), data de registro, canal de coleta, link para evidência (mensagem do paciente).
- **AC-13.1.6** 🟡 **Given** painel de privacidade, **When** clico em "Exportar registros", **Then** o sistema gera CSV/JSON com todos os consentimentos do tenant (sem dados clínicos) + evento `AuditoriaPrivacidadeExportada{exported_by_user_id, exported_at, patient_ids_count}` em audit_logs.
- **AC-13.1.7** 🔴 **Given** o registro de consentimento, **When** persistido, **Then** carrega: `patient_id`, `channel`, `finalidade` ∈ `{transacional, marketing, pesquisa}`, `granted_at` / `revoked_at`, `evidence` (msg_id ou snapshot), `version_of_terms_accepted` (versão dos termos vigentes na data). Permite provar conformidade retroativamente.

---

#### User Story 5.2 — Direito ao Esquecimento (US-13.2) — Prioridade P1

**Como** Paciente
**Quero** solicitar exclusão dos meus dados
**Para que** o tratamento cesse conforme LGPD Art. 18º

**Por que essa prioridade**: NON-NEGOTIABLE — direito legal cuja violação gera multa LGPD. Sem isso, não pode operar.

**Independent Test**: paciente acessa formulário/canal específico, solicita esquecimento; Admin Clínica vê tarefa na inbox; admin executa o fluxo dentro de 15 dias úteis; sistema anonimiza/deleta campos conforme mapa Q26; paciente recebe confirmação por e-mail; tentativa de leitura subsequente do paciente retorna registros anonimizados.

**Acceptance Scenarios**:

- **AC-13.2.1** 🔴 **Given** o paciente quer solicitar esquecimento, **When** acessa o canal de solicitação (formulário público / mensagem no canal / e-mail dedicado), **Then** o sistema cria tarefa formal `DireitoEsquecimentoSolicitado{patient_id, requested_at, deadline_at (D+15 dias úteis), channel_of_request}` e emite o evento + audit_log.
- **AC-13.2.2** 🔴 **Given** a solicitação foi registrada, **When** o Admin Clínica abre a inbox interna, **Then** vê tarefa prioritária com countdown de dias úteis restantes até o deadline.
- **AC-13.2.3** 🔴 **Given** o Admin Clínica executa o esquecimento, **When** confirma a execução, **Then** o sistema aplica o mapa de anonimização Q26: (i) **anonimizados com placeholder**: `nome→"Paciente Anonimizado #{id}"`, `cpf→"000.000.000-00"`, `rg→null`, `telefone→"00000000000"`, `email→null`, `data_nascimento→1900-01-01`, `convenio_carteirinha→null`; (ii) **deletados fisicamente**: `foto_url` (arquivo + URL), `endereco_completo`, `anotacoes_livres`, `mensagens_corpo` (metadados de conversa preservados); (iii) **preservados por obrigação legal** com banner "Dados preservados — retenção até DD/MM/AAAA": prescrições controladas (2 anos / Portaria 344/98), registros financeiros (5 anos / Lei 12.682/2012), audit logs (1 ano / LGPD Art. 16), consentimentos (5 anos pós-revogação). Emite `DireitoEsquecimentoExecutado{patient_id, executed_at, executed_by, fields_anonymized[], fields_deleted[], fields_preserved_reason[]}`.
- **AC-13.2.4** 🔴 **Given** o esquecimento foi executado, **When** o sistema confirma, **Then** envia e-mail ao requerente confirmando a execução, com lista das categorias de dados afetadas (sem expor dados em si) e prazo de retenção dos dados preservados por obrigação legal.
- **AC-13.2.5** 🟡 **Given** o paciente foi anonimizado, **When** qualquer leitura subsequente acessa o registro, **Then** o sistema retorna campos com valores placeholder (definidos em Q26). Receituários controlados e billing preservados aparecem com banner "Dados preservados por obrigação legal" + data fim da retenção.
- **AC-13.2.6** 🟡 **Given** uma solicitação está aberta, **When** o deadline se aproxima, **Then** sistema notifica Admin Clínica (Q27): **D-5** (5 dias úteis antes) via **inbox interna**; **D-2** (2 dias úteis antes) via **inbox interna + e-mail + alerta visual persistente** no topo do painel até execução.
- **AC-13.2.7** 🟡 **Given** o deadline expirou sem execução, **When** o sistema detecta, **Then** o status da tarefa vira `vencido_sem_resposta`, dispara alerta crítico ao Super Admin (Q22 — anomalia) e mantém alerta visual persistente para Admin Clínica até resolução. Não há execução automática (a anonimização exige confirmação humana).
- **AC-13.2.8** 🟡 **Given** o paciente solicita **portabilidade de dados** (Art. 18 LGPD inciso V), **When** o Admin Clínica processa (Q28), **Then** o sistema gera **arquivo JSON estruturado** com escopo: (i) dados cadastrais (nome, CPF, telefone, e-mail, endereço, convênio), (ii) timeline pública (consultas realizadas com data/tipo/profissional, mensagens com metadados — corpo só se não houve opt-out), (iii) agendamentos (passados e futuros), (iv) receituários (mascarados se controladas). Entrega via **URL assinada de 7 dias** + e-mail de notificação. Prazo de 15 dias úteis (mesmo deadline). Audit: `PortabilidadeDadosSolicitada` e `PortabilidadeDadosExecutada`.

---

#### User Story 5.3 — Pseudonimização de Prompts da IA (US-13.3) — Prioridade P1

**Como** Plataforma
**Quero** garantir que dados pessoais sensíveis sejam substituídos por placeholders antes do envio ao LLM
**Para que** dados não vazem para o provedor de IA

**Por que essa prioridade**: NON-NEGOTIABLE — Princípio I; já parcialmente implementado nas Fases 4, 5 e 7. Esta US **formaliza, audita e documenta**.

**Independent Test**: revisar a lista de campos pseudonimizados por evento emitido para a IA em todas as fases anteriores; rodar a auditoria automatizada (Q29); gerar relatório de conformidade que prova ausência de PII em qualquer payload que sai para o LLM.

**Acceptance Scenarios**:

- **AC-13.3.1** 🔴 **Given** qualquer payload que sai para o provedor de IA (LLM), **When** o sistema constrói o prompt, **Then** **CPF, RG, número de carteirinha de convênio, telefone, e-mail, foto e endereço completo** são substituídos por placeholders (`<paciente_id>`, `<documento>`, `<telefone>`, `<convenio_id>`, etc.). Mapeamento reversível mantido **apenas em memória de processo**, nunca persistido.
- **AC-13.3.2** 🔴 **Given** os logs do LLM (entrada e saída) são persistidos para auditoria (Princípio III), **When** consultados, **Then** **não contêm** dados pessoais identificáveis em texto plano. Qualquer leitura subsequente desses logs reproduz apenas placeholders.
- **AC-13.3.3** 🔴 **Given** a auditoria retroativa de cobertura (Q29 — abordagem dupla), **When** executada, **Then**: (i) **varredura estática via reflection** estende o padrão `ContainsNoClinicalData` da Fase 7 — gate de CI falha o build se qualquer evento consumido pela IA não declarar a marker interface com lista explícita de propriedades; (ii) **replay contra detector de PII** roda em job semanal sobre amostra randômica de 1% dos eventos persistidos (com peso para os mais recentes), aplicando regex de CPF `\d{3}\.?\d{3}\.?\d{3}-?\d{2}`, telefone `\(?\d{2}\)?\s?\d{4,5}-?\d{4}`, e-mail `[\w.-]+@[\w.-]+` — qualquer hit gera ticket crítico. Relatório consolidado acessível no painel de Privacidade.
- **AC-13.3.4** 🔴 **Given** novo evento de domínio é adicionado em fase futura e consumido pela IA, **When** o desenvolvedor cria, **Then** o sistema **falha o teste de CI** se o evento não declarar explicitamente conformidade com `ContainsNoClinicalData` + lista de propriedades. Gate bloqueia merge sem conformidade — extensão direta do padrão estabelecido na Fase 7.
- **AC-13.3.5** 🟡 **Given** painel de auditoria de privacidade, **When** Admin Clínica acessa, **Then** vê (i) relatório de cobertura de pseudonimização (lista de eventos × campos × status conforme/risco), (ii) número de prompts processados no mês, (iii) % de prompts com PII detectada por scan automático (esperado: 0%), (iv) lista de eventos não-conformes (esperado: vazio), (v) timestamp da última auditoria de replay semanal.
- **AC-13.3.6** 🔴 **Given** o mapeamento reversível em memória, **When** o processo termina, **Then** o mapeamento é descartado (sem persistência em disco/cache). Garantia de que mesmo um vazamento de Redis/DB não expõe a relação placeholder ↔ valor real.
- **AC-13.3.7** 🟢 **Given** Sentry / observabilidade captura erros do LLM, **When** algum payload é capturado em stack trace, **Then** o sistema aplica scrub antes de enviar ao Sentry — placeholders aparecem nos relatórios de erro, não o dado real.

---

#### Eventos de Domínio emitidos pelo Módulo 5

- `ConsentimentoRegistrado{patient_id, channel, finalidade, granted_at, evidence_message_id, terms_version}`
- `ConsentimentoRecusado{patient_id, channel, finalidade, refused_at, evidence_message_id}`
- `ConsentimentoRevogado{patient_id, channel, finalidade, revoked_at, evidence_message_id, scope}`
- `DireitoEsquecimentoSolicitado{patient_id, requested_at, deadline_at, channel_of_request}`
- `DireitoEsquecimentoExecutado{patient_id, executed_at, executed_by, fields_anonymized[], fields_deleted[], fields_preserved_reason[]}`
- `PortabilidadeDadosSolicitada{patient_id, requested_at, deadline_at}`
- `PortabilidadeDadosExecutada{patient_id, executed_at, executed_by, file_signed_url_id, file_size_bytes}`
- `AuditoriaPrivacidadeExportada{exported_by_user_id, exported_at, patient_ids_count, scope}`
- `PoliticaPseudonimizacaoAuditada{audited_at, audited_by, scope_event_types[], findings[], total_events_scanned}`

#### Edge Cases — Módulo 5

- Paciente solicita esquecimento mas tem receitas controladas dentro da janela de retenção legal (2 anos pela Portaria 344/98) → AC-13.2.5 cobre via "campos preservados por obrigação legal"; UI exibe data fim da retenção.
- Paciente revoga marketing, depois envia nova mensagem ao canal → primeiro contato vira fluxo de consentimento marketing novamente (sistema não assume "consentimento implícito" por reativação).
- Paciente anonimizado é referenciado em audit log de outro paciente → audit log preservado; apenas o nome aparece como placeholder no histórico.
- Auditoria de pseudonimização detecta novo evento não conforme em fase futura → CI/CD bloqueia merge automaticamente; ticket aberto para o time da fase.
- Paciente tem múltiplos canais e revoga apenas em um (ex.: `/sair` no Instagram) → revogação se aplica **a todos os canais** (a revogação é por paciente×finalidade, não por canal específico) — alinha com expectativa do paciente ("não quero mais receber marketing").
- Solicitação de esquecimento partindo de canal não vinculado ao paciente (ex.: terceiro alegando ser representante) → fluxo formal exige confirmação de identidade antes da execução; sem confirmação, tarefa fica em status `pending_verification`.
- Portabilidade gera arquivo >50MB → fragmenta em múltiplos arquivos zipados; URL assinada lista todos.
- URL assinada de portabilidade expira (7d) sem download → paciente pode solicitar novo link via formulário; tarefa de geração refaz; deadline LGPD não reinicia.

---

## 2. Requirements *(mandatory)*

### Functional Requirements (mapeamento RF do PRD)

#### Módulo 1 — Campanhas

- **FR-9.1**: O sistema MUST permitir criar, editar (apenas se `scheduled`), pré-visualizar, agendar, disparar e cancelar campanhas tenant-scoped.
- **FR-9.2**: O sistema MUST segmentar pacientes elegíveis por: tempo de inatividade calculado por **última `ConsultaRealizada`** (Q1), idade, sexo, tags, último profissional, último procedimento.
- **FR-9.3**: O sistema MUST validar opt-in **de marketing** (Q24) por destinatário antes de cada envio individual. Sem opt-in → exclusão automática.
- **FR-9.4**: O sistema MUST usar templates aprovados pela Meta para envios fora da janela de 24h em canais WhatsApp; consultar status do template **antes** de cada disparo.
- **FR-9.5**: O sistema MUST respeitar `business_hours` do tenant (Q7) em qualquer disparo de campanha; fora do horário, enfileirar para próximo início válido. Não há blackout sistêmico extra.
- **FR-9.6**: O sistema MUST aplicar **limite de envio diário definido pelo plano de assinatura** (Q2) com fragmentação automática de batch caso o público exceda. Admin pode reduzir o limite mas não aumentar.
- **FR-9.7**: O sistema MUST manter relatório de campanha com: enviados, bloqueados (por motivo), entregues, lidos, respondidos, agendamentos atribuíveis (≤7d após mensagem). Atualização via polling cliente a cada 30s durante disparo + consolidação ao fim do batch (Q6).
- **FR-9.8**: O sistema MUST incluir link/comando de descadastro em **toda** mensagem de campanha não-transacional.
- **FR-9.9**: O sistema MUST processar comando `/sair` em qualquer canal vinculado e revogar **opt-in de marketing** imediatamente (Q25). Comando `/sair tudo` revoga marketing + transacional.
- **FR-9.10**: Cada campanha tem **canal único** (Q3); multi-canal exige múltiplas campanhas.

#### Módulo 2 — Relatórios

- **FR-10.1**: O sistema MUST oferecer três visões: Dashboard Executivo, Relatórios Operacionais, Relatórios Clínicos — tenant-scoped.
- **FR-10.2**: O sistema MUST permitir filtros de período (7d / 30d / 90d / customizado, máx 12 meses).
- **FR-10.3**: O sistema MUST gravar audit_log em toda exportação CSV/PDF, com `exported_by`, `filters_applied`, `formato`, `tipo_relatorio`.
- **FR-10.4**: O sistema MUST limitar acesso a dados por perfil (Q13): Médico vê apenas dados onde é `professional_id`/`profissional_responsavel`; Admin Clínica e proprietário veem dados completos; Atendente/Recepcionista vê dados completos exceto conteúdo de receitas controladas.
- **FR-10.5**: O sistema MUST não modificar dados de outros domínios. Read-only é hard constraint.
- **FR-10.6**: O sistema MUST usar agregações pré-computadas atualizadas **a cada hora** (Q9) para janelas ≥ 7 dias; janelas ≤ 24h podem usar queries on-demand.
- **FR-10.7**: O sistema MUST nunca expor dados de outros tenants nas métricas globais (responsabilidade do Módulo 4).
- **FR-10.8**: O sistema MUST renderizar drill-down nos KPIs do dashboard (Q10) abrindo lista filtrada correspondente.
- **FR-10.9**: O sistema MUST exibir variação percentual contra período anterior em cada KPI (Q11).
- **FR-10.10**: A exportação PDF MUST ser layout formatado próprio (Q12), não snapshot visual.

#### Módulo 3 — Integrações

- **FR-11.1**: O sistema MUST permitir CRUD de configurações de webhook tenant-scoped, com URL HTTPS + segredo + eventos assinados.
- **FR-11.2**: O sistema MUST assinar todo payload de webhook com HMAC SHA-256 no header `X-CRM-Signature`.
- **FR-11.3**: O sistema MUST aplicar **5 tentativas com backoff exponencial (30s, 2min, 10min, 1h, 6h)** em falhas de webhook (Q16); manter log de entregas com payload, headers, response.
- **FR-11.4**: O sistema MUST oferecer **Dead Letter Queue** com retenção de 30 dias para webhooks que esgotaram retries, acessível ao Admin Clínica para reenvio manual (Q16).
- **FR-11.5**: O sistema MUST oferecer API pública versionada (`/v1/`) com documentação OpenAPI publicada.
- **FR-11.6**: O sistema MUST autenticar requisições da API pública via **Sanctum estático (default)** OU **OAuth 2.0 Client Credentials (opt-in enterprise)** — Q18. Tenant resolvido pelo token, nunca por URL.
- **FR-11.7**: O sistema MUST aplicar rate limiting **por token de tenant** (Q15) com limites diferenciados por plano: básico 100 req/min, pro 1000 req/min, enterprise 5000 req/min; hard cap por IP 10k req/min como defesa anti-DDoS.
- **FR-11.8**: O sistema MUST limitar o escopo da API pública v1 aos recursos (Q14): pacientes (R+W), agendamentos (R+W), mensagens (R), receituários (R, controladas mascaradas), tipos de atendimento (R), profissionais (R). Demais retornam 404.
- **FR-11.9**: O sistema MUST oferecer catálogo de eventos webhook (Q17) com 13 eventos materiais; excluídos: eventos de campanha, webhooks, audit logs, decisões de IA com prompt.
- **FR-11.10**: O sistema MUST registrar audit_log em operações de escrita via API pública, com `created_via='public_api'` + `api_token_id` + `auth_method`.
- **FR-11.11**: O sistema MUST tratar tenants suspensos retornando 503 `tenant_suspended` em qualquer requisição da API pública.
- **FR-11.12**: O sistema MUST publicar políticas de deprecation (mínimo 6 meses) para versões obsoletas com headers `Deprecation` + `Sunset`.

#### Módulo 4 — Painel Super Admin

- **FR-12.1**: O sistema MUST oferecer painel Super Admin global (não tenant-scoped) com listagem, filtros e ações sobre tenants.
- **FR-12.2**: O sistema MUST permitir Super Admin executar suspend / reactivate / cancel sobre tenants, com audit_log obrigatório (motivo ≥10 chars).
- **FR-12.3**: O sistema MUST suportar modo impersonate com **acesso total** ao escopo do Admin Clínica do tenant alvo (Q19), banner persistente, e audit_log de início/fim + **audit granular por tela visitada**.
- **FR-12.4**: O sistema MUST permitir CRUD de planos globais com snapshot versioning (tenants existentes não impactados ao editar); planos carregam limites de envio diário de campanha e rate limit da API pública.
- **FR-12.5**: O sistema MUST oferecer painel de métricas globais (MRR, ARR, **churn primário** = cancelamentos no período / ativos no início — Q21, **revenue churn** separado, conversão trial→pago, consumo de IA) sem expor dados de paciente individual.
- **FR-12.6**: O sistema MUST notificar Super Admin sobre anomalias (Q22) via **inbox interna + e-mail crítico** para 4 categorias monitoradas com threshold configurável.
- **FR-12.7**: O sistema MUST aplicar **política de retenção pós-cancelamento diferenciada por categoria** (Q20): billing 5a, controladas 2a, audit logs 1a, paciente 90d (30d undo + 60d grace), config 30d.
- **FR-12.8**: O sistema MUST permitir criação manual de tenant pelo Super Admin (Q23) com `billing_mode ∈ {stripe, offline_invoice}`.

#### Módulo 5 — Privacidade/LGPD

- **FR-13.1**: O sistema MUST registrar consentimento **hierárquico** (Q24): transacional implícito ao cadastro; marketing opt-in explícito; pesquisa opt-in explícito (placeholder).
- **FR-13.2**: O sistema MUST bloquear comunicações de marketing antes de consentimento registrado para o destinatário; transacional permitido por consentimento implícito.
- **FR-13.3**: O sistema MUST processar revogação via `/sair` (apenas marketing — Q25) e `/sair tudo` (marketing + transacional); persistir registro com evidência.
- **FR-13.4**: O sistema MUST oferecer canal de solicitação de direito ao esquecimento + fluxo formal com deadline D+15 dias úteis.
- **FR-13.5**: O sistema MUST aplicar **mapa de anonimização explícito** (Q26) com 3 categorias — anonimizados via placeholder, deletados fisicamente, preservados por obrigação legal — listadas por nome de campo em FR-AC-13.2.3.
- **FR-13.6**: O sistema MUST notificar Admin Clínica em **D-5 via inbox** e **D-2 via inbox + e-mail + alerta visual persistente** sobre proximidade do prazo LGPD (Q27).
- **FR-13.7**: O sistema MUST oferecer painel de privacidade para Admin Clínica visualizar consentimentos e auditorias.
- **FR-13.8**: O sistema MUST pseudonimizar PII identificadora e clínica antes de qualquer envio ao LLM (CPF, RG, carteirinha, telefone, e-mail, foto, endereço, nome do medicamento em receitas controladas).
- **FR-13.9**: O sistema MUST manter mapeamento reversível **apenas em memória de processo**, descartado ao fim do processamento.
- **FR-13.10**: O sistema MUST executar auditoria de pseudonimização (Q29) via **abordagem dupla**: (i) varredura estática via reflection (gate de CI estendendo padrão Fase 7), (ii) replay semanal de 1% randômico de eventos persistidos contra detector de PII regex. Relatório exposto no painel de privacidade.
- **FR-13.11**: O sistema MUST suportar **portabilidade de dados** (Q28) gerando arquivo JSON estruturado com 4 categorias de escopo, entregue via URL assinada de 7 dias, prazo 15 dias úteis.
- **FR-13.12**: O sistema MUST aplicar scrub de PII em integrações de observabilidade (Sentry, logs estruturados).

### Non-Functional Requirements

- **NFR-1** (Performance): p95 do dashboard executivo ≤ 1,5s; p95 de relatórios operacionais/clínicos ≤ 3s com filtros padrão. Webhook delivery: p95 latência ≤ 5s da emissão do evento. API pública: p95 ≤ 300ms para reads, ≤ 800ms para writes (alinhado RNF-001 do PRD).
- **NFR-2** (Isolamento): qualquer query de Módulo 2 e 3 MUST respeitar global scope multi-tenant; queries de Módulo 4 explicitam quebra do scope com gate de perfil Super Admin.
- **NFR-3** (Observabilidade): cada disparo de campanha, cada entrega de webhook, cada ação de impersonate, cada execução de esquecimento MUST emitir log estruturado + audit_log + métrica Prometheus.
- **NFR-4** (Conformidade Meta): nenhuma mensagem de campanha pode sair sem template aprovado fora da janela 24h; bloqueio é em runtime, não em código humano.
- **NFR-5** (LGPD): nenhum dado de paciente em texto plano pode sair em prompt do LLM; auditoria automatizada bloqueia merge se evento não conforme for adicionado.
- **NFR-6** (Acessibilidade): painéis Super Admin e Privacidade seguem WCAG AA (foco visível, contraste, navegação por teclado).
- **NFR-7** (Internacionalização): textos pt-BR default; arquitetura aceita i18n futura (mensagens de campanha, templates HSM, e-mails de confirmação LGPD).
- **NFR-8** (Retenção): audit_logs deste módulo retidos mínimo 1 ano (LGPD); logs de webhook retidos 90 dias; DLQ de webhooks retida 30 dias.
- **NFR-9** (Idempotência): toda operação write da API pública aceita header `Idempotency-Key` (UUID); requests com mesma chave em janela de 24h retornam o mesmo response.

---

## 3. Key Entities (high level — sem decisões de stack/schema)

### Módulo 1 — Campanhas

- **Campanha** — Disparo agendado ou imediato com critério de segmentação, template, canal preferido, escopo tenant. Atributos conceituais: nome, status (`draft|scheduled|dispatching|completed|canceled`), audience_filters, scheduled_for, template_ref, channel **único**, daily_limit_inherited_from_plan, total_eligible, total_dispatched.
- **DestinatárioDeCampanha** — Snapshot de cada destinatário no momento do disparo (para auditoria e idempotência). Atributos: campaign_id, patient_id, dispatched_at, status (`pending|sent|delivered|read|responded|blocked|failed`), blocked_reason.
- **TemplateMensagem** — Referência a template HSM aprovado pela Meta (já existente da Fase 3) + metadados de campanha. Status (`pending|approved|rejected|expired`).
- **OptInMarketing** — Preferência granular do paciente por finalidade (Q24). Atributos: patient_id, finalidade ∈ `{transacional, marketing, pesquisa}`, granted/revoked, granted_at/revoked_at, channel, evidence, version_of_terms_accepted.

### Módulo 2 — Relatórios

- **AgregacaoMetrica** — Pré-computação de KPIs por tenant, período e dimensão (atualização horária — Q9). Atributos: tenant_id, metric_name, period (day/week/month/hour), value, computed_at.
- **ExportacaoRelatorio** — Registro de cada exportação CSV/PDF. Atributos: exported_by, tipo, formato, filters_applied, exported_at, link_to_audit_log.

### Módulo 3 — Integrações

- **WebhookConfiguracao** — Configuração de webhook tenant-scoped. Atributos: tenant_id, url, segredo (hash), events_subscribed[], status, created_by.
- **WebhookEntrega** — Tentativa de entrega. Atributos: webhook_id, event_type, payload, attempt_number, status (`pending|delivered|failed|dlq|expired`), latency_ms, response_status, response_body_excerpt, scheduled_for, executed_at.
- **WebhookDeadLetter** — Eventos que esgotaram 5 tentativas. Atributos: webhook_id, event_type, final_payload, last_error, moved_to_dlq_at, expires_at (30d), reenviado_em.
- **TokenApi** — Token de acesso à API pública (Sanctum reaproveitado da Fase 4). Aqui formaliza atributos `scope`, `auth_method ∈ {sanctum, oauth_client_credentials}`, `created_via='public_api_config'`.
- **OauthClient** — `client_id` + `client_secret_hash` para enterprise (Q18). Tenant-scoped, opt-in.

### Módulo 4 — Painel Super Admin

- **Tenant** (existente — Fase 0) — Adicionar campos: `suspended_at`, `suspended_by`, `suspended_reason`, `canceled_at`, `retention_policy`, `billing_mode ∈ {stripe, offline_invoice}`.
- **PlanoVersao** — Cada edição de plano cria nova versão. Atributos: plan_id, version, valid_from, valid_to, snapshot (preço, profissionais inclusos, mensagens IA, daily_campaign_limit, api_rate_limit_per_min, recursos habilitados).
- **TenantPlanoVinculo** — Liga tenant à versão específica do plano. Atributos: tenant_id, plan_version_id, effective_from, effective_to.
- **SessaoImpersonate** — Registro de cada sessão. Atributos: super_admin_id, tenant_id, started_at, ended_at, scope='full', ip_address, user_agent, screens_visited_count.
- **AnomaliaDetectada** — Registro de anomalia. Atributos: categoria, tenant_id?, severity, threshold_breached, observed_value, detected_at, notified_via[].

### Módulo 5 — Privacidade/LGPD

- **RegistroConsentimento** — Cada consentimento dado/revogado. Atributos: patient_id, channel, finalidade ∈ `{transacional, marketing, pesquisa}`, granted_at, revoked_at, evidence_message_id, terms_version, scope.
- **SolicitacaoEsquecimento** — Solicitação formal de direito ao esquecimento. Atributos: patient_id, requested_at, deadline_at, channel_of_request, status (`open|in_progress|executed|expired|denied|pending_verification`), executed_at, executed_by, fields_anonymized, fields_deleted, fields_preserved.
- **SolicitacaoPortabilidade** — Solicitação de portabilidade (Q28). Atributos: patient_id, requested_at, deadline_at, status, executed_at, executed_by, file_signed_url_id, file_size_bytes, expires_at (URL 7d).
- **AuditoriaPseudonimizacao** — Resultado da auditoria. Atributos: audited_at, audited_by, mode ∈ `{static_reflection, runtime_replay}`, scope_events, findings (json), total_events_scanned, non_conformant_events, sample_size.

---

## 4. Eventos de Domínio (consolidado)

**Módulo 1**: `CampanhaCriada`, `CampanhaAtualizada`, `CampanhaDisparada`, `CampanhaCancelada`, `MensagemCampanhaEnviada`, `PacienteDescadastradoDeCampanhas`

**Módulo 2**: `RelatorioExportado` (audit-only)

**Módulo 3**: `WebhookConfigurado`, `WebhookEntregue`, `WebhookFalhou`, `WebhookReagendado`, `TokenApiEmitido`, `TokenApiRevogado`

**Módulo 4**: `TenantCriadoPorSuperAdmin`, `TenantSuspenso`, `TenantReativado`, `TenantCancelado`, `PlanoAlteradoPeloSuperAdmin`, `ImpersonateIniciado`, `ImpersonateTelaVisitada`, `ImpersonateEncerrado`, `PlanoCriado`, `PlanoEditado`, `AnomaliaDetectada`

**Módulo 5**: `ConsentimentoRegistrado`, `ConsentimentoRecusado`, `ConsentimentoRevogado`, `DireitoEsquecimentoSolicitado`, `DireitoEsquecimentoExecutado`, `PortabilidadeDadosSolicitada`, `PortabilidadeDadosExecutada`, `AuditoriaPrivacidadeExportada`, `PoliticaPseudonimizacaoAuditada`

---

## 5. Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-9.1**: Admin Clínica consegue criar e disparar uma campanha de reativação para 100 pacientes em ≤ 5 minutos (do "Nova campanha" ao primeiro envio).
- **SC-9.2**: Taxa de bloqueio de envio por motivo de conformidade (sem opt-in marketing, template não aprovado, fora de horário) = **100%** dos destinatários ineligíveis. Zero falsos positivos (paciente elegível bloqueado por bug).
- **SC-9.3**: Mensagens de campanha entregues respondidas em ≤ 7 dias resultam em ≥ 15% de agendamentos atribuíveis (métrica de eficácia; baseline para iteração).
- **SC-10.1**: Dashboard executivo carrega em ≤ 1,5s para tenants com até 50.000 pacientes em ≤ 30 dias de janela.
- **SC-10.2**: 100% das exportações CSV/PDF aparecem em audit_logs com `exported_by` + filtros aplicados.
- **SC-10.3**: Médico (não proprietário) **nunca** consegue ver dados além do escopo definido (Q13) — validado por teste automatizado de regressão.
- **SC-11.1**: Webhook é entregue ao destinatário em p95 ≤ 5 segundos da emissão do evento (medido fim a fim, sem retries).
- **SC-11.2**: API pública v1 mantém compatibilidade backward por ≥ 6 meses a partir do go-live; nenhuma mudança breaking sem nova versão.
- **SC-11.3**: Rate limit aplicado em 100% das requisições; resposta 429 inclui headers `X-RateLimit-*` e `Retry-After` em 100% dos casos de excesso.
- **SC-11.4**: DLQ retém 100% dos webhooks que esgotaram retries; Admin Clínica consegue reenviar manualmente com sucesso em ≤ 5s da ação.
- **SC-12.1**: Super Admin executa suspend/reactivate em ≤ 3 cliques + audit_log gerado em 100% dos casos.
- **SC-12.2**: Modo impersonate banner é visível em 100% das telas durante a sessão (validado por teste E2E); cada tela visitada gera audit_log granular.
- **SC-12.3**: Métricas globais (MRR, ARR, churn) atualizadas em até 1h da mudança subjacente (cancelamento, upgrade, novo tenant).
- **SC-12.4**: Anomalias detectadas geram notificação para Super Admin (inbox + e-mail crítico) em ≤ 5 minutos da detecção.
- **SC-13.1**: 100% dos pacientes que enviam primeira mensagem em qualquer canal recebem solicitação de opt-in marketing antes de qualquer campanha.
- **SC-13.2**: 100% das solicitações de esquecimento têm sua tarefa criada no mesmo dia + countdown de prazo iniciado.
- **SC-13.3**: 0 prompts enviados ao LLM contêm CPF, RG, carteirinha ou telefone em texto plano (validado por scan automatizado em CI + replay semanal).
- **SC-13.4**: Anonimização do direito ao esquecimento executada em 100% dos casos dentro do prazo de 15 dias úteis (ou tarefa marcada como `vencido_sem_resposta` + alerta crítico).
- **SC-13.5**: 100% das solicitações de portabilidade resultam em arquivo JSON gerado e URL assinada de 7 dias entregue dentro do prazo.

---

## 6. Definição de Pronto do MVP

> Quando todos os itens abaixo estiverem ✅, o produto inteiro está pronto para go-live público. Falta de qualquer item ❌ ou em dúvida (?) significa que o MVP não pode ser comercializado.

### Conformidade regulatória

- [ ] LGPD: registro hierárquico de consentimento (Q24 — transacional/marketing/pesquisa) ativo e auditável ✅/❌
- [ ] LGPD: fluxo de direito ao esquecimento operacional com prazo D+15 dias úteis e mapa de anonimização (Q26) aplicado ✅/❌
- [ ] LGPD: portabilidade de dados (Q28) entregando arquivo JSON estruturado via URL assinada 7d ✅/❌
- [ ] LGPD: auditoria de pseudonimização dual (Q29 — estática + replay semanal) executada e relatório gerado ✅/❌
- [ ] Conformidade Meta: dispatcher bloqueia em runtime qualquer envio sem opt-in marketing ou template ✅/❌
- [ ] Conformidade Meta: 100% das mensagens não-transacionais incluem `/sair` ou link de unsubscribe ✅/❌
- [ ] Portaria 344/98: receitas controladas (Fase 7) preservadas em direito ao esquecimento por 2 anos ✅/❌

### Operacional

- [ ] Painel Super Admin: list/filter/suspend/reactivate/cancel de tenants ✅/❌
- [ ] Painel Super Admin: CRUD de planos com snapshot versioning + limites de campanha/API por plano ✅/❌
- [ ] Painel Super Admin: impersonate com banner persistente + audit_log início/fim + audit granular por tela ✅/❌
- [ ] Painel Super Admin: métricas globais (MRR, ARR, churn primário + revenue churn separado, conversão) ✅/❌
- [ ] Painel Super Admin: criação manual de tenant com `billing_mode ∈ {stripe, offline_invoice}` ✅/❌
- [ ] Painel Super Admin: notificação de anomalias via inbox + e-mail crítico ✅/❌
- [ ] Política de retenção pós-cancelamento diferenciada por categoria (Q20) implementada ✅/❌

### Funcional

- [ ] Campanhas: criar, agendar, disparar canal único com validações de conformidade ✅/❌
- [ ] Campanhas: relatório com métricas de envio/entrega/resposta/atribuição + polling 30s durante disparo ✅/❌
- [ ] Campanhas: comando `/sair` revoga marketing; `/sair tudo` revoga marketing+transacional ✅/❌
- [ ] Campanhas: limite diário aplicado por plano com fragmentação automática ✅/❌
- [ ] Dashboard Executivo: 5 cards (NPS como placeholder) + filtro de período + drill-down + variação % ✅/❌
- [ ] Relatórios Operacionais: tempo de resposta, volume, performance da IA, escopo por perfil ✅/❌
- [ ] Relatórios Clínicos: ocupação, mix de procedimentos, retornos, escopo por perfil ✅/❌
- [ ] Exportação CSV/PDF com audit_log em todos os relatórios; PDF em layout próprio ✅/❌
- [ ] Webhooks: CRUD + HMAC + retry 5×exponential + DLQ 30d com reenvio manual ✅/❌
- [ ] Webhooks: catálogo de 13 eventos materiais expostos para subscrição ✅/❌
- [ ] API Pública v1: docs OpenAPI + Sanctum + OAuth opt-in + rate limit por plano + versionamento ✅/❌
- [ ] API Pública v1: escopo restrito (pacientes, agendamentos, mensagens R, receituários R, tipos R, profissionais R) ✅/❌

### Não-funcional

- [ ] Performance: dashboard ≤ 1,5s p95; relatórios ≤ 3s p95; webhook delivery ≤ 5s p95 ✅/❌
- [ ] Observabilidade: métricas Prometheus expostas para cada novo domínio ✅/❌
- [ ] Observabilidade: erros reportados ao Sentry com scrub de PII ativo ✅/❌
- [ ] Testes: cobertura ≥ 70% no backend desta fase ✅/❌
- [ ] Testes: E2E cobre jornadas críticas (criar campanha + disparo, executar esquecimento, portabilidade, impersonate) ✅/❌
- [ ] Acessibilidade WCAG AA validada nos painéis Super Admin e Privacidade ✅/❌
- [ ] CI gate: scan estático de pseudonimização (`ContainsNoClinicalData` marker) bloqueia merge de evento não conforme ✅/❌
- [ ] Job semanal: replay de 1% randômico contra detector PII em execução ✅/❌

### Documentação e qualidade

- [ ] OpenAPI da API pública v1 publicada e revisada ✅/❌
- [ ] Política de Privacidade e Termos de Uso versionados na plataforma (consumidos por `terms_version`) ✅/❌
- [ ] Runbook operacional para Super Admin (impersonate, suspend, reativar, criar tenant manual) ✅/❌
- [ ] DPIA (Data Protection Impact Assessment) executada para Módulo 5 ✅/❌

---

## 7. Mapeamento à Constituição

| Princípio | Módulo(s) | Como esta fase atende |
|----|----|----|
| **I. Privacidade, Consentimento e LGPD (NON-NEGOTIABLE)** | 1, 5 | Módulo 5 entrega registro hierárquico de consentimento (Q24), direito ao esquecimento operacional com mapa explícito (Q26), portabilidade de dados (Q28), auditoria dual de pseudonimização (Q29) e painel de privacidade. Módulo 1 valida opt-in marketing antes de qualquer envio. |
| **II. Isolamento Multi-Tenant (NON-NEGOTIABLE)** | 2, 3, 4 | Módulo 2 (relatórios) e 3 (API pública) respeitam global scope multi-tenant. Módulo 4 (Super Admin) **explicita** a quebra de scope com gate de perfil; métricas globais agregam sem expor dados individuais; impersonate audita por tela visitada. |
| **III. Segurança Clínica e Auditabilidade da IA (NON-NEGOTIABLE)** | 5 | Módulo 5 formaliza pseudonimização (US-13.3), garante que logs do LLM não contêm PII, e executa auditoria dual (estática + replay) — Q29. Mantém regra "IA nunca menciona medicamento" da Fase 7. |
| **IV. Spec-Driven e Test-First** | Todos | Esta spec é o gate; planejamento e tasks só após 29/29 clarifications resolvidas. Cobertura ≥ 70% mantida; testes E2E exigidos para as jornadas críticas (campanha, esquecimento, portabilidade, impersonate). |
| **V. Observabilidade e Excelência Operacional** | Todos | Cada módulo emite eventos auditáveis + métricas Prometheus dedicadas. Audit_logs obrigatórios em: exportações, impersonate (incluindo por tela visitada), suspensão de tenant, execução de esquecimento, alteração de plano, criação manual de tenant. |
| **VI. Conformidade Meta nos Disparos (NON-NEGOTIABLE)** | 1 | Dispatcher de campanha (US-9.3) aplica em runtime: opt-in marketing, template aprovado, `business_hours`, limite diário do plano, link de unsubscribe. Bloqueio é em código, não em treinamento operacional. |
| **VII. Segurança Operacional (NON-NEGOTIABLE)** | 3, 4 | Módulo 3 obriga HTTPS em URLs de webhook, HMAC SHA-256 em payloads, rate limit por token+IP em API pública; Sanctum hashado (Fase 4) + OAuth opt-in para enterprise. Módulo 4: ações destrutivas exigem motivo ≥10 chars + audit_log. |

---

## 8. Out of Scope (não entra na Fase 8 nem no MVP)

- **Financeiro do paciente** — pré-pagamento de consulta, integração com gateway de pagamento por paciente (Stripe Connect, split de pagamentos), cobrança via boleto/PIX para o paciente.
- **Telemedicina** — videoconferência, prescrição digital com assinatura ICP-Brasil, integração com plataformas de telemedicina (Conexa, etc.).
- **Prontuário Eletrônico** — anamnese, evolução clínica, exames anexos, diagnósticos estruturados (CID-10). O produto é CRM, não prontuário (RNF do PRD §4).
- **Multi-unidade (filiais por tenant)** — cada CNPJ/clínica é um tenant. Federar várias unidades sob um mesmo tenant fica fora.
- **SSO Google para usuários internos** — login via Google Workspace. Mantém-se Sanctum (Bearer) e cookie session (Filament) das Fases 0/4.
- **Integração com sistemas de saúde externos (TISS / HL7 / FHIR)** — convênios, planos de saúde, prontuário hospitalar.
- **Aplicativo mobile nativo** — o produto é web responsivo (SPA Vue 3). App nativo (iOS/Android) fica para fase posterior.
- **Coleta automatizada de NPS** — Q8 marcou como placeholder. Módulo de pesquisa (timer pós-consulta, UI de survey, scoring) fica para pós-MVP.
- **Re-envio multi-step de campanha** — Q5 marcou disparo único. Nurturing (D+3 / D+7 / D+14) fica para pós-MVP.
- **Aprovação intermediária de campanha** — Q4 dispensou. Workflow approval fica para pós-MVP.
- **Modo "lado a lado" de comparativo entre períodos** — Q11 escolheu variação %. Comparativo lado a lado fica para pós-MVP.
- **Multi-canal por campanha (WhatsApp + Instagram simultâneo)** — Q3 escolheu canal único. Multi-canal fica para pós-MVP.
- **Sandbox de API pública com dados sintéticos** — AC-11.2.9 marca como 🟢; implementação fica fora do MVP.
- **Cancelamento automático após N dias de inadimplência** — AC-12.1.8 anota como feature futura.
- **Compartilhamento com convênio (consentimento)** — Q24 deixou fora; relevante quando integração TISS for priorizada.
- **Notificações push (mobile / browser) para Admin Clínica** — toda notificação é inbox + e-mail (Q27).

---

## 9. Assumptions

### Sobre a plataforma e usuários

- **A.1**: Tenants existentes na Fase 0 já têm modelo de billing operacional via Stripe (Cashier). Esta fase **não reimplementa billing**; apenas expõe ações administrativas no painel Super Admin + adiciona `billing_mode='offline_invoice'` para enterprise.
- **A.2**: Serviço de mensageria da Fase 3 está operacional para todos os canais ativos no tenant (WhatsApp Business, Instagram Direct, Widget Web). Campanhas reutilizam esse serviço — sem fork de dispatcher.
- **A.3**: Audit log infrastructure (tabela `audit_logs` + listener `RegistraEventoTimelineListener`) da Fase 2 está disponível e é consumida por este módulo sem extensões adicionais de schema.
- **A.4**: Métricas Prometheus expostas pelas Fases 4/5/7 (ai_decision_logs, agenda metrics, prescription metrics) estão disponíveis para o Módulo 2 agregar.
- **A.5**: Templates HSM já aprovados pela Meta (Fase 3) são reaproveitáveis para campanhas; novos templates específicos de reativação são cadastráveis pelo Admin Clínica.
- **A.6**: `business_hours` configurado por tenant na Fase 5 está disponível e consultável; sem necessidade de blackout sistêmico adicional (Q7).

### Sobre perfis e permissões (já existentes via Spatie)

- **A.7**: Os perfis `super_admin`, `admin_clinica`, `medico`, `atendente`, `recepcionista` já estão definidos (Fase 0/1). Esta fase adiciona abilities específicas: `campaign.create`, `campaign.dispatch`, `report.view`, `report.export`, `webhook.manage`, `api_token.manage`, `tenant.manage`, `tenant.impersonate`, `plan.manage`, `privacy.view`, `privacy.export`, `forgetting.execute`, `portability.execute`.
- **A.8**: Super Admin não pertence a nenhum tenant (`users.tenant_id NULL` já modelado na Fase 0). Todas as ações de Super Admin são auditadas com `tenant_id` do **alvo da ação**, não do executor.

### Sobre escopo regulatório e legal

- **A.9**: A clínica contratante é controlador de dados LGPD; a plataforma é operador. O fluxo de direito ao esquecimento é **operacionalizado pela clínica** (Admin Clínica clica em "Executar"); a plataforma apenas rastreia o prazo e aplica o mapa de anonimização.
- **A.10**: Termos de Uso e Política de Privacidade são versionados externamente (CMS / arquivos versionados no repo) e referenciados por `terms_version`. Esta fase **não desenvolve** o texto legal — apenas consome a versão vigente no momento do consentimento.
- **A.11**: Retenção de dados após cancelamento de tenant respeita tanto LGPD quanto a Portaria 344/98 (receitas controladas — 2 anos) — política diferenciada por categoria de dado (Q20).

### Sobre integrações externas

- **A.12**: Stripe webhooks (cancelamento, falha de pagamento) já são consumidos pela Fase 0 e geram eventos de domínio reutilizáveis pelo Módulo 4 (suspensão automática por inadimplência).
- **A.13**: A entrega de webhooks externos pelo CRM (Módulo 3) usa a infraestrutura de fila do Horizon (já presente desde Fase 0). Sem nova plataforma de filas.
- **A.14**: OAuth 2.0 Client Credentials (Q18) é opt-in para enterprise; o Sanctum estático default cobre todos os parceiros técnicos pequenos/médios.

### Sobre fora-de-escopo já validado

- **A.15**: NPS no MVP é placeholder (Q8). Sem coleta automática nesta fase — entidade `pesquisa_nps` **não é modelada agora**.
- **A.16**: Sandbox de API pública é nice-to-have (AC-11.2.9); fica para depois do MVP.
- **A.17**: Alertas automáticos do Super Admin (Q22) saem via inbox interna + e-mail crítico; sem Slack/PagerDuty no MVP.
- **A.18**: `business_hours` é o único bloqueador de envio temporal (Q7); sem blackout sistêmico extra na plataforma.
