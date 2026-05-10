# Feature Specification: Fase 0 — Fundação Multi-tenant, Autenticação e Gestão de Usuários

**Feature Branch**: `001-fundacao-multitenant`
**Created**: 2026-05-10
**Status**: Draft
**Input**: User description: "Fase 0 do CRM Médico SaaS — Fundação Multi-tenant, Autenticação e Gestão de Usuários (US-1.1 a 1.5 + US-2.1 a 2.4)."

## Visão Geral da Fase

Esta fase entrega a **fundação operacional da plataforma SaaS multi-tenant**:
um visitante consegue criar a conta da sua clínica, completar o onboarding,
contratar/ajustar um plano comercial, ter visibilidade do consumo da cota
mensal de mensagens IA, autenticar usuários internos com senha forte,
montar a equipe via convite por e-mail, recuperar senhas e auditar ações
sensíveis. Sem essa fase, nenhuma das demais (pacientes, inbox, IA, agenda,
receituários, campanhas, relatórios) tem onde rodar — todo dado e toda ação
delas são escopados ao tenant criado aqui.

A fase NÃO entrega nenhum fluxo de paciente nem qualquer canal de
comunicação externo. Ela termina quando uma clínica recém-cadastrada tem
plano contratado, equipe convidada com permissões aplicadas, login
funcional para todos os perfis e log de auditoria registrando os eventos
exigidos pela LGPD.

## Clarifications

### Session 2026-05-10

- Q: Estratégia de isolamento multi-tenant (database-per-tenant, schema-per-tenant ou single-DB com `tenant_id`)? → A: **Single-database com `tenant_id` + global scope no ORM.** Toda tabela de domínio carrega `tenant_id`; global scope aplica o filtro automaticamente em toda query; teste de isolamento cobrindo 100% dos endpoints autenticados é gate de merge.
- Q: Resolução de tenant em ambiente local (DNS local, wildcard público, header ou query)? → A: **Subdomínio real via DNS público wildcard** (`lvh.me`, `nip.io` ou equivalente que resolve `*.lvh.me → 127.0.0.1`). Zero-config para o desenvolvedor; dev, CI e produção compartilham o **mesmo** code path de resolução de tenant (middleware lê do host). Nada de header `X-Tenant` ou query param.
- Q: Comportamento do tenant Inadimplente após 7 dias de carência? → A: **Bloqueio seletivo de funcionalidades não-essenciais** ("graceful degradation"). **Bloqueado** após 7 dias: motor de IA (todas as conversas caem em modo manual), disparos em massa/campanhas, geração de relatórios pesados, integrações externas (webhooks de saída, Google/Outlook sync), configurações administrativas (planos, integrações, base de conhecimento da IA). **Preservado**: login de toda a equipe, inbox manual, agenda, leitura/escrita de pacientes/receituários, confirmações automáticas de consulta já agendadas. Banner persistente em destaque para Admin Clínica e Financeiro até regularização. Após 30 dias adicionais sem pagamento, tenant é suspenso pelo Super Admin (fluxo separado, não automático nesta fase).
- Q: Comportamento ao atingir o hard cap de mensagens IA? → A: **Degradação para template estático + escalonamento humano.** A partir do cap, a IA para de gerar respostas; toda mensagem recebida dispara um template aprovado pela Meta tipo "Recebemos sua mensagem e retornaremos em breve" e a conversa entra na fila prioritária da inbox para um atendente humano assumir. Custo de LLM = zero a partir do cap. Admin é notificado por e-mail quando o cap é atingido e quando o ciclo vira (cap reseta).
- Q: Retenção máxima do log de auditoria além do mínimo de 1 ano? → A: **2 anos em hot storage + 5 anos em cold storage + deleção física aos 5 anos.** Hot = consultável e exportável direto pelo painel do Admin Clínica. Cold = arquivado em storage frio (acesso por solicitação interna com SLA de até 5 dias úteis). Deleção física aos 5 anos demonstra conformidade com LGPD Art. 16 (minimização). Aplica-se uniformemente a todos os tipos de evento auditável.

## User Scenarios & Testing *(mandatory)*

> Cada user story é testável de forma independente. As histórias P1
> formam o caminho mínimo para uma clínica começar a operar; as P2
> são refinamentos de operação contínua e compliance.

### User Story 1 — Cadastro de Nova Clínica (US-1.1) (Priority: P1)

**Como** Visitante interessado em contratar a plataforma
**Quero** me cadastrar e criar a conta da minha clínica
**Para que** eu possa começar a usar o sistema em período de trial sem
fricção comercial.

**Why this priority**: É o ponto de entrada absoluto. Sem cadastro de
tenant, nenhuma das demais histórias é exercitável. Bloqueia toda a
plataforma.

**Independent Test**: Um visitante completa o formulário de cadastro,
recebe e-mail de boas-vindas e consegue acessar a aplicação em
período de trial — sem precisar de plano contratado, equipe convidada
ou qualquer outra história desta fase.

**Acceptance Scenarios**:

1. **Given** um visitante com e-mail e CNPJ ainda não cadastrados,
   **When** preenche o formulário (nome da clínica, CNPJ, nome do
   responsável, e-mail, telefone, senha) e aceita os Termos de Uso e
   a Política de Privacidade, **Then** o sistema cria o tenant
   isolado, gera um identificador de tenant (slug) único, ativa
   trial de 14 dias sem exigir cartão e envia e-mail de boas-vindas.
2. **Given** um e-mail OU CNPJ já cadastrados, **When** o visitante
   tenta cadastrar, **Then** o sistema rejeita com mensagem clara e
   oferece o caminho de recuperação de senha.
3. **Given** o aceite de Termos/Política não foi marcado, **When** o
   visitante tenta submeter, **Then** o cadastro é bloqueado e a
   ausência do consentimento é sinalizada como obrigatória.
4. **Given** o cadastro foi concluído, **When** observamos o estado
   inicial, **Then** o usuário criador tem perfil **Admin Clínica**,
   o consentimento de Termos/Política está registrado com data e
   finalidade, e o tenant está marcado como "Trial".
5. **Given** o cadastro foi concluído, **When** o visitante tenta
   acessar dados de qualquer outro tenant existente, **Then** o
   sistema rejeita o acesso (isolamento multi-tenant).

**Dependências**: nenhuma técnica externa. Pré-requisito conceitual:
estratégia de isolamento de tenant definida (ver Q1 abaixo).

**Riscos**: deduplicação por CNPJ não detectar variantes (com/sem
máscara); validação fraca de CNPJ permitir cadastros sintéticos;
identificador de tenant gerado ser preditível e habilitar enumeração.

**Pontos de ambiguidade**: Q1 (estratégia de isolamento) e Q2
(formato do subdomínio em dev) impactam diretamente como o tenant é
criado e referenciado.

---

### User Story 2 — Onboarding Guiado da Clínica (US-1.2) (Priority: P1)

**Como** Admin Clínica recém-cadastrado
**Quero** seguir um onboarding passo a passo
**Para que** o sistema esteja minimamente configurado para uso quando
eu sair da tela de boas-vindas.

**Why this priority**: Sem onboarding, o Admin Clínica chega a um
painel vazio e fica sem ação seguinte. A retenção pós-cadastro depende
diretamente desse fluxo guiado.

**Independent Test**: Um Admin Clínica recém-criado consegue,
sozinho, percorrer o wizard até o estado "configuração mínima
concluída" sem precisar de assistência humana.

**Acceptance Scenarios**:

1. **Given** Admin Clínica em primeiro login, **When** acessa o
   painel, **Then** o wizard de onboarding aparece com etapas
   ordenadas e indicador percentual de conclusão.
2. **Given** etapas do wizard, **When** o Admin progride, **Then** o
   estado de progresso é persistido entre sessões (sair/voltar
   retoma de onde parou).
3. **Given** uma etapa marcada como não-bloqueante, **When** o Admin
   opta por pular, **Then** o sistema permite skip e marca a etapa
   como pendente.
4. **Given** todas as etapas bloqueantes concluídas, **When** o
   onboarding termina, **Then** as funcionalidades pertinentes às
   futuras fases ficam habilitadas para uso (ex.: cadastro de
   pacientes, mas isto está fora desta fase).
5. **Given** o Admin não completou onboarding, **When** acessa a
   aplicação, **Then** o wizard é re-exibido como ação principal
   sugerida (não bloqueia o resto da app, mas é a CTA primária).

**Dependências**: US-1.1 (tenant precisa existir).

**Riscos**: wizard com etapas demais cansa o Admin (drop-off);
persistência de estado parcial pode entrar em estado inconsistente
se o Admin trocar de dispositivo no meio.

**Pontos de ambiguidade**: nesta fase, várias etapas do wizard
referenciam módulos fora de escopo (cadastro de profissional,
conexão de canal, configuração da agenda, base de conhecimento da
IA). Esta fase entrega APENAS o **esqueleto do wizard** com a
primeira etapa exercitável ("dados da clínica") e placeholders
desabilitados para as demais. As etapas futuras serão habilitadas
em fases posteriores. Esta limitação é decisão de escopo, não
NEEDS_CLARIFICATION.

---

### User Story 3 — Assinatura de Plano (US-1.3) (Priority: P1)

**Como** Admin Clínica com trial ativo
**Quero** assinar um plano pago
**Para que** eu continue usando após o período de trial.

**Why this priority**: O trial expira em 14 dias. Sem caminho de
assinatura funcional, o tenant fica órfão e a operação para. É P1
porque define a viabilidade comercial da plataforma.

**Independent Test**: Um Admin Clínica em trial conclui um checkout
de plano, vê a confirmação por e-mail e o tenant transita de
"Trial" para "Ativo" — sem depender de pacientes, inbox ou qualquer
módulo posterior.

**Acceptance Scenarios**:

1. **Given** Admin Clínica em trial, **When** acessa a página de
   planos, **Then** o sistema exibe: preço base por profissional
   ativo, cota mensal de mensagens IA inclusa e valor unitário por
   mensagem excedente.
2. **Given** Admin escolhe quantidade inicial de profissionais,
   **When** confirma o plano, **Then** o checkout integrado com
   gateway de pagamento externo é apresentado e processa cartão.
3. **Given** o pagamento é confirmado pelo gateway, **When** o
   webhook chega, **Then** o tenant transita para "Ativo", a
   cobrança recorrente mensal fica programada e e-mail de
   confirmação é enviado.
4. **Given** falha de pagamento, **When** o gateway notifica falha,
   **Then** o tenant entra em estado **Inadimplente** após 3
   tentativas e fica nesse estado por 7 dias antes de bloqueios
   serem aplicados (ver Q4 sobre o que exatamente é bloqueado).
5. **Given** trial expirado sem assinatura, **When** o Admin
   tenta usar a aplicação, **Then** acesso é restringido a uma
   tela única de "renovar/assinar" e a operação cotidiana fica
   bloqueada.
6. **Given** assinatura ativa, **When** o ciclo mensal vira,
   **Then** o sistema cobra automaticamente o valor base + qualquer
   excedente do mês anterior.

**Dependências**: US-1.1 (tenant em trial existe); US-1.2 (Admin
deve estar minimamente configurado para entender o que está
contratando).

**Riscos**: divergência entre status de pagamento no gateway e no
nosso lado (webhook perdido); webhooks duplicados gerando dupla
cobrança ou dupla ativação; dunning poor → churn evitável.

**Pontos de ambiguidade**: Q4 (estado Inadimplente — quais
funcionalidades ficam bloqueadas).

---

### User Story 4 — Upgrade/Downgrade de Plano (US-1.4) (Priority: P2)

**Como** Admin Clínica
**Quero** alterar o plano ou o número de profissionais
**Para que** o custo acompanhe o uso real da clínica.

**Why this priority**: Não é first-day blocker, mas é necessário
nos primeiros 60-90 dias quando a clínica começa a expandir/contrair
a equipe. Aceita-se entregar logo depois das histórias P1.

**Independent Test**: Um Admin Clínica com assinatura ativa adiciona
1 profissional, confirma cobrança proporcional (proration) e vê o
plano atualizado — sem precisar testar fluxo de paciente ou IA.

**Acceptance Scenarios**:

1. **Given** Admin Clínica com plano ativo, **When** adiciona N
   profissionais no painel de billing, **Then** o sistema calcula
   proration automaticamente e cobra o valor proporcional na
   próxima fatura.
2. **Given** Admin Clínica com plano ativo, **When** remove
   profissionais, **Then** a redução de cobrança vigora apenas no
   próximo ciclo (não bloqueia recursos imediatamente).
3. **Given** alteração efetivada, **When** observamos os registros,
   **Then** existe entrada de histórico com: ator, alteração feita,
   timestamp, valor anterior, valor novo.
4. **Given** alteração efetivada, **When** observamos as
   notificações, **Then** o Admin recebeu e-mail confirmando a
   mudança.

**Dependências**: US-1.3 (precisa ter plano contratado).

**Riscos**: proration calculada inconsistentemente entre nosso
estado e o gateway; race condition em alterações simultâneas
disparadas por múltiplos administradores.

**Pontos de ambiguidade**: nenhum específico desta US.

---

### User Story 5 — Monitoramento de Cota de Mensagens IA (US-1.5) (Priority: P2)

**Como** Admin Clínica
**Quero** acompanhar o consumo de mensagens IA do mês
**Para que** eu possa prever excedentes e ajustar o plano antes de
ser surpreendido na fatura.

**Why this priority**: Custo variável de IA é o pesadelo financeiro
do plano híbrido. O Admin precisa de visibilidade preditiva, não
apenas reativa. P2 porque o consumo só vira material depois que a
fase 1 (atendimento + IA) estiver entregando.

**Independent Test**: Um Admin Clínica visualiza um dashboard de
consumo (mesmo que zero, na ausência das fases posteriores) com
todos os campos descritos, e configura um hard cap. A interface e a
infra de leitura/configuração ficam prontas para serem alimentadas
quando a IA entrar em produção.

**Acceptance Scenarios**:

1. **Given** Admin Clínica autenticado, **When** acessa o painel de
   consumo IA, **Then** vê: cota inclusa do plano, consumido até
   agora no ciclo, projeção para o fim do mês (linear), custo
   estimado de excedente.
2. **Given** consumo atingindo 80% da cota, **When** o limiar é
   ultrapassado, **Then** o sistema dispara alerta por e-mail ao
   Admin Clínica.
3. **Given** consumo atingindo 100% da cota, **When** o limiar é
   ultrapassado, **Then** o sistema dispara alerta por e-mail e
   marca status "em excedente" no painel.
4. **Given** Admin Clínica configura um hard cap de gasto, **When**
   o consumo atinge o cap, **Then** o comportamento configurado
   (ver Q5) é aplicado.
5. **Given** o ciclo mensal vira, **When** o relógio do faturamento
   passa do dia 1, **Then** o consumo é arquivado em histórico e o
   contador zera.

**Dependências**: US-1.3 (precisa ter plano para haver cota).

**Riscos**: contagem de mensagens IA imprecisa (race condition em
incremento concorrente); projeção linear ingênua subestimar picos
sazonais; o Admin perceber alerta tarde demais.

**Pontos de ambiguidade**: Q5 (comportamento exato do hard cap).

---

### User Story 6 — Login de Usuário Interno (US-2.1) (Priority: P1)

**Como** Usuário interno do tenant (Admin, Médico, Atendente,
Recepcionista, Financeiro)
**Quero** autenticar com e-mail e senha
**Para que** eu acesse o painel da clínica de forma segura.

**Why this priority**: Sem login, ninguém entra na aplicação além
do criador inicial. P1 absoluto.

**Independent Test**: Um usuário convidado em estado "ativo" insere
e-mail/senha corretos e chega ao dashboard apropriado ao seu perfil.

**Acceptance Scenarios**:

1. **Given** usuário com credenciais válidas, **When** envia e-mail
   e senha, **Then** sessão é estabelecida e redireciona ao dashboard
   apropriado ao perfil.
2. **Given** 5 tentativas de login falhas consecutivas, **When** a
   sexta tentativa ocorre, **Then** o login é bloqueado
   temporariamente para aquele usuário/IP por janela configurada.
3. **Given** sessão ativa, **When** o tempo configurado expira sem
   atividade, **Then** a sessão é encerrada e o usuário é
   redirecionado ao login.
4. **Given** login bem-sucedido em qualquer perfil, **When**
   inspecionamos o log de auditoria, **Then** existe entrada com:
   tenant, usuário, timestamp, IP, user-agent.

**Dependências**: US-1.1 (tenant existe); US-2.2 (para haver mais
de um usuário a logar — mas o usuário criador da US-1.1 já consegue
logar de saída).

**Riscos**: enumeração de usuários via mensagens de erro distintas
("e-mail não existe" vs "senha incorreta"); bloqueio temporário ser
DoS-able.

**Pontos de ambiguidade**: nenhum específico desta US.

---

### User Story 7 — Cadastro de Usuários Internos (US-2.2) (Priority: P1)

**Como** Admin Clínica
**Quero** cadastrar atendentes, médicos e financeiro
**Para que** a equipe acesse o sistema com permissões adequadas.

**Why this priority**: Sem equipe convidada, a clínica é um Admin
sozinho — não há operação. P1.

**Independent Test**: Um Admin convida um membro com perfil X, o
membro recebe e-mail, define senha, faz primeiro login, vê apenas
as funcionalidades autorizadas para o perfil X.

**Acceptance Scenarios**:

1. **Given** Admin Clínica autenticado, **When** preenche o
   formulário de convite (nome, e-mail, perfil, profissionais
   vinculados quando aplicável), **Then** convite por e-mail é
   enviado com link de definição de senha válido por 24h.
2. **Given** convite enviado, **When** o destinatário não aceita
   em 24h, **Then** o link expira; Admin pode reenviar.
3. **Given** convite aceito, **When** o convidado define a senha,
   **Then** o usuário é marcado como ativo, recebe permissões do
   perfil escolhido (via política de permissões granular) e pode
   fazer login.
4. **Given** plano contratado tem limite de N usuários, **When**
   o N+1 convite seria enviado, **Then** o sistema bloqueia e
   instrui o Admin a fazer upgrade.
5. **Given** Admin remove um usuário, **When** a remoção é
   confirmada, **Then** o usuário é desativado (não excluído
   fisicamente — preserva auditoria), suas sessões ativas são
   encerradas e ele perde acesso imediatamente.
6. **Given** alteração de perfil de um usuário, **When** Admin
   muda perfil, **Then** as permissões da nova função aplicam-se
   no próximo login (ou imediatamente, se sessão ativa).

**Dependências**: US-1.1 (tenant); US-2.1 (login deve funcionar
para o convidado conseguir acessar).

**Riscos**: link de convite vazar permite cadastro indevido;
permissões mal-mapeadas dão acesso a fluxos fora do perfil;
remoção de usuário deixar sessão ativa por descuido.

**Pontos de ambiguidade**: nenhum específico desta US.

---

### User Story 8 — Recuperação de Senha (US-2.3) (Priority: P2)

**Como** Usuário interno
**Quero** redefinir minha senha
**Para que** eu recupere acesso quando esquecer.

**Why this priority**: Indispensável a médio prazo, mas não bloqueia
go-live se a equipe inicial guarda bem suas senhas. Pode entrar
logo após as P1.

**Independent Test**: Um usuário ativo solicita recuperação,
recebe e-mail, troca senha pelo link, faz login com a nova senha
e recebe notificação de troca. Solicitação para e-mail inexistente
não revela existência.

**Acceptance Scenarios**:

1. **Given** usuário existente, **When** clica em "Esqueci minha
   senha" e informa o e-mail, **Then** sistema envia e-mail com
   token de uso único válido por 60 minutos.
2. **Given** e-mail informado NÃO existe na base, **When** o
   formulário é submetido, **Then** o sistema responde com a mesma
   mensagem genérica de sucesso (não revela existência da conta).
3. **Given** token válido e dentro do prazo, **When** usuário
   acessa o link e define nova senha, **Then** a senha atende à
   política mínima (≥ 8 caracteres, com maiúscula e número), o
   token é invalidado e a senha antiga não funciona mais.
4. **Given** token usado, **When** usuário tenta usar o mesmo
   link novamente, **Then** o sistema rejeita.
5. **Given** senha trocada, **When** o sucesso é confirmado,
   **Then** e-mail de notificação é enviado ao usuário e o evento
   é registrado no log de auditoria.

**Dependências**: US-2.1 (login para validar a nova senha).

**Riscos**: tokens previsíveis; e-mail revelando existência via
canais oblíquos (resposta de tempo distinta); usuário confundir
o e-mail de notificação de troca legítima com phishing.

**Pontos de ambiguidade**: nenhum específico desta US.

---

### User Story 9 — Log de Auditoria (US-2.4) (Priority: P2)

**Como** Admin Clínica
**Quero** consultar log de ações sensíveis
**Para que** eu rastreie alterações e atenda exigências de LGPD.

**Why this priority**: A LGPD exige; sem o log, a clínica fica
descoberta legalmente. Mas o registro inicia desde o dia 1; o
PAINEL de consulta pode entrar logo após as P1.

**Independent Test**: Após exercitar US-1.x e US-2.x, o Admin
Clínica abre o painel de auditoria, filtra por usuário e data, vê
as ações sensíveis com timestamps e exporta em CSV.

**Acceptance Scenarios**:

1. **Given** ações sensíveis ocorridas (login, mudança de
   permissão, alteração de plano, exclusão de usuário, troca de
   senha, configuração de hard cap), **When** o Admin consulta o
   log, **Then** todas as entradas estão presentes com: tenant,
   ator, ação, alvo, timestamp, IP, user-agent.
2. **Given** painel de log, **When** Admin aplica filtros
   (usuário, intervalo de data, tipo de ação), **Then** os
   resultados são filtrados e paginados.
3. **Given** painel de log, **When** Admin clica em exportar,
   **Then** o sistema gera CSV com os mesmos campos exibidos
   (respeitando o filtro aplicado).
4. **Given** retenção mínima de 1 ano, **When** uma entrada
   completa 1 ano, **Then** ela permanece consultável (não é
   deletada automaticamente; ver Q3 sobre retenção máxima).
5. **Given** tentativa de leitura de log de outro tenant,
   **When** o request chega, **Then** é rejeitada (isolamento).

**Dependências**: US-1.1 a US-2.3 — todas as ações sensíveis
geradas precisam ter um produtor de evento já implementado.

**Riscos**: log inconsistente (algum produtor esquece de emitir
o evento); volume crescer sem limite e degradar performance;
exportação CSV mal escapada virar vetor de injeção em planilhas.

**Pontos de ambiguidade**: Q3 (retenção além do mínimo de 1 ano).

---

### Edge Cases (toda a fase)

- **Cadastro com CNPJ formatado vs não formatado**: tratar como o mesmo (canonicalizar antes de comparar duplicatas).
- **Visitante que abandona o cadastro no meio**: nenhum tenant é criado até a confirmação completa; dados parciais não persistem.
- **Tenant criado mas trial expirado antes de qualquer login do convite**: o convite continua válido pelas 24h, mas ao logar o convidado cai na tela de "renovar/assinar" se ele for Admin Clínica.
- **Admin Clínica único decide remover a si mesmo**: o sistema bloqueia (deve haver pelo menos 1 Admin ativo por tenant).
- **Conflito de e-mail entre tenants**: o mesmo e-mail pessoal pode aparecer como usuário em dois tenants distintos. A identidade é (tenant_id, e-mail), não e-mail global.
- **Falha do gateway de pagamento durante upgrade**: a mudança não é aplicada localmente; o estado anterior permanece e o Admin recebe erro acionável.
- **Race em incremento de cota IA**: dois jobs concorrentes incrementando o contador devem produzir resultado consistente (sem perda).
- **Token de recuperação de senha solicitado várias vezes**: cada novo token invalida os anteriores; só o último vale.
- **Tenant suspenso pelo Super Admin**: nenhum login não-Super-Admin consegue acessar; mensagem clara é exibida.

## Requirements *(mandatory)*

### Functional Requirements

#### Multi-tenancy e Tenant lifecycle

- **FR-001**: O sistema MUST permitir cadastro de novo tenant por
  visitante anônimo via formulário público, exigindo: nome da
  clínica, CNPJ, nome do responsável, e-mail, telefone, senha e
  aceite explícito de Termos de Uso e Política de Privacidade.
- **FR-002**: O sistema MUST garantir unicidade global de e-mail e
  CNPJ entre tenants, com canonicalização de CNPJ
  (com/sem máscara) antes da checagem.
- **FR-003**: O sistema MUST gerar identificador único de tenant
  (slug) no cadastro e MUST permitir customização do slug pelo
  Admin Clínica em momento posterior. Regras do slug:
  - Formato: lowercase, `[a-z0-9-]`, 3–63 caracteres (RFC 1035).
  - Slugs **reservados** (não permitidos para tenants): `api`,
    `admin`, `panel`, `www`, `app`, `auth`, `static`, `assets`,
    `mail`, `ftp`, `cdn`, `status`, `support`, `help`. Tentativa
    de cadastro/customização com slug reservado retorna erro de
    validação.
  - Em colisão com slug existente, o sistema MUST sugerir o slug
    desejado com sufixo numérico (ex.: `clinica-alfa-2`).
- **FR-004**: O sistema MUST aplicar isolamento de dados entre
  tenants em **todas** as operações de leitura/escrita: queries de
  domínio, jobs em fila, broadcasts, caches e índices de busca. O
  método é **single-database com coluna `tenant_id` em toda tabela
  de domínio + global scope no ORM** que aplica o filtro
  automaticamente em toda query, complementado por prefixo de
  `tenant_id` em chaves Redis e segmentação por tenant em broadcasts.
  Toda PR que toque persistência, fila ou broadcast MUST incluir
  teste de isolamento provando impossibilidade de leitura cruzada
  (constituição, princípio II).
- **FR-005**: O sistema MUST resolver o tenant ativo a partir do
  **subdomínio do host** em todos os ambientes (produção, homologação,
  dev e CI). Em produção, o subdomínio é o slug do tenant em domínio
  próprio (ex.: `<slug>.crm.com.br`). Em dev/CI, é usado um serviço
  público de DNS wildcard que resolve qualquer subdomínio para
  `127.0.0.1` (ex.: `<slug>.lvh.me`, `<slug>.nip.io`). O middleware
  de resolução de tenant é **único** e idêntico em todos os
  ambientes — não há fallback baseado em header `X-Tenant` ou query
  param. Em fluxos não autenticados (cadastro público, login), o
  tenant é resolvido pelo subdomínio do request; o cadastro de
  novo tenant ocorre em domínio principal sem subdomínio (ex.:
  `crm.com.br/cadastro`).
- **FR-006**: O sistema MUST criar trial de 14 dias automaticamente
  no cadastro, sem exigir cartão de crédito.
- **FR-007**: O sistema MUST registrar consentimento de Termos e
  Política com data, canal e finalidade no momento do cadastro.

#### Onboarding

- **FR-008**: O sistema MUST apresentar wizard de onboarding ao
  Admin Clínica em primeiro login, com etapas ordenadas, indicador
  percentual e opção de skip em etapas não-bloqueantes.
- **FR-009**: O sistema MUST persistir o progresso do wizard entre
  sessões, permitindo retomar de onde parou.
- **FR-010**: O sistema MUST entregar nesta fase a etapa "dados da
  clínica" funcional; etapas "primeiro profissional", "conexão de
  canal", "configuração da agenda" e "base de conhecimento da IA"
  ficam como placeholders desabilitados a serem habilitados em
  fases posteriores.

#### Billing e Cota

- **FR-011**: O sistema MUST oferecer página de planos com modelo
  híbrido visível: preço base por profissional ativo + cota mensal
  de mensagens IA inclusas + valor unitário do excedente.
- **FR-012**: O sistema MUST integrar checkout via gateway de
  pagamento externo para captura de cartão e cobrança recorrente
  mensal.
- **FR-013**: O sistema MUST processar webhooks do gateway de forma
  idempotente, garantindo que webhooks duplicados não produzam
  cobrança/ativação dupla.
- **FR-014**: O sistema MUST transitar tenant para estado
  **Inadimplente** após 3 falhas de cobrança e iniciar contagem de
  carência de 7 dias. Após 7 dias, o sistema MUST aplicar **bloqueio
  seletivo de funcionalidades não-essenciais** preservando
  atendimento básico:
  - **Bloqueado**: motor de IA (conversas caem em modo manual sem
    fallback automático), disparos em massa e campanhas, geração de
    relatórios pesados, integrações externas (webhooks de saída,
    sync Google/Outlook), configurações administrativas pesadas
    (planos, integrações, base de conhecimento da IA).
  - **Preservado**: login de toda a equipe, inbox manual, agenda
    (visualização e edição), leitura/escrita de pacientes e
    receituários, confirmações automáticas de consultas já
    agendadas.
  - **UI**: banner persistente em destaque exibido para Admin
    Clínica e Financeiro com CTA para regularizar pagamento, em
    todas as telas, até a regularização.
  - **Escalada**: 30 dias adicionais sem pagamento (total de 37
    dias de inadimplência) tornam o tenant elegível para suspensão
    pelo Super Admin. A suspensão automática NÃO está no escopo
    desta fase — é executada manualmente pelo Super Admin.
- **FR-015**: O sistema MUST permitir alteração de número de
  profissionais ativos com cálculo de proration aplicado na
  próxima fatura; reduções vigoram apenas no próximo ciclo.
- **FR-016**: O sistema MUST registrar histórico de alterações de
  plano (ator, alteração, timestamp, valor anterior/novo) e
  notificar o Admin por e-mail.
- **FR-017**: O sistema MUST exibir painel de cota IA com: cota
  inclusa, consumido no ciclo, projeção de fim de mês, custo
  estimado de excedente.
- **FR-018**: O sistema MUST disparar alerta por e-mail ao Admin
  Clínica em 80% e 100% da cota IA.
- **FR-019**: O sistema MUST permitir configuração de hard cap de
  gasto IA pelo Admin Clínica. Ao atingir o cap, o sistema MUST
  aplicar **degradação para template estático + escalonamento
  humano**:
  - A IA para de gerar respostas para o tenant;
  - Toda mensagem recebida nos canais (WhatsApp, Instagram, web)
    dispara automaticamente um template aprovado pela Meta com
    acknowledgment ("Recebemos sua mensagem e retornaremos em
    breve") respeitando a janela de 24h e os requisitos do
    Princípio VI (Conformidade Meta);
  - A conversa entra na fila prioritária da inbox para um
    atendente humano assumir;
  - Admin recebe notificação por e-mail no momento em que o cap é
    atingido e quando o ciclo vira (cap reseta no próximo mês).
- **FR-020**: O sistema MUST arquivar consumo IA no fim do ciclo
  e zerar o contador para o próximo ciclo.

#### Autenticação e Usuários

- **FR-021**: O sistema MUST autenticar usuários internos via
  e-mail e senha, escopados pelo tenant.
- **FR-021a**: Após login bem-sucedido, o sistema MUST redirecionar
  para `/panel` (raiz do SPA do tenant). A diferenciação fina de
  dashboards por perfil (Admin Clínica, Médico, Atendente,
  Recepcionista, Financeiro) é feita **dentro do `/panel`** pela
  SPA, baseada nas permissões do `AuthenticatedUserResource` (rotas
  bloqueadas/visíveis por perfil). Esta fase entrega o `/panel`
  raiz funcional para Admin Clínica e Financeiro; demais perfis
  fazem login com sucesso mas veem placeholder com lista de
  funcionalidades futuras (entram em fases posteriores).
- **FR-022**: *(removido — 2FA TOTP foi retirado do MVP na constituição
  v1.3.0; pode retornar como opt-in voluntário em fase futura sem
  quebrar contratos.)*
- **FR-023**: O sistema MUST bloquear temporariamente o login após
  5 tentativas falhas consecutivas para o par usuário/IP.
- **FR-024**: O sistema MUST manter sessão com expiração configurável
  (default: 2h de inatividade). Expiração por inatividade
  redireciona ao login. O login MUST aceitar opção "lembrar-me" que,
  quando marcada, estende o cookie de sessão para **30 dias** sem
  renovação por atividade. "Lembrar-me" MUST ser desabilitado para
  perfis Admin Clínica, Super Admin e Financeiro (operações sensíveis
  de billing/usuários exigem login fresco em cada janela de uso).
- **FR-025**: O sistema MUST permitir ao Admin Clínica convidar
  usuários internos via e-mail com link válido por 24h. O link de
  aceite MUST apontar para o **subdomínio do tenant convidado**
  (`<slug>.crm.com.br/aceitar?token=…`); o aceite ocorre nesse
  subdomínio e o tenant é resolvido pelo host. Token apresentado em
  subdomínio diferente do dono do convite retorna 410 (defesa em
  profundidade contra reuso cross-tenant).
- **FR-026**: O sistema MUST aplicar permissões granulares por
  perfil (Admin Clínica, Médico, Atendente, Recepcionista,
  Financeiro) em todos os módulos. Um usuário só vê e age sobre
  o que seu perfil autoriza.
- **FR-027**: O sistema MUST respeitar limite de usuários
  conforme plano contratado.
- **FR-028**: O sistema MUST suportar desativação (não exclusão
  física) de usuário, encerrando sessões ativas imediatamente e
  preservando o histórico para auditoria.
- **FR-029**: O sistema MUST manter pelo menos 1 Admin Clínica
  ativo por tenant em todos os momentos (impedir auto-remoção
  quando único Admin).
- **FR-030**: O sistema MUST oferecer recuperação de senha via
  link enviado por e-mail, com token de uso único válido por 60
  minutos.
- **FR-031**: O sistema MUST aplicar política de senha forte:
  mínimo 8 caracteres, ao menos 1 maiúscula e 1 número.
- **FR-032**: O sistema MUST responder com mensagem genérica
  consistente em "esqueci a senha" mesmo quando o e-mail não
  existe (sem revelar existência de conta).
- **FR-033**: O sistema MUST notificar o usuário por e-mail após
  troca de senha bem-sucedida.

#### Auditoria

- **FR-034**: O sistema MUST registrar evento auditável para cada
  ação sensível: cadastro de tenant, login (sucesso/falha), troca
  de plano, alteração de permissão, criação/desativação de usuário,
  troca de senha, configuração de hard cap, e exclusões.
- **FR-035**: Cada entrada de log MUST conter no mínimo: tenant,
  ator, ação, alvo, timestamp, IP, user-agent.
- **FR-036**: O sistema MUST permitir consulta filtrada do log
  (por usuário, data, tipo de ação) com paginação, exclusivamente
  pelo Admin Clínica do tenant proprietário.
- **FR-037**: O sistema MUST permitir exportação do log em CSV
  com escape adequado para evitar injeção em planilhas.
- **FR-038**: O sistema MUST reter logs de auditoria conforme
  política em três tiers, com transição automática:
  - **Hot storage (0–2 anos)**: consultável e exportável
    diretamente pelo painel do Admin Clínica. Latência de leitura
    consistente com SC-008 (≤ 30s para encontrar evento).
  - **Cold storage (2–5 anos)**: logs arquivados em storage frio
    (custo reduzido). Não aparecem mais no painel diretamente;
    recuperação ocorre por solicitação interna com SLA de até 5
    dias úteis para retorno do dataset.
  - **Deleção física aos 5 anos**: ao completar 5 anos, registros
    são deletados fisicamente, em conformidade com LGPD Art. 16
    (minimização). Job agendado roda mensalmente para aplicar a
    transição e a deleção.
  - O mínimo de 1 ano da constituição é coberto integralmente
    dentro do tier hot.
- **FR-039**: O sistema MUST rejeitar qualquer leitura de log
  vinda de outro tenant (princípio de isolamento).

#### Segurança transversal

- **FR-040**: O sistema MUST armazenar senhas com argon2id
  (preferencial) ou bcrypt com cost ≥ 12.
- **FR-041**: O sistema MUST aplicar rate limiting por tenant E
  por endpoint em todas as APIs públicas.
- **FR-042**: O sistema MUST servir todas as superfícies em pt-BR
  por padrão, com strings localizáveis (i18n-ready).

### Key Entities

- **Tenant**: Entidade raiz da plataforma. Atributos-chave: nome,
  CNPJ canonicalizado, slug, status (Trial, Ativo, Inadimplente,
  Suspenso, Cancelado), data de cadastro, data de fim do trial.
- **Plano**: Catálogo comercial. Atributos: nome, preço base por
  profissional, cota mensal de mensagens IA inclusa, valor por
  excedente, status (ativo/inativo). Tenants existentes não são
  impactados ao editar (snapshot por tenant).
- **Assinatura**: Vínculo Tenant ↔ Plano em vigência. Atributos:
  tenant, plano (snapshot), número de profissionais ativos,
  status, próximo ciclo de cobrança, identificador externo do
  gateway.
- **Usuário Interno**: Pessoa que acessa o painel. Atributos:
  tenant, e-mail, senha hash, perfil(is), status (convidado, ativo,
  desativado), data do primeiro acesso, último login.
- **Convite**: Token de definição de senha. Atributos: tenant,
  e-mail destinatário, perfil pretendido, token, validade, status
  (pendente, aceito, expirado).
- **Permissão**: Grão fino de autorização (módulo + ação),
  agrupada por perfil.
- **Consentimento**: Registro LGPD. Atributos: tenant, sujeito,
  finalidade (Termos, Marketing, etc.), canal, data, estado
  (vigente/revogado).
- **Cota de IA**: Contador mensal por tenant. Atributos: tenant,
  ciclo (ano-mês), inclusa, consumida, hard_cap, projeção.
- **Histórico de Plano**: Registro imutável de cada mudança de
  assinatura.
- **Evento de Auditoria**: Registro imutável de ação sensível.
  Atributos: tenant, ator, ação, alvo, payload (sem PII desnecessária),
  timestamp, IP, user-agent.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Visitante completa cadastro de clínica em ≤ 3 minutos
  (do primeiro clique no formulário até e-mail de boas-vindas
  recebido).
- **SC-002**: Admin Clínica recém-cadastrado completa a etapa
  "dados da clínica" do onboarding em ≤ 2 minutos sem assistência.
- **SC-003**: 95% dos checkouts de plano são concluídos em ≤ 90
  segundos (do clique em "assinar" até confirmação visível).
- **SC-004**: Webhook do gateway de pagamento é processado de
  forma idempotente em 100% dos casos: testes determinam que
  webhooks duplicados nunca produzem dupla cobrança ou dupla
  ativação.
- **SC-005**: Admin convidado faz primeiro login em ≤ 1 minuto
  após clicar no link do e-mail de convite (definição de senha +
  redirect).
- **SC-006**: Usuário recupera acesso via "esqueci minha senha"
  em ≤ 90 segundos (clique no link do e-mail → nova senha →
  login).
- **SC-007**: *(removido — métrica era específica do fluxo 2FA, que
  saiu do MVP na constituição v1.3.0.)*
- **SC-008**: Admin Clínica encontra qualquer evento sensível
  no log de auditoria via filtro em ≤ 30 segundos.
- **SC-009**: Zero ocorrências de leitura cruzada entre tenants
  detectadas em testes automatizados de isolamento que cobrem
  100% dos endpoints autenticados.
- **SC-010**: 100% das ações sensíveis listadas em FR-034 produzem
  exatamente um evento de auditoria correspondente (verificado
  por testes que disparam a ação e contam eventos).
- **SC-011**: Alertas de cota IA (80% e 100%) são entregues ao
  Admin em ≤ 5 minutos do cruzamento do limiar.
- **SC-012**: Bloqueio temporário por tentativas falhas de login
  ativa após exatamente 5 tentativas em 100% dos cenários
  testados (sem flakiness).
- **SC-013**: Tenant em estado Inadimplente após 7 dias tem o
  comportamento de bloqueio definido em Q4 aplicado em ≤ 1 hora
  do cruzamento do prazo (verificado por job agendado e teste).

## Out of Scope (desta fase)

Itens explicitamente **fora** desta fase, a serem entregues em
fases posteriores:

- **Pacientes (CRM Core)**: cadastro, timeline, importação em
  massa, segmentação por tags, funil de leads.
- **Inbox / Atendimento Omnichannel**: WhatsApp, Instagram, widget
  de chat web, atribuição/transferência de conversa, modo "humano
  assume", respostas rápidas.
- **IA Matricial**: base de conhecimento, classificação de
  intenção, agendamento via IA, escalonamento, guardrails de
  segurança clínica, treinamento contínuo, auditoria de decisões
  da IA.
- **Agenda**: configuração de agenda do profissional, tipos de
  atendimento, agendamento manual, confirmação automática,
  reagendamento via chat, lista de espera, sync Google/Outlook.
- **Receituários**: cadastro, upload de PDF, alerta de
  vencimento, renovação via IA.
- **Campanhas e Reativação**: disparo em massa, templates Meta,
  conformidade de disparo, sazonalidade.
- **Relatórios e Dashboard executivo/operacional/clínico**.
- **Webhooks externos e API pública** para integradores.
- **Painel Super Admin (US-12.x)**: gestão global de tenants,
  planos globais, métricas globais. Esta fase entrega apenas a
  base; o painel Super Admin completo entra como fase própria.
- **Cobrança em moedas além de BRL**.
- **Multi-unidade por tenant, telemedicina nativa, prontuário
  eletrônico, pré-pagamento de consulta pelo paciente** (decisões
  fechadas na constituição como fora do MVP).

## Definição de Pronto desta Fase

A fase só é considerada **pronta** quando todos os itens abaixo
estão verdadeiros:

- [ ] Todas as 9 user stories (US-1.1 a 1.5 + US-2.1 a 2.4)
      têm critérios de aceitação atendidos e cobertos por testes.
- [ ] Cobertura de testes do backend ≥ 70% (constituição IV).
- [ ] Testes E2E cobrem as jornadas críticas desta fase:
      cadastro → onboarding → assinatura → login → convite/aceite.
- [ ] Testes de isolamento multi-tenant cobrem 100% dos
      endpoints autenticados, garantindo zero leitura cruzada.
- [x] Os 5 NEEDS_CLARIFICATION foram resolvidos em sessão de
      `/speckit-clarify` (Session 2026-05-10) — ver bloco
      "Histórico de Decisões".
- [ ] Webhook do gateway de pagamento testado para
      idempotência (incluindo cenário de webhook reordenado e
      duplicado).
- [ ] Política de senha forte, hash argon2id/bcrypt cost ≥ 12,
      rate limiting por tenant+endpoint e bloqueio por tentativas
      falhas verificados em testes.
- [ ] Log de auditoria registra 100% das ações sensíveis listadas
      em FR-034 e exporta em CSV escapado.
- [ ] Toda string voltada ao usuário em pt-BR via arquivos de
      tradução (zero hardcoded em UI/controllers).
- [ ] Constitution Check (7 princípios) revisado e aprovado.
- [ ] Documentação OpenAPI atualizada para todos os endpoints
      desta fase.
- [ ] Plan Stripe / gateway de pagamento configurado em ambiente
      de homologação com cartões de teste validados.

## Dependências e Premissas

### Dependências externas

- Provedor de gateway de pagamento (decisão fechada na
  constituição: Stripe) configurado para o ambiente alvo.
- Provedor de envio transacional de e-mail configurado (boas-vindas,
  convites, recuperação de senha, alertas de cota).

### Premissas

- O Super Admin da plataforma já existe ou é provisionado fora do
  fluxo público (seed/setup inicial). Esta fase **não** inclui o
  cadastro público de Super Admins.
- A política de senha (≥ 8, maiúscula, número) é considerada o
  padrão atual; mudanças exigem amendment.
- O modelo "1 e-mail pode aparecer em múltiplos tenants" é
  considerado correto (identidade = tuple tenant + e-mail).
- O ambiente de produção opera em fuso BRT/BRST (afeta cálculo de
  ciclo mensal de cobrança).

## Histórico de Decisões da Sessão de Clarificação

Cinco pontos foram intencionalmente elevados como ambiguidades
explícitas e resolvidos em sessão de `/speckit-clarify`
(2026-05-10). As decisões já estão integradas aos FRs
correspondentes; este bloco preserva o **rationale** de cada
decisão para rastreabilidade.

### Q1: Estratégia de isolamento multi-tenant ✅ RESOLVED (Session 2026-05-10)

**Decisão**: Single-database com coluna `tenant_id` em toda tabela
de domínio + global scope no ORM. Isolamento garantido por código
(scope) + testes obrigatórios cobrindo 100% dos endpoints
autenticados (princípio II da constituição). FR-004 atualizado.

### Q2: Resolução de tenant em ambiente de desenvolvimento local ✅ RESOLVED (Session 2026-05-10)

**Decisão**: Subdomínio real via DNS público wildcard
(`<slug>.lvh.me`, `<slug>.nip.io` etc.) em dev e CI; subdomínio
em domínio próprio em produção. Middleware de resolução único em
todos os ambientes. FR-005 atualizado.

### Q3: Política de retenção de logs de auditoria além do mínimo de 1 ano ✅ RESOLVED (Session 2026-05-10)

**Decisão**: 2 anos em hot storage + 5 anos em cold storage +
deleção física aos 5 anos. Detalhes em FR-038. Tier hot serve o
painel; tier cold é recuperado por solicitação interna; deleção
aos 5 anos cobre LGPD Art. 16.

### Q4: Comportamento do estado Inadimplente após 7 dias ✅ RESOLVED (Session 2026-05-10)

**Decisão**: Bloqueio seletivo de funcionalidades **não-essenciais**
("graceful degradation"). Detalhes em FR-014: bloqueia IA, campanhas,
relatórios pesados, integrações externas e config admin pesada;
preserva login, inbox manual, agenda e operação cotidiana de
pacientes/receituários para não desabrigar pacientes em atendimento.

### Q5: Comportamento do hard cap de mensagens IA ✅ RESOLVED (Session 2026-05-10)

**Decisão**: Degradação para template estático aprovado pela Meta
+ escalonamento da conversa para a fila prioritária da inbox.
Detalhes em FR-019. Mantém canal vivo, custo de LLM = zero a
partir do cap, sem violar o Princípio VI da constituição
(Conformidade Meta nos disparos).
