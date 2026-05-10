<!--
SYNC IMPACT REPORT
==================
Version change: 1.1.0 → 1.2.0
Bump rationale: MINOR — adições materiais sem remoção ou redefinição de
princípios existentes. Adicionados 2 princípios NON-NEGOTIABLE (Meta e
Segurança Operacional), 1 seção (Localização e Idioma), 1 decisão de
produto fechada (cobrança híbrida) e refinamentos pontuais a I, II, IV
e V para incorporar requisitos não cobertos antes.

Modified principles:
  - I. Privacidade, Consentimento e Conformidade LGPD — hash de senha
       refinado para "argon2id ou bcrypt com cost ≥ 12" (era apenas
       "bcrypt/argon2").
  - II. Isolamento Multi-Tenant — reforço explícito: isolamento existe
        desde a primeira PR de domínio, nunca como retrofit.
  - IV. Desenvolvimento Spec-Driven e Test-First — adicionados:
        E2E (Playwright/Cypress) obrigatório nas jornadas críticas
        (onboarding, agendamento via IA, confirmação automática,
        renovação de receita); migrações imutáveis após aplicadas em
        produção; OpenAPI (Scribe) sempre em dia com a API pública.
  - V. Observabilidade e Excelência Operacional — adicionados:
       eventos auditáveis para envios externos e mudanças de estado
       de paciente/agendamento; exportação de métricas via Prometheus
       para ingestão em Grafana.

Added sections:
  - VI. Conformidade Meta nos Disparos (NON-NEGOTIABLE) — janela 24h
       do WhatsApp, templates aprovados, opt-in de marketing, link de
       descadastro em mensagens não transacionais, bloqueio em runtime.
  - VII. Segurança Operacional (NON-NEGOTIABLE) — rate limiting por
        tenant E por endpoint; 2FA TOTP opcional para usuários
        internos e OBRIGATÓRIO para Admin Clínica e Super Admin.
  - Localização e Idioma — pt-BR como padrão, arquitetura i18n-ready
       (Vue i18n no frontend, arquivos `lang/<locale>` no backend).
  - Restrições Técnicas e Arquiteturais → bloco "Decisões de produto
       fechadas": cobrança híbrida (base por profissional ativo +
       cota mensal de mensagens IA com excedente), gateway Stripe.

Removed sections: nenhuma.

Templates requiring updates:
  - .specify/templates/plan-template.md             ✅ aligned
       (Constitution Check agora avalia 7 princípios; novos gates de
       conformidade Meta, segurança operacional e i18n surgem quando
       relevantes ao escopo da feature).
  - .specify/templates/spec-template.md             ✅ aligned
       (sem mudanças de seções obrigatórias; novos princípios surgem
       como FRs/NFRs específicos).
  - .specify/templates/tasks-template.md            ✅ aligned
       (E2E e compliance Meta entram como tasks cross-cutting na fase
       Polish ou na user story específica).
  - .specify/templates/checklist-template.md        ✅ aligned.

Follow-up TODOs: nenhum.

----------------------------------------------------------------------
PRIOR REPORTS
----------------------------------------------------------------------
v1.1.0 (2026-05-10) — MINOR: adicionada subseção "Arquitetura de
Aplicações e Camadas" (SPA Vue 3 para tenants, Filament 5 para
super-admin, pipeline Form Request → Controller → Service → Resource).

v1.0.0 (2026-05-10) — Initial ratification, baseline com 5 princípios
(LGPD, Isolamento Multi-Tenant, Segurança Clínica da IA, Spec-Driven
Test-First, Observabilidade) + Restrições Técnicas, Quality Gates e
Governance.
-->

# Paciente360 Constitution

## Core Principles

### I. Privacidade, Consentimento e Conformidade LGPD (NON-NEGOTIABLE)

A plataforma trata dados pessoais e sensíveis de pacientes em escala multi-tenant.
Toda funcionalidade MUST:

- Registrar consentimento explícito (data, canal, finalidade) antes de qualquer
  comunicação de marketing ou tratamento não estritamente operacional.
- Honrar revogação de consentimento e direito ao esquecimento via anonimização
  (não exclusão física quando registros financeiros/legais exigirem retenção)
  dentro do prazo legal de 15 dias úteis.
- Usar criptografia em trânsito (TLS 1.3) e em repouso para dados sensíveis;
  hash de senhas com argon2id (preferencial) ou bcrypt com cost ≥ 12.
- Pseudonimizar prompts enviados ao LLM: CPF, RG, número de carteirinha,
  telefone e demais identificadores diretos MUST ser substituídos por
  placeholders antes do envio ao provedor de IA.
- Manter log de auditoria de ações sensíveis (acesso a dados de paciente,
  alteração de agenda, exclusões, alterações de permissão) com retenção
  mínima de 1 ano.

**Rationale**: A operação fora desses limites configura risco regulatório
material (multa de até 2% do faturamento por infração LGPD), perda de
licença de operação de canais Meta e quebra de confiança da clínica
contratante. Cada um destes itens é verificável por inspeção de código,
testes automatizados ou auditoria de logs.

### II. Isolamento Multi-Tenant (NON-NEGOTIABLE)

Isolamento de dados por tenant é requisito de arquitetura, presente desde
a primeira PR de domínio — NUNCA tratado como retrofit. Toda consulta,
comando e job MUST ser escopado ao tenant correto. Especificamente:

- Modelos Eloquent que carregam dados de tenant MUST aplicar global scope
  por `tenant_id` (ou estratégia equivalente decidida em plano).
- Endpoints de API MUST resolver o tenant ativo a partir do contexto
  autenticado (Sanctum + middleware), nunca de parâmetro de rota livre.
- Jobs em fila (Horizon) MUST carregar e restaurar o contexto de tenant
  antes de qualquer leitura/escrita de dados.
- Broadcast (Reverb) MUST autorizar canais privados/presence verificando
  pertencimento ao tenant antes de aceitar inscrição.
- Caches, chaves de Redis e índices de busca MUST ser prefixados/segmentados
  por tenant.

Toda PR que toque persistência, fila ou broadcast MUST incluir teste de
integração que comprove a impossibilidade de leitura cruzada entre tenants.

**Rationale**: Vazamento entre tenants é falha de severidade catastrófica
em SaaS médico — atinge sigilo profissional e LGPD simultaneamente.
Detecção tardia é inviável: a defesa MUST estar no design.

### III. Segurança Clínica e Auditabilidade da IA (NON-NEGOTIABLE)

A camada de IA matricial opera dentro de limites estritos de escopo CRM:

- A IA MUST NOT emitir diagnóstico, prescrição, posologia, interpretação
  de exame ou qualquer orientação clínica. Tentativas detectadas MUST ser
  redirecionadas a agendamento e marcadas para revisão.
- Guardrails de prompt e testes automatizados MUST cobrir cenários de
  bypass (prompt injection, role-play, tradução para outras línguas).
- Toda decisão da IA MUST gerar registro contendo: prompt enviado,
  contexto pseudonimizado, intenção classificada, score de confiança,
  resposta gerada e ação executada (ex.: agendamento criado). Retenção
  mínima de 6 meses.
- Score de confiança abaixo do limiar configurado MUST escalar a conversa
  para atendente humano. Detecção de urgência médica MUST escalar
  imediatamente, com prioridade alta na inbox.
- Quando atendente humano assume a conversa, a IA MUST pausar
  automaticamente para evitar mensagens concorrentes.

**Rationale**: A clínica contratante carrega responsabilidade ética e
legal pelo que a plataforma diz em seu nome. Sem auditabilidade
verificável, qualquer reclamação ou processo torna-se indefensável.

### IV. Desenvolvimento Spec-Driven e Test-First

Todo trabalho de feature segue o ciclo Spec Kit (`/speckit-specify` →
`/speckit-clarify` → `/speckit-plan` → `/speckit-tasks` → `/speckit-implement`)
e o ciclo Red-Green-Refactor:

- Features novas MUST originar de uma spec aprovada em `specs/<###-feature>/`
  antes da implementação.
- Bugs e mudanças de comportamento observável MUST ter teste de regressão
  escrito e em falha antes do fix.
- Cobertura de testes do backend MUST ser ≥ 70% (PHPUnit feature tests
  predominantes; unit quando justificado).
- Testes E2E (Playwright ou Cypress) MUST cobrir as jornadas críticas:
  onboarding de clínica, agendamento via IA no chat, confirmação
  automática de consulta e renovação de receita. Falha de E2E nessas
  jornadas bloqueia o merge.
- Migrações aplicadas em produção MUST ser tratadas como imutáveis:
  correções e mudanças de schema entram via NOVA migration, nunca
  alterando a já aplicada. Migrations e seeders MUST ser idempotentes.
- Documentação OpenAPI (Scribe) MUST ser atualizada na mesma PR que
  altera contrato de API pública; PR sem essa atualização é rejeitada.
- Toda PR MUST passar `vendor/bin/sail artisan test` e `vendor/bin/sail bin pint`
  antes do merge.
- Tests existentes MUST NOT ser removidos sem aprovação explícita
  registrada na PR.

**Rationale**: Em uma plataforma multi-tenant com IA e canais externos
(WhatsApp/Instagram), regressões silenciosas são caras de detectar em
produção. Tests-first também forçam clareza de contrato antes do código.

### V. Observabilidade e Excelência Operacional

Cada caminho crítico MUST ser observável em produção:

- Logs estruturados (JSON, com `tenant_id`, `user_id`, `correlation_id`)
  para toda requisição HTTP, job de fila e decisão de IA.
- Toda ação de IA, todo envio externo (WhatsApp, Instagram, e-mail) e
  toda mudança de estado de paciente ou agendamento MUST gerar evento
  auditável persistido (tabela de eventos ou log estruturado dedicado),
  com identificação do ator (humano, IA, job) e do tenant.
- Métricas operacionais (tempo de resposta da API com target p95 < 300ms,
  tempo de resposta da IA com target ≤ 5s, taxa de erro, taxa de
  escalonamento, consumo mensal de mensagens IA por tenant) MUST ser
  expostas em endpoint Prometheus para ingestão por Grafana.
- Erros não-tratados MUST ser reportados ao Sentry com contexto de
  tenant, usuário e correlation_id suficientes para reprodução.
- Webhooks de WhatsApp/Instagram/Stripe MUST registrar payload bruto e
  resposta enviada, com retry exponencial em falha.
- SLA de uptime alvo: 99,5%. Backup diário com retenção de 30 dias e
  procedimento de disaster recovery testado pelo menos uma vez por
  semestre.

**Rationale**: Sem visibilidade ponta a ponta, problemas de IA, fila ou
canal externo se acumulam sem dono. Métricas e logs estruturados são a
base para SLA contratual e para o treinamento contínuo da IA exigido pelo
princípio III.

### VI. Conformidade Meta nos Disparos (NON-NEGOTIABLE)

A operação dos canais externos (WhatsApp Business Cloud API e Instagram
Direct via Graph API) está sujeita a regras de plataforma da Meta cuja
violação acarreta suspensão de conta. Conformidade é gate de envio:

- Envios fora da janela de 24h do WhatsApp MUST usar template aprovado
  pela Meta. O dispatcher MUST consultar status do template antes do
  disparo e bloquear o envio se o template não estiver aprovado.
- Disparos em massa (campanhas de reativação, sazonais, lembretes
  proativos) MUST validar opt-in de marketing registrado para cada
  destinatário. Sem opt-in válido, o destinatário é excluído do
  disparo automaticamente — não há fallback "best effort".
- Mensagens não transacionais MUST incluir comando/link de
  descadastro (ex.: "/sair" no canal) e o recebimento desse comando
  MUST revogar o opt-in de marketing imediatamente, registrando
  data, canal e finalidade revogados.
- O dispatcher MUST aplicar bloqueio em runtime se qualquer
  verificação de conformidade falhar (template não aprovado, opt-in
  ausente, fora de horário comercial configurado, janela de 24h
  expirada). Cada bloqueio MUST gerar evento auditável com motivo
  explícito.

**Rationale**: Suspensão de conta WhatsApp Business é evento
catastrófico para a clínica contratante (perda do canal principal de
relacionamento) e para a plataforma (perda de credibilidade junto a
todos os tenants). A defesa MUST estar no código, não em treinamento
operacional humano.

### VII. Segurança Operacional (NON-NEGOTIABLE)

Controles de segurança aplicados a toda a stack, complementando o
princípio I (que cobre os requisitos LGPD de proteção de dados):

- Hash de senhas: argon2id (preferencial) ou bcrypt com cost ≥ 12.
  Hashes existentes MUST ser recomputados no próximo login válido
  quando o algoritmo/custo configurado mudar.
- TLS 1.3 obrigatório em produção; certificados renovados
  automaticamente.
- Rate limiting MUST ser aplicado por tenant E por endpoint. Limites
  default são conservadores e overrides exigem justificativa em
  configuração versionada.
- 2FA via TOTP (RFC 6238): opcional para usuários internos em geral
  (Médico, Atendente, Recepcionista, Financeiro), OBRIGATÓRIO para
  perfis Admin Clínica e Super Admin. Nesses perfis, login sem 2FA
  habilitado MUST ser bloqueado após período de carência de 7 dias
  do primeiro acesso.
- Bloqueio temporário de login após 5 tentativas falhas consecutivas
  (já presente em US-2.1) MUST ser ativo em produção e cobrir
  qualquer endpoint de autenticação (web, API, painel Filament).

**Rationale**: O conjunto acima é o piso operacional para um SaaS
multi-tenant que carrega dados clínicos pseudonimizados, credenciais de
canais Meta e tokens de pagamento. Cada item é verificável por
inspeção de configuração ou teste automatizado.

## Restrições Técnicas e Arquiteturais

A stack é fixa para o MVP e mudanças exigem amendment desta constituição:

- **Backend**: Laravel 13 + PHP 8.5, executado via Laravel Sail
  (`vendor/bin/sail`). Sanctum para autenticação, Spatie Permissions para
  autorização, Horizon para filas, Reverb para WebSockets, Pail para
  tail de logs, Pint para formatação.
- **Frontend (tenant)**: Vue 3 (Composition API) + Pinia + Vue Router +
  Tailwind v4, entregue como SPA consumindo a API REST.
- **Painel super-admin**: Filament 5 (ver subseção
  "Arquitetura de Aplicações e Camadas" abaixo).
- **Persistência**: PostgreSQL ou MySQL para dados relacionais; Redis para
  cache, filas e presence.
- **IA**: Camada de orquestração matricial sobre LLM (provider
  configurável) com pseudonimização obrigatória (princípio I).
- **Canais externos**: WhatsApp Business Cloud API (Meta), Instagram
  Graph API, widget JS embutível.
- **Comandos de desenvolvimento**: SEMPRE prefixados com
  `vendor/bin/sail` (artisan, composer, npm, php). Comandos rodados fora
  do Sail são considerados fora de convenção e não devem ser usados em
  documentação ou scripts.
- **Estrutura de diretórios**: Manter a estrutura padrão Laravel; não
  criar novas pastas raiz sem aprovação.
- **Dependências**: Mudanças em `composer.json` ou `package.json`
  requerem aprovação explícita na PR.

**Decisões de produto fechadas para o MVP** (mudanças exigem amendment):

- **Cobrança**: modelo híbrido — plano base por profissional ativo +
  cota mensal de mensagens IA inclusas, com cobrança por mensagem
  excedente. Gateway: Stripe. Cobrança recorrente mensal; suspensão
  por inadimplência após 3 falhas de cobrança e 7 dias de carência.

Itens fora do escopo do MVP (não devem ser implementados sem amendment
do escopo): telemedicina nativa, multi-unidade por tenant, prontuário
eletrônico, pré-pagamento de consultas pelo paciente.

### Arquitetura de Aplicações e Camadas

A plataforma é dividida em **duas aplicações distintas** servidas pelo
mesmo backend Laravel:

1. **Aplicação do Tenant (clínicas)** — SPA Vue 3 que consome a **API REST**
   versionada (`/api/v1/...`). Toda funcionalidade utilizada por Admin
   Clínica, Médico, Atendente, Recepcionista e Financeiro MUST ser
   entregue por esse caminho. A SPA é a única superfície UI suportada
   para tenants; nenhuma tela Blade/Filament destinada a fluxos de
   tenant deve ser criada.
2. **Painel Super-Admin (plataforma)** — Filament 5, restrito ao perfil
   Super Admin. Seu escopo é exclusivamente: gestão de tenants
   (listagem, suspensão, reativação, impersonate auditado), planos
   globais, métricas globais, suporte e configurações da plataforma.
   Filament MUST NOT ser usado para fluxos de tenant (inbox, agenda,
   pacientes, receituários, IA, campanhas, billing do tenant etc.).

**Pipeline obrigatório da API** (cada endpoint Vue → Laravel):

`Form Request → Controller → Service → (Eloquent / Job / Integração) → Resource`

Cada camada tem responsabilidade única e não pode ser pulada:

- **Form Request** (`app/Http/Requests/...`): valida o payload, autoriza
  via policy/permission e normaliza input. Controllers MUST receber a
  Form Request type-hinted; nunca chamar `request()->validate()` no
  controller.
- **Controller** (`app/Http/Controllers/Api/...`): fino. Recebe a
  Request, delega ao Service, devolve a Resource. MUST NOT conter
  lógica de negócio, queries Eloquent diretas, nem manipulação de
  dados além do roteamento de chamadas.
- **Service** (`app/Services/...`): **única camada autorizada a conter
  regras de negócio**, orquestração de transações, despacho de jobs,
  chamada a integrações externas (WhatsApp, Instagram, LLM, Stripe) e
  emissão de eventos de domínio. Services MUST receber dependências via
  injeção de construtor (PHP 8 promotion) e ser cobertos por testes
  PHPUnit (feature ou unit conforme apropriado).
- **Resource** (`app/Http/Resources/...`): única forma de serializar
  resposta da API. MUST NOT conter regras de negócio nem queries
  adicionais — apenas formatação e seleção de campos a expor. Use
  Resource Collections para listagens.
- **Eloquent Model** (`app/Models/...`): representa estado, relações,
  scopes (incluindo o global scope de tenant — princípio II) e casts.
  Models MUST NOT conter regras de negócio que extrapolem a entidade
  (ex.: orquestração entre múltiplos modelos, integrações externas);
  isso pertence ao Service.

**Painel Filament** segue suas próprias convenções (Resources,
Pages, Widgets), mas qualquer ação que envolva regras de negócio
compartilhadas com a API (ex.: suspender tenant, alterar plano) MUST
delegar à mesma camada de Services usada pela API. Não duplicar lógica
entre Filament e Controllers.

**Verificação**: PRs que adicionem queries Eloquent, despacho de jobs
ou chamadas a integrações dentro de Controllers, Resources ou Models
MUST ser rejeitadas em code review e refatoradas para Service.

## Localização e Idioma

- **Idioma padrão**: pt-BR. Toda string voltada ao usuário (UI da SPA
  Vue, painel Filament, mensagens de erro de API, e-mails
  transacionais, mensagens de IA, templates do WhatsApp) MUST ser
  servida em pt-BR por padrão.
- **Arquitetura i18n-ready**: strings de UI MUST viver em arquivos de
  tradução — Vue I18n no frontend, arquivos `lang/<locale>/*.php` no
  backend. Strings hardcoded em componentes, controllers ou Services
  MUST ser rejeitadas em code review.
- **Formatação localizada**: datas, horários, moedas e números MUST
  usar formatação localizada (`Intl`/`dayjs` no frontend; `Carbon`
  com locale e `NumberFormatter` no backend). Não concatenar formatos
  literais.
- **Documentação técnica e specs internos**: PT-BR (alinhado com
  `docs/project-description.md` e `docs/user-stories.md`).
  Identificadores de código (classes, métodos, variáveis, rotas) e
  mensagens de log estruturado: inglês, conforme convenção Laravel.

## Fluxo de Desenvolvimento e Quality Gates

1. **Branch & Spec**: Toda feature começa com `/speckit-git-feature` e
   `/speckit-specify`. Branches seguem nomenclatura `###-feature-name`.
2. **Clarify & Plan**: Ambiguidades são resolvidas via `/speckit-clarify`
   antes de `/speckit-plan`. O plan MUST passar Constitution Check
   (verificação contra os 7 princípios) antes de Phase 0.
3. **Tasks**: `/speckit-tasks` gera lista por user story, ordenada por
   dependência, suportando entrega incremental por prioridade (P1 → P2 → P3).
4. **Implementação**: `/speckit-implement` executa tasks. Cada task
   MUST resultar em commit separado quando possível, respeitando hooks
   `before_*`/`after_*` do `.specify/extensions.yml`.
5. **Quality Gates antes do merge**:
   - `vendor/bin/sail bin pint --dirty --format agent` (formatação OK)
   - `vendor/bin/sail artisan test --compact` (todos os testes verdes)
   - Análise: Constitution Check revisado, cobertura ≥ 70%, sem
     regressão em testes pré-existentes.
   - Code review: ao menos um revisor humano confirma aderência aos
     princípios I, II, III, VI e VII quando o diff toca dados
     sensíveis, tenant boundary, IA, dispatcher de canais Meta ou
     superfície de autenticação/rate limiting.
6. **Documentação técnica**: Mudanças em API pública MUST atualizar
   contratos OpenAPI; mudanças em decisões da IA MUST atualizar a base
   de conhecimento e testes de guardrail.

## Governance

Esta constituição é a fonte da verdade para princípios, restrições e
quality gates do projeto. Em conflito com qualquer outro documento
(README, CLAUDE.md, AGENTS.md, comentários de código), a constituição
prevalece, exceto onde explicitamente delegar (ex.: convenções de código
delegadas a CLAUDE.md/AGENTS.md).

**Amendments**:

- Propostas de alteração MUST ser feitas via PR que toca este arquivo,
  com justificativa, impacto e plano de migração quando aplicável.
- Versionamento segue SemVer:
  - **MAJOR**: remoção ou redefinição incompatível de princípio,
    mudança em quality gate obrigatório.
  - **MINOR**: adição de princípio/seção, ou expansão material de
    guidance existente.
  - **PATCH**: clarificação, correção tipográfica, refinamento
    não-semântico.
- Toda amendment MUST atualizar `LAST_AMENDED_DATE` e produzir um
  novo Sync Impact Report (HTML comment no topo do arquivo) listando
  mudanças e templates afetados.

**Compliance Review**:

- PRs MUST passar Constitution Check listado em `plan.md`. Violações
  precisam ser justificadas em "Complexity Tracking" do plan ou refeitas.
- Revisões periódicas (trimestral) MUST verificar deriva entre
  constituição e código (ex.: novos endpoints sem auditoria, jobs sem
  contexto de tenant, decisões de IA sem log).
- Guidance operacional para agentes de IA (Claude Code, Cursor etc.)
  vive em `CLAUDE.md` e `AGENTS.md`; estes arquivos MUST ser mantidos
  consistentes com esta constituição.

**Version**: 1.2.0 | **Ratified**: 2026-05-10 | **Last Amended**: 2026-05-10
