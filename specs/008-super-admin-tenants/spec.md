# Feature Specification: Painel Super Admin + Gerenciamento de Tenants + Auditoria do Onboarding

**Feature Branch**: `008-super-admin-tenants`
**Created**: 2026-05-19
**Status**: Draft
**Input**: User description: "Painel Super Admin da Plataforma + Gerenciamento de Tenants (US-12.1, US-12.2, US-12.3) e auditoria/refinamento do fluxo de Onboarding já entregue (US-1.1, US-1.2). Inclui: (1) listagem/filtros/busca cross-tenant no Filament; (2) ações administrativas (suspender, reativar, estender trial, ajustar plano, impersonar com audit log); (3) gestão global de planos (CRUD, preços base + cota IA inclusa + valor por mensagem excedente, ativação/desativação); (4) dashboard de métricas globais (tenants ativos, MRR/ARR, churn, consumo IA agregado, top consumidores, conversões trial→pago); (5) verificação e melhorias no onboarding atual: corrigir status desatualizado no docs/user-stories.md, validar wizard 5 etapas (clinic_data → first_professional → channel_connection → schedule_setup → ai_knowledge_base), confirmar bloqueio de funcionalidades quando required step pending, garantir e-mail de boas-vindas, completar fluxos que estão parciais. Não é IA Matricial, não é billing Stripe completo (esses ficam em specs separadas)."

## Visão Geral

A Paciente360 já entregou 7 fases (Fases 0–7) e opera com tenants reais cadastrados via fluxo `POST /tenants/register` + wizard de onboarding em 5 etapas. **Falta o "centro de comando" da plataforma**: hoje o time de operações da Paciente360 (Super Admins) não tem ferramentas adequadas para acompanhar a saúde do negócio, agir sobre clínicas em risco/inadimplentes, configurar a oferta comercial (planos) ou auditar a jornada de adoção (onboarding).

Esta feature entrega o **painel super-admin operacional** (cross-tenant, isolado em domínio próprio `crm.com.br/admin`) cobrindo as US-12.x, e fecha as pontas soltas do fluxo de Onboarding (US-1.1 e US-1.2) que foi parcialmente entregue na Fase 0 — sem entrar no mérito de IA Matricial (Épico 5) nem do billing completo via Stripe (Épico 1 — US-1.3/1.4/1.5), que ficam em specs subsequentes.

**Escopo deliberado** (NÃO INCLUI):
- Implementação completa de checkout Stripe, proration, dunning (fica em spec de billing futura).
- IA Matricial: agente, KB, RAG, classificação de intenção, escalonamento.
- Recuperação de senha (US-2.3) e MFA/SSO.
- Onboarding com tour guiado in-app, vídeos, gamificação — apenas o wizard 5 etapas já existente é validado/refinado.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Gestão de Tenants (US-12.1) (Priority: P1)

**Como** Super Admin da Paciente360,
**Quero** visualizar, filtrar, buscar e operar sobre todos os tenants cadastrados na plataforma,
**Para que** eu consiga atender suporte, intervir em inadimplência e acompanhar o ciclo de vida comercial de cada clínica.

**Why this priority**: Sem esta capacidade, o time de operações depende de acesso direto ao banco para qualquer demanda de suporte ou cobrança — isto é insustentável a partir de ~10 tenants e expõe risco operacional e LGPD. É o desbloqueador mínimo para o time comercial/CS operar.

**Independent Test**: Logado como Super Admin no painel `/admin`, consigo listar os 7 tenants existentes, filtrar por status (trial / ativo / suspenso / inadimplente), buscar por nome ou CNPJ, ver detalhe de um tenant (com plano, profissionais ativos, último login, consumo IA) e executar uma ação administrativa (ex.: suspender) — gerando audit log correspondente.

**Acceptance Scenarios**:

1. **Given** existem 7 tenants cadastrados com status variados, **When** o Super Admin acessa `/admin/tenants`, **Then** vê uma tabela com colunas `nome`, `slug`, `cnpj_mascarado`, `status`, `plano`, `profissionais_ativos`, `trial_ends_at`, `created_at` paginada e ordenável.
2. **Given** o Super Admin filtra por `status = trial`, **When** aplica o filtro, **Then** a tabela mostra apenas tenants em trial e o contador de resultados reflete o filtro.
3. **Given** o Super Admin busca por "Clínica Alfa" no campo de busca, **When** confirma a busca, **Then** a tabela retorna apenas tenants cujo nome contém "Alfa" (case-insensitive, com `unaccent`).
4. **Given** um tenant em status `active` com inadimplência detectada, **When** o Super Admin clica em "Suspender" e confirma o motivo no modal, **Then** o tenant transiciona para `suspended`, todos os usuários daquele tenant ficam impedidos de acessar APIs autenticadas, um audit log `super_admin.tenant.suspended` é gravado com motivo e o tenant aparece com badge "Suspenso" na lista.
5. **Given** um tenant em trial que precisa de mais tempo, **When** o Super Admin escolhe "Estender Trial" e informa "+14 dias", **Then** `trial_ends_at` avança 14 dias, o evento `TrialExtendedBySuperAdmin` é emitido e a clínica recebe e-mail informando.
6. **Given** o Super Admin precisa investigar um bug reportado por um Admin Clínica, **When** clica em "Impersonar" e confirma o motivo, **Then** uma sessão de impersonação com duração máxima 1 hora é aberta, com banner persistente "Você está impersonando {tenant} — sair", todas as ações executadas durante a sessão são marcadas como `actor.impersonated_by = {super_admin_id}` no audit log e o evento `SuperAdminImpersonationStarted` é emitido. PII clínica visível segue as mesmas restrições do perfil impersonado.
7. **Given** um tenant suspenso por erro operacional, **When** Super Admin clica em "Reativar" e informa motivo, **Then** o tenant volta a `active`, audit log `super_admin.tenant.reactivated` é gravado e usuários voltam a conseguir acessar a API.
8. **Given** o Super Admin precisa ajustar o plano de um tenant após negociação comercial, **When** seleciona o novo plano e a quantidade de profissionais e confirma, **Then** o tenant passa a referenciar o novo `Plan`, audit log `super_admin.tenant.plan_changed` registra o antes/depois e o tenant recebe e-mail. *(Cobrança via Stripe é tratada em spec separada — aqui apenas o vínculo lógico muda.)*

---

### User Story 2 — Gestão Global de Planos (US-12.2) (Priority: P1)

**Como** Super Admin,
**Quero** criar, editar, ativar e desativar os planos comerciais oferecidos na plataforma,
**Para que** o time comercial consiga lançar/ajustar pacotes sem depender de migration de código.

**Why this priority**: Hoje o `Plan` model existe (Fase 0/1) mas não há CRUD operacional. Lançar/ajustar plano exige deploy. Isto bloqueia operação comercial básica. P1 porque é dependência direta da US-1 (ajuste de plano em tenant) e da US-3 (dashboard precisa agrupar tenants por plano).

**Independent Test**: Acessando `/admin/plans`, consigo criar um plano "Profissional" com preço base R$ 149/profissional/mês + 1000 mensagens IA inclusas + R$ 0,15 por mensagem excedente, ativá-lo, e em seguida ver este plano disponível para vincular a tenants na US-1.

**Acceptance Scenarios**:

1. **Given** Super Admin acessa `/admin/plans`, **When** clica em "Novo Plano", **Then** abre formulário com campos: `nome`, `slug` (auto), `descrição`, `preco_base_centavos` (por profissional/mês), `cota_mensagens_ia_inclusas`, `preco_excedente_msg_centavos`, `is_active` (toggle), `is_public` (visível na página de pricing), `ordem_exibicao`, `features[]` (array de strings — ex.: "Sync Google Calendar", "Suporte prioritário").
2. **Given** um plano "Starter" ativo está vinculado a 3 tenants, **When** Super Admin tenta desativar o plano, **Then** o sistema permite a desativação mas exibe alerta "3 tenants ainda usam este plano — eles continuam ativos no plano atual, mas novas adesões ficam indisponíveis", e o plano some das páginas públicas de pricing.
3. **Given** Super Admin edita o preço base de R$ 99 para R$ 119, **When** salva, **Then** os tenants existentes vinculados ao plano **NÃO** têm cobrança alterada automaticamente (mudança aplica-se a novas adesões / renovações de ciclo — gate de retrocompatibilidade). Um audit log `super_admin.plan.price_changed` é gravado com valores antes/depois.
4. **Given** Super Admin tenta excluir um plano vinculado a tenants, **When** confirma a exclusão, **Then** o sistema impede a exclusão e mostra "Não é possível excluir plano com tenants vinculados — desative em vez disso".
5. **Given** dois planos têm `slug` idêntico, **When** Super Admin tenta salvar o segundo, **Then** o formulário retorna erro de validação com mensagem clara.

---

### User Story 3 — Dashboard de Métricas Globais (US-12.3) (Priority: P1)

**Como** Super Admin (ou diretoria),
**Quero** uma tela única com os KPIs vitais do negócio,
**Para que** eu acompanhe saúde de receita, adoção e consumo sem precisar abrir múltiplas planilhas.

**Why this priority**: Falta visibilidade agregada de negócio. Hoje a única forma de saber "quantos tenants ativos temos hoje" é via query manual. Para uma startup SaaS em fase de tração, isto é crítico para decisões semanais.

**Independent Test**: Em `/admin` (home do painel super-admin) vejo 6 cards de KPI principais e 3 gráficos (séries temporais 30 dias) que carregam em <3s e refletem o estado atual da base. Posso clicar em "Top 10 consumidores IA" e ver lista cross-tenant ordenada.

**Acceptance Scenarios**:

1. **Given** Super Admin acessa `/admin`, **When** a página carrega, **Then** vê 6 cards de KPI:
   - **Tenants Ativos**: contagem de tenants com `status = active` + trial não expirado
   - **Tenants em Trial**: contagem de `status = trial` com `trial_ends_at > now()`
   - **MRR Estimado**: soma `(plano.preco_base × profissionais_ativos)` para todos tenants ativos com plano (excluindo trial)
   - **Consumo IA (mês atual)**: soma de `mensagens_ia_consumidas` no ciclo atual + valor estimado de excedente
   - **Conversão Trial→Pago (últimos 90d)**: % de tenants que saíram de trial sem cancelar
   - **Churn 30d**: % de tenants que cancelaram nos últimos 30 dias / base ativa início do período
2. **Given** o dashboard carregou, **When** Super Admin observa a seção gráfica, **Then** vê 3 séries temporais (últimos 30 dias):
   - Novos cadastros por dia
   - Tenants saindo de trial (convertidos vs perdidos)
   - Volume total de mensagens IA por dia
3. **Given** Super Admin quer identificar maiores consumidores, **When** clica em "Top 10 consumidores IA", **Then** vê tabela com `tenant`, `plano`, `mensagens_consumidas_mes`, `% acima da cota`, `custo_estimado_excedente`.
4. **Given** Super Admin acessa o dashboard em horário de pico, **When** os widgets fazem fetch, **Then** todos os 6 KPIs e 3 gráficos renderizam em ≤ 3 segundos (cache de 5 minutos aceitável — TTL configurável).
5. **Given** os números do dashboard precisam ser explicáveis, **When** Super Admin passa o mouse sobre um KPI, **Then** vê tooltip explicando a fórmula e o período de referência.

---

### User Story 4 — Auditoria e Correção do Fluxo de Onboarding (US-1.1, US-1.2) (Priority: P2)

**Como** time de produto/engenharia,
**Quero** garantir que o fluxo de Onboarding (já entregue na Fase 0) atende todos os critérios de aceite originais e está documentado corretamente,
**Para que** a próxima onda de tenants (incluindo os trazidos via campanha comercial) tenha experiência consistente e a base de conhecimento interna reflita a realidade.

**Why this priority**: O fluxo existe e funciona, mas há débito técnico: (a) o status no `docs/user-stories.md` está desatualizado (marca US-1.1/1.2 como "Pendente" quando estão entregues), (b) não há middleware bloqueando funcionalidades quando o step `clinic_data` está pendente (apenas convenção), (c) o e-mail de boas-vindas existe mas não tem testes E2E garantindo entrega. P2 porque o fluxo já gera valor — esta US fecha as pontas soltas.

**Independent Test**: Após a entrega: (1) `docs/user-stories.md` reflete o status correto, (2) um teste E2E novo prova que uma chamada à API de domínio (ex.: criar paciente) é bloqueada com 403 + payload claro quando o step required do onboarding está pendente, (3) um teste de feature prova que o e-mail de boas-vindas é encadeado e contém o checklist das 5 etapas com links profundos para o wizard.

**Acceptance Scenarios**:

1. **Given** o documento `docs/user-stories.md` lista US-1.1 e US-1.2 como "Pendente", **When** a entrega desta US-4 é concluída, **Then** o apêndice "Status das User Stories" é atualizado refletindo as US efetivamente entregues nas Fases 0–7 (campo `Status` com data de entrega e link para a spec).
2. **Given** um tenant recém-criado em que o step `clinic_data` está `pending`, **When** o Admin Clínica chama `POST /pacientes` (ou qualquer endpoint de domínio fora de `/onboarding/*` e `/auth/*`), **Then** recebe HTTP 403 com payload `{ "error": "onboarding_required_step_pending", "step": "clinic_data", "wizard_url": "/onboarding" }` — o gate é aplicado por middleware central, não em cada controller.
3. **Given** o mesmo tenant após completar `clinic_data`, **When** chama `POST /pacientes`, **Then** a requisição prossegue normalmente. Os steps opcionais (`first_professional`, `channel_connection`, `schedule_setup`, `ai_knowledge_base`) NÃO bloqueiam — apenas exibem alerta no SPA sugerindo completá-los.
4. **Given** uma nova clínica acabou de ser registrada via `POST /tenants/register`, **When** a transação completa, **Then** o job `SendWelcomeEmailJob` é enfileirado e, ao executar, envia e-mail para o `Admin Clínica` com:
   - Saudação personalizada (nome do responsável)
   - Link único para o wizard de onboarding (`/onboarding`)
   - Checklist das 5 etapas com indicação visual da etapa atual
   - Informação clara sobre os 14 dias de trial e como assinar
5. **Given** o wizard tem 5 etapas (`clinic_data`, `first_professional`, `channel_connection`, `schedule_setup`, `ai_knowledge_base`), **When** o Admin completa cada etapa, **Then** o campo `progress_percent` reflete corretamente apenas o peso de `clinic_data` (única `required = true`) — i.e., após `clinic_data`, progresso = 100%, mas a UI mostra também o status dos opcionais como "passos sugeridos".
6. **Given** o Admin Clínica abandonou o wizard na etapa 2 e voltou no dia seguinte, **When** acessa `/onboarding`, **Then** o estado persistido é restaurado e o wizard retoma do ponto exato em que parou.
7. **Given** o middleware de bloqueio precisa ser aplicado em todas as rotas de domínio existentes, **When** a equipe roda a suíte de testes, **Then** existe ao menos um teste por grupo de rota (pacientes, anotações, agenda, prescrições, inbox, leads) verificando que o bloqueio é aplicado quando `clinic_data` está pendente.

---

### User Story 5 — Audit Log e Trilha de Ações Super Admin (Priority: P2)

**Como** Super Admin (e DPO/auditoria interna),
**Quero** ter trilha completa e imutável de todas as ações executadas por Super Admins no painel da plataforma,
**Para que** eu consiga responder a investigações LGPD, identificar abuso/erro e proteger o time contra acusações infundadas.

**Why this priority**: Toda ação que um Super Admin executa sobre dados de um tenant (suspender, impersonar, alterar plano) é potencialmente sensível e regulada pela LGPD (princípio da finalidade, accountability). Sem trilha estruturada, não é possível demonstrar conformidade. P2 porque depende das US-1/US-2 já estarem em pé.

**Independent Test**: Acesso `/admin/audit-logs` e vejo trilha imutável das últimas 30 dias com filtros por `actor`, `action`, `tenant_affected`, `period`. Cada entrada mostra `quando, quem, ação, tenant alvo, motivo informado, IP, payload diff sem PII clínica`.

**Acceptance Scenarios**:

1. **Given** Super Admin executou as ações `suspended`, `reactivated`, `plan_changed`, `impersonation_started`, `impersonation_ended`, `trial_extended`, **When** acessa `/admin/audit-logs`, **Then** vê todas registradas em ordem cronológica decrescente.
2. **Given** uma sessão de impersonação durou 23 minutos, **When** o Super Admin clica para encerrar (ou o timeout de 1 hora dispara), **Then** o log `super_admin.impersonation_ended` é gravado com `duration_seconds` e quantidade de requisições feitas durante a sessão.
3. **Given** uma ação destrutiva foi executada por engano, **When** o time de auditoria consulta o log, **Then** consegue identificar `actor_id` (Super Admin), `target_tenant_id`, `reason` (texto livre que o Super Admin foi obrigado a informar) e `before / after` em formato diff JSON sem PII clínica.
4. **Given** alguém tenta apagar/editar uma entrada de audit log via interface ou API, **When** a tentativa ocorre, **Then** falha com 403 — entradas de audit log são append-only e a tabela não expõe rotas de UPDATE/DELETE pela interface.

---

### Edge Cases

- **Impersonação durante manutenção**: Se um tenant impersonado é suspenso durante a sessão, a sessão é encerrada imediatamente e o Super Admin volta ao painel com mensagem clara.
- **Plano deletado por engano**: A exclusão é bloqueada quando há tenants vinculados. Se 0 tenants vinculados, é permitida mas com confirmação dupla.
- **MRR com tenants em trial**: Trial NÃO conta para MRR. Trials são exibidos no card "Tenants em Trial" separadamente.
- **Cota IA não configurada**: Tenant sem plano (em trial sem plano selecionado ainda) é exibido como "Sem plano" e contribui zero para MRR.
- **Onboarding nunca completado por meses**: Tenant que permanece >90d com `clinic_data` pendente recebe e-mail de re-engajamento (fora do escopo desta spec, mas alarme visível no dashboard Super Admin).
- **Audit log retention**: Logs de Super Admin têm retenção mínima de 5 anos (LGPD + boas práticas). Logs de impersonação têm retenção 10 anos.
- **Filtros do dashboard e privacidade**: O Super Admin vê agregados por tenant (nome, slug, contadores). NUNCA vê conteúdo de mensagem, prontuário, paciente, prescrição (Princípio I — restrição cross-tenant para PII clínica permanece absoluta).
- **Cálculo de churn quando base é pequena**: Se a base ativa é < 20 tenants, exibir nota "Amostra pequena — interpretar com cautela".
- **Performance do dashboard**: Cache de 5 minutos em métricas agregadas; recálculo sob demanda via botão "Atualizar agora" (rate-limit 1/min).
- **Tenant sem usuário ativo**: Lista mostra "Sem admin ativo" com call-to-action para reset de senha do criador original.
- **Concorrência de Super Admins**: Dois Super Admins atuando sobre o mesmo tenant — última escrita vence; ambos eventos são registrados no audit log preservando o histórico.

## Requirements *(mandatory)*

### Functional Requirements

#### Painel Super Admin — Acesso e Segurança

- **FR-001**: O sistema MUST expor um painel super-admin acessível apenas em domínio dedicado (`crm.com.br/admin` por padrão, configurável via env) usando autenticação por sessão (cookie), separado do domínio dos tenants.
- **FR-002**: O sistema MUST restringir acesso ao painel super-admin a usuários com role `super-admin` (no guard `web`).
- **FR-003**: O sistema MUST exibir banner persistente no topo do painel: "Modo Super Admin — todas as ações são auditadas".
- **FR-004**: O sistema MUST aplicar IP allowlist opcional configurável via env (`SUPER_ADMIN_IP_ALLOWLIST`) — se preenchida, requisições de IPs fora retornam 403.
- **FR-005**: O sistema MUST expirar sessões Super Admin em 4 horas de inatividade (renovação por uso). [NEEDS CLARIFICATION: prazo ideal — proposta 4h baseada em prática de painéis administrativos críticos, ou prazo mais agressivo (1h) com renovação ativa?]

#### Gestão de Tenants (US-12.1)

- **FR-006**: O sistema MUST listar todos os tenants em uma tabela com paginação, ordenação e busca cross-tenant — bypass dos global scopes de tenancy é restrito ao painel super-admin.
- **FR-007**: A tabela MUST exibir colunas: `nome`, `slug`, `cnpj_mascarado` (formato `XX.XXX.XXX/XXXX-XX` com últimos 4 dígitos visíveis para Super Admin), `status`, `plano`, `profissionais_ativos`, `trial_ends_at`, `created_at`.
- **FR-008**: O sistema MUST oferecer filtros: `status` (trial/active/suspended/canceled), `plano`, `created_at_range`, `tem_consumo_ia_mes_atual`.
- **FR-009**: O sistema MUST permitir busca por `nome` (LIKE com unaccent), `slug`, `cnpj` (validando formato CNPJ).
- **FR-010**: O sistema MUST oferecer ações administrativas: `Ver detalhes`, `Suspender`, `Reativar`, `Estender trial (+7d/+14d/+30d ou data)`, `Alterar plano`, `Impersonar`.
- **FR-011**: Toda ação destrutiva ou de alteração de estado MUST exigir confirmação modal com campo `motivo` (obrigatório, mín 10 chars) que é registrado no audit log.
- **FR-012**: Suspender um tenant MUST: (a) impedir login dos usuários do tenant na API (middleware `tenant.not-suspended` já existe — confirmar coverage), (b) emitir evento `TenantSuspendedBySuperAdmin`, (c) enviar e-mail ao Admin Clínica primário com motivo, (d) gravar audit log.
- **FR-013**: Reativar um tenant MUST: (a) restaurar `status = active`, (b) emitir evento `TenantReactivatedBySuperAdmin`, (c) enviar e-mail ao Admin Clínica primário, (d) gravar audit log.
- **FR-014**: Estender trial MUST atualizar `trial_ends_at`, emitir evento `TenantTrialExtendedBySuperAdmin{old_date, new_date, motivo}` e enviar e-mail.
- **FR-015**: Alterar plano MUST atualizar `tenants.plan_id`, emitir `TenantPlanChangedBySuperAdmin{old_plan_id, new_plan_id, motivo}`, gravar audit log. *(Sem impacto em cobrança — billing fica em spec separada.)*
- **FR-016**: Impersonar MUST: (a) abrir sessão temporária com escopo do tenant alvo, (b) gravar `actor_impersonated_by` em qualquer audit log/evento gerado durante a sessão, (c) timeout 1h (configurável, máximo 4h), (d) banner persistente "Você está impersonando {tenant.nome} — Sair", (e) ao encerrar, gravar `super_admin.impersonation_ended` com `duration_seconds` e `requests_during_session`.
- **FR-017**: Durante impersonação, Super Admin MUST seguir as mesmas restrições de visualização de PII clínica do perfil do usuário impersonado (i.e., impersonar `recepcao` não dá acesso a notas clínicas mesmo que o Super Admin tivesse permissão maior fora da impersonação).
- **FR-018**: Após impersonação encerrada, Super Admin MUST ser redirecionado de volta ao painel super-admin sem necessidade de novo login.

#### Gestão Global de Planos (US-12.2)

- **FR-019**: O sistema MUST oferecer CRUD de planos com campos: `nome`, `slug` (gerado, UNIQUE), `descricao`, `preco_base_centavos` (int, R$ por profissional/mês), `cota_mensagens_ia_inclusas` (int, mensal), `preco_excedente_msg_centavos` (int), `is_active`, `is_public`, `ordem_exibicao`, `features` (JSON array de strings).
- **FR-020**: Slug do plano MUST ser único e gerado automaticamente a partir do nome (com sufixo numérico em caso de colisão).
- **FR-021**: Plano com `is_active = false` MUST sumir da página pública de pricing mas permanecer vinculável para tenants existentes (mudança de plano só permitida para planos ativos via painel).
- **FR-022**: Edição de preço base NÃO MUST alterar cobrança de tenants já vinculados — preço é "carimbado" no momento da próxima cobrança/renovação. [NEEDS CLARIFICATION: implementação concreta de "carimbar preço" depende da spec de billing futura — esta spec apenas garante que a alteração de plano não afeta tenants existentes retroativamente. OK manter como vínculo lógico + comunicado por e-mail?]
- **FR-023**: O sistema MUST impedir exclusão de plano vinculado a ≥1 tenant com mensagem clara.
- **FR-024**: O sistema MUST registrar audit log `super_admin.plan.created`, `super_admin.plan.updated`, `super_admin.plan.activated`, `super_admin.plan.deactivated`, `super_admin.plan.deleted` para toda mutação.
- **FR-025**: A listagem de planos MUST mostrar por plano: `nome`, `preco_base_formatado`, `cota_ia`, `excedente_unitario`, `is_active`, `tenants_vinculados_count`, `ordem_exibicao`.

#### Dashboard de Métricas Globais (US-12.3)

- **FR-026**: O painel super-admin MUST exibir, em sua home (`/admin`), 6 KPI cards: `tenants_ativos`, `tenants_em_trial`, `mrr_estimado`, `consumo_ia_mes`, `conversao_trial_pago_90d_pct`, `churn_30d_pct`.
- **FR-027**: O dashboard MUST exibir 3 séries temporais (últimos 30 dias): `novos_cadastros_dia`, `tenants_convertidos_vs_perdidos_dia`, `mensagens_ia_dia`.
- **FR-028**: O dashboard MUST oferecer widget "Top 10 consumidores IA do mês" com `tenant_nome`, `plano`, `mensagens_mes`, `pct_acima_cota`, `custo_excedente_estimado`.
- **FR-029**: O dashboard MUST oferecer widget "Tenants em alerta" listando: tenants com `clinic_data` pendente há >7d, tenants com `trial_ends_at < now() + 3d` sem plano selecionado, tenants suspensos.
- **FR-030**: Cada KPI MUST exibir tooltip com fórmula de cálculo e período de referência ao hover.
- **FR-031**: O dashboard MUST renderizar carga inicial em ≤ 3 segundos (P95) com cache de 5 minutos configurável; botão "Atualizar agora" disponível com rate-limit 1 req/min.
- **FR-032**: Cálculo de MRR MUST excluir tenants em trial e tenants suspensos.
- **FR-033**: Cálculo de churn 30d MUST usar fórmula: `(tenants_cancelados_no_periodo / tenants_ativos_no_inicio_do_periodo) × 100`.
- **FR-034**: Conversão trial→pago 90d MUST usar: `(tenants_que_saíram_de_trial_e_assinaram_no_período / tenants_que_terminaram_trial_no_período) × 100`.

#### Auditoria do Onboarding (US-1.1, US-1.2)

- **FR-035**: O sistema MUST aplicar middleware `tenant.onboarded` em todas as rotas de domínio (pacientes, anotações, agenda, prescrições, inbox, leads, tags, profissionais) verificando que `tenant.onboarding_state.steps.clinic_data.status === 'completed'`. Caso pendente, retornar HTTP 403 com payload `{ "error": "onboarding_required_step_pending", "step": "clinic_data", "wizard_url": "/onboarding" }`.
- **FR-036**: Rotas exceto do gate: `/auth/*`, `/onboarding/*`, `/me`, `/tenant`, `/billing/*` (para permitir assinar plano antes de completar onboarding).
- **FR-037**: O job `SendWelcomeEmailJob` MUST ser enfileirado dentro da transação de criação do tenant (commit-after pattern com `DB::afterCommit`) e MUST entregar e-mail contendo:
  - Saudação com `responsavel.nome`
  - Link único (com token assinado, TTL 7 dias) para `/onboarding` que faz login automático e abre o wizard
  - Checklist visual das 5 etapas
  - Informação sobre `trial_ends_at` formatada em pt-BR
- **FR-038**: O wizard de onboarding MUST suportar todas as 5 etapas definidas (`clinic_data`, `first_professional`, `channel_connection`, `schedule_setup`, `ai_knowledge_base`) — apenas `clinic_data` é required.
- **FR-039**: O `progress_percent` retornado por `GET /onboarding/state` MUST refletir apenas required steps (mantém comportamento atual). A UI MUST adicionalmente exibir `optional_completed_count / optional_total_count` para passos sugeridos.
- **FR-040**: O documento `docs/user-stories.md` MUST ser atualizado refletindo o status correto de cada US (Pendente / Em Andamento / Entregue [Fase X — data — link spec]). Esta atualização é parte do entregável da US-4 desta spec.
- **FR-041**: O sistema MUST gerar evento `OnboardingRequiredStepBlocked{tenant_id, user_id, step, requested_route}` quando o middleware bloqueia uma chamada — métrica para o dashboard Super Admin medir tenants "presos" no onboarding.

#### Audit Log Super Admin (US-5)

- **FR-042**: O sistema MUST registrar audit log para toda ação Super Admin com: `id` (UUID v7), `actor_user_id`, `actor_ip`, `actor_user_agent`, `action` (enum string), `target_type` (`tenant` / `plan` / `user`), `target_id`, `reason` (texto obrigatório), `before_json`, `after_json`, `created_at`.
- **FR-043**: O sistema MUST garantir que o campo `before_json` e `after_json` NÃO contém PII clínica (somente metadados — `status`, `plan_id`, `trial_ends_at`, `nome`, `slug`). Conteúdo de mensagem, prontuário, paciente, prescrição NUNCA é gravado.
- **FR-044**: A tabela de audit log MUST ser append-only — não há rotas de UPDATE/DELETE via interface. Retenção mínima 5 anos para ações administrativas, 10 anos para impersonation events.
- **FR-045**: O painel `/admin/audit-logs` MUST oferecer filtros por `actor`, `action`, `target_tenant_id`, `period`.
- **FR-046**: Cada entrada no painel MUST mostrar `quando`, `quem`, `ação`, `tenant_alvo`, `motivo`, `IP`, `before/after diff` formatado.
- **FR-047**: Eventos de domínio gerados durante impersonação MUST conter `actor.impersonated_by = {super_admin_id}` em seus payloads.

### Key Entities

- **Tenant**: Entidade existente (Fase 0). Atributos relevantes para esta spec: `id`, `nome`, `slug`, `cnpj`, `status`, `trial_ends_at`, `plan_id`, `onboarding_state`, `created_at`. Sem novos campos.
- **Plan**: Entidade existente, expandida. Campos novos/garantidos: `is_active`, `is_public`, `ordem_exibicao`, `features` (JSON), `cota_mensagens_ia_inclusas`, `preco_excedente_msg_centavos`. Garantir `slug` UNIQUE.
- **SuperAdminAuditLog**: Nova entidade. Tabela `super_admin_audit_logs` com os campos do FR-042. Append-only, índices em `(actor_user_id, created_at)`, `(target_type, target_id)`, `(action, created_at)`.
- **ImpersonationSession**: Nova entidade. Tabela `impersonation_sessions` com `id`, `super_admin_id`, `target_tenant_id`, `target_user_id`, `reason`, `started_at`, `ended_at`, `duration_seconds`, `requests_count`, `ended_reason` (manual/timeout/tenant_suspended). UNIQUE constraint impede duas sessões ativas do mesmo Super Admin.
- **PlatformMetricSnapshot** (derivada): Tabela `platform_metric_snapshots` (snapshot diário) com `snapshot_date`, `mrr_centavos`, `tenants_ativos`, `tenants_trial`, `consumo_ia_mes`, `conversao_trial_pago_pct`, `churn_pct`. Permite séries temporais sem recalcular agregados pesados a cada request.
- **OnboardingState** (JSON em `tenant.onboarding_state`): Estrutura existente. Esta spec apenas valida e adiciona evento `OnboardingRequiredStepBlocked`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Time de suporte resolve 95% das demandas de tenant management (suspender, reativar, alterar plano, estender trial) sem precisar abrir o banco de dados, em ≤ 2 minutos por demanda.
- **SC-002**: Dashboard `/admin` carrega 6 KPIs + 3 gráficos em ≤ 3 segundos (P95) com até 1.000 tenants na base.
- **SC-003**: 100% das ações Super Admin (suspender, impersonar, alterar plano, criar plano, etc.) geram entry em `super_admin_audit_logs` — verificado por teste automatizado.
- **SC-004**: Toda sessão de impersonação tem `started_at` E `ended_at` registrados (taxa de sessões órfãs < 1%, com cron de cleanup forçando `ended_at` em sessões > 4h sem activity).
- **SC-005**: 0 vazamentos de PII clínica em audit logs ou eventos de Super Admin — gate por teste de reflection no `before_json/after_json` (similar ao `PrescriptionEventPayloadLgpdTest` da Fase 7).
- **SC-006**: 100% das rotas de domínio (pacientes, anotações, agenda, prescrições, inbox, leads, tags, profissionais) aplicam o gate `tenant.onboarded` — verificado por teste de cobertura de middleware.
- **SC-007**: E-mail de boas-vindas chega ao Admin Clínica em ≤ 60 segundos após o `POST /tenants/register` retornar 201 (P95) — verificado por teste E2E com Mailpit/Mailhog.
- **SC-008**: Apêndice "Status das User Stories" em `docs/user-stories.md` reflete a realidade com 100% de acurácia (cada US tem status correto, data de entrega quando aplicável e link para spec) — validação manual + revisão.
- **SC-009**: MRR mensal calculado pelo dashboard difere em < 1% do MRR calculado por planilha financeira externa (reconciliação mensal).
- **SC-010**: Tenants "presos no onboarding" >7d são identificados no widget "Tenants em alerta" em 100% dos casos — verificável por seed + verificação visual.

## Assumptions

- **Acesso super-admin**: Assumimos que existe ao menos um usuário com role `super-admin` seedado em produção/staging. A criação do primeiro Super Admin é via seeder/artisan command (já existente ou via spec separada de provisioning) — fora desta spec.
- **Painel em domínio separado**: O painel super-admin reusa o painel Filament existente em `crm.com.br/admin` (Fase 0/4) — domínio diferente do API tenant para isolamento de cookies.
- **Auth super-admin**: Cookie session no domínio `crm.com.br` (não Bearer) — padrão já estabelecido na Fase 4. MFA fica fora desta spec.
- **Billing**: Esta spec MUDA o vínculo lógico de plano (`tenant.plan_id`), mas NÃO interage com Stripe/Cashier. Mudança de cobrança real depende de spec de billing futura. Quando o Super Admin altera o plano, o tenant é informado por e-mail e o ajuste na fatura é responsabilidade da próxima spec.
- **IA Matricial fora**: Métricas de consumo IA (cota, excedente) assumem que existe um módulo de IA registrando consumo em alguma tabela (esperado na spec futura de IA Matricial). Nesta spec, se a tabela não existir ainda, o dashboard mostra "0" e o widget "Top consumidores IA" fica vazio sem quebrar.
- **Audit log existente**: Aproveitar a infraestrutura `Auditable` + `audit_logs` da Fase 2 (CRM Pacientes) onde possível, mas a tabela `super_admin_audit_logs` é dedicada por ter retenção e schema distintos (append-only + diff JSON estruturado).
- **Filament 5 + Vue inbox coexistem**: Painel super-admin é 100% Filament 5 (Livewire/Alpine). Não há Vue SPA neste painel. As capacidades de Vue do tenant continuam intactas.
- **Cache Redis disponível**: Dashboard usa Redis para cache 5min de KPIs agregados (já disponível desde Fase 0).
- **Tenants em trial sem plano**: Em trial, `tenant.plan_id` pode ser NULL (default `Starter` é opcional). Dashboard trata NULL como "Sem plano".
- **Datas em pt-BR**: Toda data exibida no painel super-admin é formatada em `dd/mm/yyyy HH:MM` no fuso `America/Sao_Paulo`.
- **Performance da query cross-tenant**: A consulta de listagem de tenants ignora global scopes de tenancy via método dedicado (`Tenant::withoutTenantScope()` ou equivalente) — guard rail por teste para não vazar em outros lugares.

## Dependências

- **Fase 0 (001-fundacao-multitenant)**: Tenant model, Plan model, autenticação web/cookie no painel admin, OnboardingService com 5 STEPS — ✅ entregue.
- **Fase 4 (004-token-auth-migration)**: Separação de auth API (Bearer) vs painel admin (cookie) — ✅ entregue.
- **Cashier base**: `CheckoutController`, `StripeWebhookController`, `Plan` model — ✅ existe (Fase 0/1) mas fluxo completo é spec separada.
- **Spatie Laravel Permission**: Role `super-admin` no guard `web` — ✅ disponível.
- **Filament 5**: TenantResource, PlanResource, PlatformMetrics page já existem parcialmente — esta spec completa.
- **Não depende** de: IA Matricial (Épico 5), Billing avançado (US-1.3/1.4/1.5 completo), Campanhas, Webhooks externos.

## Out of Scope (explicit)

- Checkout Stripe completo, proration, dunning, falhas de pagamento (Épico 1 / spec separada).
- IA Matricial (Épico 5).
- Recuperação de senha (US-2.3), MFA, SSO.
- Webhooks de eventos externos (US-11.1).
- API pública documentada (US-11.2).
- Direito ao esquecimento LGPD (US-13.2 — spec separada).
- Onboarding com tour guiado in-app, vídeos, gamificação — apenas o wizard 5 etapas já existente é validado/refinado.
- Pricing page pública customizável.
