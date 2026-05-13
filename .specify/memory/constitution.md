<!--
SYNC IMPACT REPORT
==================
Version change: 1.3.0 → 1.4.0
Bump rationale (escolha do owner): MINOR — expansão material do
Princípio VII (Segurança Operacional). Aceita formato de
autenticação adicional (Bearer Sanctum Personal Access Tokens) para
a API tenant, sem reduzir nenhum gate existente. Filament super
admin permanece em cookie session (guard `web` próprio). Adiciona 5
novos gates específicos do flow Bearer (token hash SHA-256 DB, CORS
env-driven, CSP estrita em prod, DOMPurify em HTML user-provided,
audit log de uso suspeito de token).

Justificativa do trade-off de produto:
  - Fase 3 (Omnichannel Inbox) revelou tensões com cookie SPA stateful
    em deploys cross-domain (CORS, same-site, broadcasting auth) e
    bloqueava ngrok/load balancer setups sem trustProxies. Mobile e
    integrações third-party precisariam de fluxo paralelo, criando
    duplicação.
  - Bearer tokens Sanctum (já instalados, tabela personal_access_tokens
    existente) viabilizam deploy decoupled (api.crm.com.br +
    app.crm.com.br via CDN) sem retrabalho de auth, abrem caminho
    para mobile e Postman/integradores.
  - Trade-off de XSS (localStorage vulnerável) é mitigado por gates
    obrigatórios (CSP + DOMPurify + ESLint + audit suspeito +
    expiração 30d). R1 do spec 004 detalhado.
  - Filament admin isolado em cookie no domínio crm.com.br
    (sem cruzamento com app.crm.com.br) preserva o paradigma
    server-rendered onde ele funciona melhor.

Modified principles:
  - VII. Segurança Operacional (NON-NEGOTIABLE) — EXPANDIDO. Os 4
        bullets v1.3.0 permanecem inalterados (argon2id, TLS 1.3,
        rate limiting, brute force lock). Adicionados:
        - Aceite de Bearer Sanctum Personal Access Tokens como
          formato de autenticação para API tenant
        - Filament super admin permanece com guard `web` (cookie
          session) por design isolado
        - Tokens armazenados via SHA-256 hash no DB
        - CORS configurável via CORS_ALLOWED_ORIGINS env, audit em PR
        - CSP estrita em produção (sem unsafe-inline/eval; nonce-based)
        - DOMPurify obrigatório em HTML user-provided
        - Audit log de uso suspeito de token (mesmo token, IPs/UAs
          distintos em janela <5min)

Added sections: nenhuma seção nova; expansão dentro de VII.

Removed sections: nenhuma.

Templates requiring updates:
  - .specify/templates/plan-template.md             ✅ aligned
  - .specify/templates/spec-template.md             ✅ aligned
  - .specify/templates/tasks-template.md            ✅ aligned
  - .specify/templates/checklist-template.md        ✅ aligned

Artefatos da feature 004 (referenciam este amendment como pré-requisito):
  ✓ specs/004-token-auth-migration/plan.md          (Constitution Check
       já marca Princípio VII como CONDICIONADO a v1.4.0)
  ✓ specs/004-token-auth-migration/spec.md          (Definição de
       Pronto lista amendment como gate)
  ✓ specs/004-token-auth-migration/quickstart.md    (Seção 1 documenta
       amendment como pré-requisito bloqueante)

Follow-up TODOs: nenhum bloqueante. /speckit.tasks da Fase 004 pode
prosseguir; Lote A T001 = aplicar este amendment (já cumprido por
este commit).

----------------------------------------------------------------------
PRIOR REPORTS
----------------------------------------------------------------------
v1.3.0 (2026-05-10) — MINOR (com leitura alternativa MAJOR registrada):
diluição do bullet "2FA via TOTP (RFC 6238)" do Princípio VII. Os
4 bullets restantes (argon2id, TLS 1.3, rate limiting, brute force
lock) preservados. 2FA pode reentrar como opt-in voluntário em
fase futura sem amendment.

==================
HISTORICAL: v1.2.0 → v1.3.0 RATIONALE (PRESERVED FOR AUDIT)
==================
Bump rationale (escolha do owner): MINOR — diluição de um requisito
específico (2FA TOTP) dentro do Princípio VII (Segurança Operacional),
mantendo o princípio em pé com 4 dos 5 bullets originais (argon2id,
TLS 1.3, rate limiting, brute force lock). O princípio NON-NEGOTIABLE
não é removido nem redefinido — apenas tem seu escopo reduzido.

⚠️ Leitura alternativa registrada para auditoria: pela letra da seção
"Governance > Amendments > MAJOR" desta constituição ("mudança em
quality gate obrigatório"), a remoção do gate de 2FA poderia ser
classificada como MAJOR, já que a obrigatoriedade de 2FA para Admin
Clínica e Super Admin era um quality gate explícito. O owner do
projeto optou por MINOR com a justificativa de que o princípio
permanece materialmente em vigor; este report preserva ambas as
leituras para que futuros revisores entendam a intenção.

Justificativa do trade-off de produto:
  - O conjunto restante (argon2id + TLS 1.3 + rate limiting por
    tenant+endpoint + bloqueio por brute force) entrega um piso
    operacional defensável para o MVP.
  - 2FA pode ser reintroduzido em fase futura como **opt-in
    voluntário** sem quebrar contratos (a infra de Sanctum + sessão
    + audit log já cobre o flow).
  - A auditoria abrangente do Princípio I (LGPD) cobre a parte
    forense em caso de comprometimento de credencial.
  - UX de login simplificada para o MVP reduz fricção de adoção em
    consultórios pequenos (público-alvo principal).

Modified principles:
  - VII. Segurança Operacional (NON-NEGOTIABLE) — REMOVIDO o bullet
        "2FA via TOTP (RFC 6238)" e o sub-requisito "obrigatório
        para Admin Clínica e Super Admin com janela de carência de 7
        dias". Os 4 bullets restantes seguem inalterados (hash
        argon2id/bcrypt cost ≥ 12, TLS 1.3 em produção, rate limiting
        por tenant+endpoint, bloqueio temporário após 5 tentativas
        falhas). Rationale do princípio reescrito para refletir o
        escopo reduzido.

Added sections: nenhuma.

Removed sections: bullet "2FA via TOTP" do Princípio VII.

Templates requiring updates:
  - .specify/templates/plan-template.md             ✅ aligned
       (Constitution Check segue avaliando 7 princípios; gate de 2FA
       deixa de ser exigência obrigatória).
  - .specify/templates/spec-template.md             ✅ aligned.
  - .specify/templates/tasks-template.md            ✅ aligned.
  - .specify/templates/checklist-template.md        ✅ aligned.

Artefatos da feature ativa que requerem cascade (NÃO atualizados por
este comando — o owner deve abrir PR de cleanup):
  ⚠ specs/001-fundacao-multitenant/spec.md
       — remover FR-022, cenários 2 e 3 da US-2.1, SC-007, edge case
       TOTP, premissa de TOTP e item de DoD relativo a 2FA.
  ⚠ specs/001-fundacao-multitenant/plan.md
       — remover deps `pragmarx/google2fa`, `bacon/bacon-qr-code` da
       Stack; remover `TwoFactorController.php`, `TwoFactorService.php`,
       `EnforceTwoFactorEnrollment.php`, `TwoFactorPage.vue`,
       `TwoFactorTest.php` da Project Structure; revisar Constitution
       Check e Verificação Constitucional do Princípio VII.
  ⚠ specs/001-fundacao-multitenant/research.md
       — editar R5 (remover blocos de TOTP + recovery codes; manter
       Sanctum SPA como base).
  ⚠ specs/001-fundacao-multitenant/data-model.md
       — remover colunas `two_factor_secret`,
       `two_factor_recovery_codes`, `two_factor_confirmed_at`,
       `must_enroll_two_factor_after` da tabela `users` (§ 4).
  ⚠ specs/001-fundacao-multitenant/contracts/openapi.yaml
       — remover paths `/auth/two-factor/{enroll,confirm,verify,disable}`;
       remover schemas `TwoFactorVerifyRequest`, `TwoFactorEnrollResponse`,
       `TwoFactorConfirmRequest`, `TwoFactorConfirmResponse`,
       `TwoFactorDisableRequest`; simplificar `LoginResponse`
       (remover `requires_two_factor`, `must_enroll_two_factor`,
       `pending_token`); remover campo `two_factor_enabled` do
       `UserResource`.
  ⚠ specs/001-fundacao-multitenant/tasks.md
       — remover T102, T106, T313 inteiras; remover middleware
       `EnforceTwoFactorEnrollment` (T104); ajustar T103 (sem branch
       2FA), T105 (sem `TwoFactorService`), T107 (sem `TwoFactorPage`).

Follow-up TODOs: nenhum bloqueante. Recomenda-se rerun de
`/speckit-analyze` após a cascata estar aplicada para confirmar
zero CRITICAL.

----------------------------------------------------------------------
PRIOR REPORTS
----------------------------------------------------------------------
v1.2.0 (2026-05-10) — MINOR: adicionados Princípio VI (Conformidade
Meta), Princípio VII (Segurança Operacional), seção "Localização e
Idioma" e bloco "Decisões de produto fechadas" (cobrança híbrida
Stripe). Refinamentos a I/II/IV/V.

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

**Gates de senha e transporte** (inalterados desde v1.2.0):

- Hash de senhas: argon2id (preferencial) ou bcrypt com cost ≥ 12.
  Hashes existentes MUST ser recomputados no próximo login válido
  quando o algoritmo/custo configurado mudar.
- TLS 1.3 obrigatório em produção; certificados renovados
  automaticamente.
- Rate limiting MUST ser aplicado por tenant E por endpoint. Limites
  default são conservadores e overrides exigem justificativa em
  configuração versionada.
- Bloqueio temporário de login após 5 tentativas falhas consecutivas
  (já presente em US-2.1) MUST ser ativo em produção e cobrir
  qualquer endpoint de autenticação (API tenant, painel Filament,
  webhooks autenticados).

**Formatos de autenticação aceitos** (expandido em v1.4.0):

- **API tenant** (Vue SPA + mobile + integrações externas) MUST
  autenticar via **Sanctum Personal Access Tokens (Bearer)**.
  Tokens emitidos no login com expiração configurável (default 30d
  com sliding expiration). Header `Authorization: Bearer <token>`
  obrigatório em endpoints autenticados.
- **Filament super admin** MUST permanecer com **session cookie**
  (guard `web` próprio) por design — server-rendered Blade. Cookies
  do Filament em `crm.com.br` MUST ser isolados de qualquer
  subdomínio de tenant (`app.crm.com.br` não compartilha session).
- Webhooks providers (Twilio, Meta, Widget público) MUST validar via
  HMAC signature — não cookie nem Bearer.

**Gates específicos de tokens Bearer** (novos em v1.4.0):

- Tokens MUST ser armazenados via **SHA-256 hash** no banco
  (Sanctum default). Plain text token retornado ao cliente APENAS
  no momento da emissão (login response); nunca recuperável depois.
- Multi-tenant cross-check obrigatório: middleware MUST validar que
  `user(token).tenant_id === tenant(X-Tenant-Slug header).id` em
  TODA request autenticada da API tenant. Mismatch → 403. Mitigação
  anti-token-roubo cross-tenant.
- **CORS** MUST ser configurável via env `CORS_ALLOWED_ORIGINS` com
  audit em PR. Mudanças de origin whitelist passam por code review.
  `supports_credentials: false` para API tenant (Bearer-only, sem
  cookies cross-origin).
- **CSP (Content-Security-Policy)** estrita obrigatória em produção:
  sem `unsafe-inline` ou `unsafe-eval` em `script-src`/`style-src`;
  nonce-based para scripts inline legítimos quando necessário. CSP
  relaxada permitida apenas em ambientes `local`/`testing` para
  acomodar dev tooling (Vite HMR).
- **DOMPurify** (ou equivalente verificado) obrigatório em todo HTML
  user-provided antes de render (`v-html` em Vue, `dangerouslySetInnerHTML`
  em React etc.). ESLint plugin `no-unsanitized` MUST estar ativo no CI
  para bloquear DOM sinks diretos sem sanitização.
- **Audit log de uso suspeito de token**: middleware MUST detectar
  mesmo token apresentado de IPs OU User-Agents distintos em janela
  ≤ 5 minutos (via cache Redis) e emitir evento `Auditable`
  `TokenUsoSuspeito`. Side effect: alerta Sentry com prioridade
  `error` + notificação in-app ao admin do tenant. Token NÃO é
  auto-revogado (false positive risk com NAT/CGNAT/VPN); revogação
  fica como ação humana via gestão de sessões.
- **Logout scope**: `POST /auth/logout` MUST revogar APENAS o token
  do `Authorization` header da request (não todos os tokens do user).
  Revogação de todos os tokens fica como ação explícita via
  `POST /auth/logout-all`.

**Nota sobre 2FA** (decisão de escopo, v1.3.0 preservada em v1.4.0):
a obrigatoriedade de 2FA TOTP permanece removida do MVP. 2FA pode ser
reintroduzido como **opt-in voluntário** em fase futura sem violar
este princípio nem quebrar contratos existentes (a infra Sanctum
tokens torna isso ainda mais simples — basta marcar tokens com
ability `2fa-verified`).

**Rationale**: O conjunto acima é o piso operacional para um SaaS
multi-tenant que carrega dados clínicos pseudonimizados, credenciais de
canais Meta e tokens de pagamento. A migração para Bearer (v1.4.0)
desacopla deploy api/SPA sem reduzir nenhum gate existente; ao
contrário, adiciona 5 novos gates específicos do novo formato que
preservam defesa em profundidade. Filament permanece em cookie por
design — server-rendered admin não ganha nada com Bearer e seria
fricção desnecessária para os ~5 super admins.

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

**Version**: 1.4.0 | **Ratified**: 2026-05-10 | **Last Amended**: 2026-05-12
