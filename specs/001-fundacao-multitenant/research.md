# Phase 0 Research — Fase 0 Fundação Multi-tenant

**Feature**: `001-fundacao-multitenant`
**Spec**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)
**Date**: 2026-05-10

Este documento consolida as decisões técnicas necessárias antes do
desenho detalhado (Phase 1). Cada decisão usa o formato:
**Decisão · Rationale · Alternativas rejeitadas**.

---

## R1. Estratégia de isolamento multi-tenant

**Decisão**: **Single-database, multi-tenant por coluna `tenant_id` +
global scope no Eloquent**, complementado por prefixo `tenant:{id}:`
em chaves Redis e canal segmentado por tenant em broadcasts (Reverb).

**Rationale**:

- Já é o caminho explicitamente sugerido pelo Princípio II da
  constituição ("global scope por `tenant_id` ou estratégia
  equivalente decidida em plano").
- Decisão registrada em `spec.md` Session 2026-05-10 (Q1).
- Padrão dominante em SaaS Laravel para clínicas pequenas/médias
  (centenas a baixos milhares de tenants). Custo operacional mínimo:
  uma única base para migrar, fazer backup, monitorar e reindexar.
- A defesa de isolamento vem de **dois mecanismos combinados**:
  1. **Global scope** automático aplicado por uma trait
     `BelongsToTenant` em todos os Models de domínio. Toda query
     ganha `WHERE tenant_id = :current_tenant_id` sem o
     desenvolvedor precisar lembrar.
  2. **Testes de isolamento obrigatórios** cobrindo 100% dos
     endpoints autenticados (gate de merge — princípio II).
- O acoplamento ao serviço de autenticação é limpo: o middleware
  resolve o tenant pelo subdomínio do request e injeta no
  `app('tenant')` antes de qualquer Controller rodar.

**Alternativas rejeitadas**:

| Alternativa | Por que rejeitada |
|---|---|
| **Database-per-tenant** | Custo operacional alto: migrations rodam N vezes (uma por tenant); provisionamento de novo tenant exige criar/popular DB inteiro; backup/restore por tenant é nativo mas oneroso em escala. Vantagem (isolamento físico) é coberta por testes + scope no nosso caso. |
| **Schema-per-tenant (PostgreSQL)** | Trava o banco em PostgreSQL — embora seja nossa escolha hoje, perderíamos opcionalidade. Migrations precisam iterar por schema, complicando rollouts. Search path por tenant é frágil em conexões pooladas (PgBouncer). |
| **Híbrido (silver-bullet pools)** | Fornece isolamento físico para tenants premium e single-DB para os demais. Complexidade desnecessária para o MVP; pode ser introduzido em fase posterior se um tenant enterprise aparecer. |

**Implicações de design**:

- Toda Model de domínio MUST usar a trait `BelongsToTenant` que:
  - Adiciona `belongsTo(Tenant::class)` relationship.
  - Aplica `addGlobalScope(new TenantScope)` no `boot()`.
  - Auto-popula `tenant_id` em `creating` event a partir de
    `app('tenant')->id`.
- Tabelas de domínio carregam coluna `tenant_id BIGINT NOT NULL`
  com índice composto `(tenant_id, ...)` para todo lookup comum.
- Tabela `tenants` é a **única** sem `tenant_id`. Modelos cross-tenant
  (Plans globais, Roles globais quando aplicável, Super Admin Users)
  não usam a trait.
- Filament `/admin` (Super Admin) **desabilita** o global scope para
  permitir gestão cross-tenant; isso é uma exceção controlada via
  `Tenant::withoutTenantScope()` em queries do painel super-admin.

---

## R2. Estratégia de migração e rollout de schema

**Decisão**: **Migrations padrão Laravel rodando uma única vez na
base compartilhada**. Não há "migration por tenant" porque não há
schema por tenant. Toda mudança de schema afeta todos os tenants
simultaneamente.

**Rationale**:

- Decorre diretamente de R1: single-DB significa um único conjunto
  de migrations.
- A constituição (Princípio IV — Spec-Driven & Test-First) exige
  **migrações imutáveis após aplicadas em produção**: correções
  entram via NOVA migration, jamais editando a aplicada.
- Migrations com prefixo de domínio (ex.:
  `2026_01_01_000001_create_tenants_table.php`,
  `2026_01_01_000010_create_users_table.php`) facilitam leitura
  cronológica e auditoria.

**Estratégia de rollout em produção**:

1. Migrations sempre **idempotentes** (use `Schema::hasTable`,
   `hasColumn` quando seeders/data migrations agem em estado).
2. Migrations destrutivas (drop column/table) **proibidas**: marcar
   coluna como `deprecated`, parar de escrever, deletar em migration
   posterior após N releases.
3. Adições de coluna `NOT NULL` em tabela com dados → padrão de 3
   passos: (a) adicionar `NULLABLE` + default, (b) backfill em job
   ou comando, (c) tornar `NOT NULL` em migration posterior.
4. CI roda `migrate --pretend` em PRs que tocam migrations para
   capturar diffs visualmente.
5. Produção roda `php artisan migrate --force --step` no deploy.
6. Rollback (`migrate:rollback`) **proibido em produção**: corrige
   por nova migration. Em dev é aceitável.

**Seeders por ambiente**:

- `DatabaseSeeder` — só dados estritamente necessários em qualquer
  ambiente (planos comerciais default, papéis Spatie, Super Admin
  inicial via env vars).
- `DevSeeder` — dados de uso local: 2 tenants de exemplo (`clinica-alfa`,
  `clinica-beta` em `lvh.me`), usuários de cada perfil, plano de
  teste já contratado para um deles.
- `DemoSeeder` — dados ricos para demonstração comercial: pacientes,
  agendamentos, conversas mockadas (alimentado em fases futuras —
  esqueleto vazio nesta fase).

**Alternativas rejeitadas**:

| Alternativa | Por que rejeitada |
|---|---|
| Migrations por tenant via comando custom | Não se aplica a single-DB. |
| Flyway / outro migrador externo | Laravel migrations são adequadas e familiares ao time; sair do ecossistema sem ganho concreto. |
| Schemas versionados via "blue-green DB" | Overkill para o estágio do projeto; reintroduzir se complexidade aumentar. |

---

## R3. Cashier + Stripe: webhook handling, proration, idempotência

**Decisão**: usar `laravel/cashier-stripe` como base; estender via
**`StripeWebhookController` próprio** que herda de
`Cashier\Http\Controllers\WebhookController` para tratar eventos
adicionais (`invoice.payment_failed`, `customer.subscription.updated`,
`charge.dispute.created`); proration habilitada por default para
mid-cycle changes; idempotência garantida por **registrar o
`stripe_event_id` em uma tabela `stripe_events` antes de processar**.

**Rationale**:

- Cashier já cobre o caminho feliz: criação de Customer, Subscription,
  webhooks `customer.*`, `invoice.*`, retenção de invoice records.
- O modelo híbrido (base por profissional + cota IA + excedente) NÃO
  é nativo do Cashier — vamos modelar como:
  - 1 **Subscription Item "base"** com price recorrente e `quantity`
    igual ao número de profissionais ativos. Mudanças de quantidade
    disparam proration via `subscription->updateQuantity()`.
  - 1 **Subscription Item "ai-overage"** com price metered (Stripe
    Metered Billing). Cada mensagem IA consumida acima da cota
    inclusa registra um `usage_record` no Stripe via job no fim do
    ciclo (ou sob demanda via cron — a decidir em fase 5 quando IA
    entrar; nesta fase só preparamos a estrutura).
- Idempotência: tabela `stripe_events (id PK = stripe_event_id,
  type, payload, processed_at)`. Antes de processar qualquer webhook,
  o controller faz `INSERT ... ON CONFLICT DO NOTHING` e rejeita
  reprocessamento. Stripe re-tenta com mesmo `event.id` → segundo
  processamento é no-op.
- Proration: Cashier aplica automaticamente em `swap()` e
  `updateQuantity()` quando passamos `proration_behavior =>
  'create_prorations'`. Documentar em ADR.

**Eventos de webhook monitorados nesta fase**:

| Evento Stripe | Ação no nosso lado |
|---|---|
| `customer.subscription.created` | Cashier persiste; emitimos `SubscriptionStartedAuditable`. |
| `customer.subscription.updated` | Atualiza estado interno; recalcula limites de plano (n professionals). |
| `customer.subscription.deleted` | Marca tenant como `cancelled` (não deleta). |
| `invoice.payment_succeeded` | Reseta contador de falhas; `MarkTenantActive` job. |
| `invoice.payment_failed` | Incrementa contador; aos 3 falhos → estado `inadimplente` + agenda `ApplyOverdueRestrictionsJob` para D+7. |
| `charge.dispute.created` | Notifica financeiro; nenhuma ação automática nesta fase. |

**Segurança**:

- Webhook secret em `services.stripe.webhook_secret`, validação via
  `Cashier::secret()` middleware (já oferecido pelo pacote).
- Endpoint `/stripe/webhook` fica fora do middleware de tenant
  resolution (não há subdomínio em webhooks).

**Alternativas rejeitadas**:

| Alternativa | Por que rejeitada |
|---|---|
| Integração custom com Stripe SDK direto | Reinventar Cashier; perder o ecossistema (Eloquent traits Billable, etc.). |
| Mercado Pago como gateway | Decisão fechada na constituição: Stripe (Restrições Técnicas → Decisões de produto fechadas). |
| Cobrança apenas por flat-rate sem cota IA | Conflita com o modelo híbrido decidido na constituição. |

---

## R4. Auditoria: `owen-it/laravel-auditing` vs. listener custom

**Decisão**: **Listener custom + AuditLog model próprio** com trait
opcional `Auditable` para reduzir boilerplate em ações Eloquent
comuns. Não usar `owen-it/laravel-auditing` nesta fase.

**Rationale**:

- A constituição (Princípio V — Observabilidade) exige eventos
  auditáveis para **três classes de coisas**:
  1. Mudanças de estado de modelos (CRUD em paciente, agendamento etc.).
  2. **Envios externos** (WhatsApp, Instagram, e-mail, Stripe).
  3. **Decisões da IA** (prompt, contexto, intenção, score, resposta,
     ação executada — princípio III).
- `owen-it/laravel-auditing` é excelente para (1) mas não cobre (2)
  e (3) sem extensão substancial. Usar dois sistemas de auditoria
  fragmenta o histórico e duplica armazenamento.
- O Princípio I (LGPD) também impõe auditoria de acessos a dados
  sensíveis e tentativas de login — eventos não-Eloquent que o
  custom path captura naturalmente.
- A retenção em três tiers (hot 2y / cold 5y / delete) decidida em
  Q3 da clarificação requer controle direto sobre a tabela de
  storage, o que é trivial em modelo próprio mas exige patches no
  pacote.

**Arquitetura proposta**:

- **Modelo único `AuditLog`** com schema por FR-035 (tenant, ator,
  ação, alvo, payload JSON, IP, user-agent, timestamp).
- **Eventos de domínio** (PHP `Event`/`Listener`) emitidos pelos
  Services. Cada Service que faz mudança auditável dispara um evento
  específico (`TenantRegistered`, `UserInvited`, `LoginFailed`,
  `PlanUpgraded`, `HardCapConfigured` etc.). Um listener único
  (`PersistAuditLog`) escuta todos os eventos que implementam a
  interface `Auditable` e grava na tabela.
- **Trait helper `RecordsActivity`** para Models que precisam logar
  `created/updated/deleted` automaticamente: adiciona `boot` hooks
  que disparam o evento correto. Evita repetir código em Services
  para CRUD trivial.
- **Job assíncrono `WriteAuditLogJob`** opcional para eventos de alto
  volume — nesta fase só usado em login (que pode ter rajadas em
  ataque de brute force). Demais eventos gravam síncrono.
- **Lifecycle de retenção**:
  - Job mensal `ArchiveAuditLogsJob` move registros com mais de 2
    anos para `audit_logs_cold` (mesma tabela em namespace lógico
    diferente, ou storage S3 Glacier — decisão fina vai em
    fase posterior; para o MVP a tabela `audit_logs_cold` no
    PostgreSQL já atende, com índice mais leve).
  - Job mensal `DeleteExpiredAuditLogsJob` deleta de
    `audit_logs_cold` registros com mais de 5 anos.

**Alternativas rejeitadas**:

| Alternativa | Por que rejeitada |
|---|---|
| `owen-it/laravel-auditing` puro | Cobre só CRUD Eloquent; precisaríamos de um segundo sistema para ações não-Eloquent → fragmentação. |
| `spatie/laravel-activitylog` | Mesma limitação que o anterior, ainda mais focado em CRUD/log de modificações. |
| Solução baseada em logs estruturados (sem tabela) | Princípio I exige log **consultável e exportável** pelo Admin Clínica via painel, com filtros. Tabela é o caminho natural. |
| Stream para Loki/CloudWatch direto | Para auditoria de longo prazo a busca seria custosa; manter em PostgreSQL no tier hot atende o painel. Stream para observabilidade operacional segue separado (Princípio V — Prometheus para métricas, fora desta fase). |

---

## R5. Autenticação (Sanctum SPA)

**Decisão**: Sanctum SPA mode para o frontend Vue (cookie de sessão
HttpOnly, CSRF token); Sanctum personal access tokens reservados
para casos pontuais (CLI, integrações). **Sem 2FA no MVP** —
removido na constituição v1.3.0; pode ser reintroduzido como opt-in
voluntário em fase futura sem quebrar contratos.

**Rationale**:

- A SPA Vue 3 está no mesmo domínio raiz (`api.crm.com.br` +
  `<slug>.crm.com.br`); Sanctum SPA com `SANCTUM_STATEFUL_DOMAINS`
  cobre isso e dispensa armazenar token no localStorage (CSRF +
  HttpOnly cookie é o padrão seguro).
- Login flow nesta fase:
  1. `GET /sanctum/csrf-cookie` → SPA recebe cookie CSRF.
  2. POST `/api/v1/auth/login` (e-mail + senha + tenant resolvido
     via subdomínio) → estabelece sessão e responde `user`.
  3. Erros: 401 genérico (não revela existência); 423 quando
     `users.locked_until > now()` (5 falhas em janela); 429 quando
     rate limiter dispara.
- Postura de defesa em profundidade no MVP, mesmo sem 2FA:
  argon2id + TLS 1.3 + rate limiting por tenant+endpoint + brute
  force lock (5 falhas) + auditoria de `login.succeeded`/`failed`
  no painel de Admin Clínica (princípio I + V).
- Personal access tokens (Sanctum) seguem disponíveis para
  ferramentas internas (CLI Super Admin, CI test runner) e
  integrações futuras com terceiros.

**Alternativas rejeitadas**:

| Alternativa | Por que rejeitada |
|---|---|
| JWT com tokens em localStorage | Vulnerável a XSS; Sanctum SPA é o padrão Laravel para esta arquitetura. |
| Laravel Fortify | Inflexível para os gates customizados (rate limit por tenant+endpoint, mensagem genérica de 401, lock por brute force específico). |
| Manter 2FA no MVP | A constituição v1.3.0 retirou explicitamente o gate. Reintrodução depende de novo amendment. |
| WebAuthn/Passkeys | Excelente UX mas adiados para fase pós-MVP de segurança. |
| SMS OTP | Custo recorrente, vetor SIM-swap, fora do plano. |

---

## R6. UI shell — SPA Vue 3 vs. componentes do Filament

**Decisão**: Toda UI **operacional do tenant** (login, onboarding,
billing, gestão de usuários, painel de cota IA, log de auditoria,
recuperação de senha) é entregue na **SPA Vue 3**. O Filament `/panel`
NÃO é usado para tenant nesta fase — a constituição (Princípio
arquitetural — subseção "Arquitetura de Aplicações e Camadas") proíbe
explicitamente Filament em fluxos de tenant. O Filament `/admin` é
usado **exclusivamente** para o Super Admin (gestão de tenants e
planos globais).

**Rationale**:

- Constituição v1.1.0+ é explícita: "A SPA é a única superfície UI
  suportada para tenants; nenhuma tela Blade/Filament destinada a
  fluxos de tenant deve ser criada."
- O design de referência (`docs/design/01_Login.png`) é claramente
  uma SPA com layout duas colunas, presença de marketing à direita,
  KPIs em tempo real — não é o estilo Filament.
- Filament aqui serve o Super Admin **apenas**: CRUD de Tenants
  (listar, suspender, reativar), CRUD de Plans (catálogo global),
  visualização de métricas globais.

**Reuso de Services entre Filament e API**: ambos chamam a mesma
camada `app/Services/...` (princípio arquitetural). Filament Resources
para Tenants/Plans NÃO contêm lógica de negócio — delegam aos
mesmos `TenantService`, `PlanService` que a API usa.

**Rotas**:

- `/api/v1/...` — API REST consumida pela SPA Vue do tenant
  (subdomínio `<slug>.crm.com.br`).
- `/panel/...` — **reservado** para a SPA Vue, mas o "shell" é
  servido via Blade vazio + Vite (apenas `<div id="app"></div>`). O
  roteamento real fica no Vue Router. Em produção é cacheável.
- `/admin/...` — Filament Super Admin, no domínio principal sem
  subdomínio (`admin.crm.com.br` ou `crm.com.br/admin`).
- `/stripe/webhook` — endpoint público fora do middleware de tenant.

---

## R7. Reverb (preparação)

**Decisão**: instalar e configurar Reverb nesta fase; **não emitir
nenhum evento broadcast nesta fase**. Apenas:

- Registrar `BroadcastServiceProvider` com canais autenticados via
  `tenant.{tenant_id}` e `tenant.{tenant_id}.user.{user_id}`.
- Configurar `routes/channels.php` com autorização verificando
  pertencimento ao tenant (Princípio II).
- Docker Compose com serviço `reverb` rodando.
- WSS proxy configurado em Nginx (mesmo cert Let's Encrypt do app).

**Rationale**: a fase 2 (Inbox) consome Reverb pesadamente; deixar
infra pronta evita overhead de configuração depois. Sem eventos
nesta fase, não há risco de mensagens despublicadas afetarem testes.

---

## R8. Observabilidade nesta fase

**Decisão**: para a Fase 0, observabilidade é entregue parcialmente:

- ✅ **Sentry**: integrado (laravel/sentry) para captura de exceções
  com contexto de tenant.
- ✅ **Telescope**: habilitado em dev e staging; **desabilitado em
  produção** (proteção via `gate` apenas para Super Admin se
  precisar inspeção pontual).
- ✅ **Logs estruturados**: middleware
  `LogStructuredRequestData` adiciona `tenant_id`, `user_id` e
  `correlation_id` (X-Request-Id ou gerado) a todo log via Monolog
  processor.
- ⚠️ **Prometheus/Grafana**: **adiado** para a fase 2 (Inbox), quando
  métricas ficam materiais (consumo IA por tenant, latência por
  canal). Justificativa registrada na seção "Verificação
  Constitucional" do plan.md.
- ✅ **Eventos auditáveis** para mudanças de estado e ações sensíveis
  desta fase: cobertos por R4.

**Rationale**: o Princípio V exige que Prometheus/Grafana exponha
métricas operacionais, mas as métricas materiais ainda não existem
nesta fase (consumo IA = 0 sem IA implementada). Configurar a infra
sem dado para alimentar ela seria over-engineering. Compromisso:
nenhum endpoint da API nesta fase fica sem instrumentação que
possa ser **convertida** em métrica no futuro (todos os logs já
têm `tenant_id`, `correlation_id`, latência).

---

## R9. Internacionalização (i18n)

**Decisão**: Vue I18n no frontend; arquivos `lang/<locale>/*.php`
no backend; `pt-BR` como locale default e único nesta fase, mas
toda string em arquivo de tradução (zero hardcode).

**Rationale**:

- Princípio "Localização e Idioma" da constituição: "strings
  hardcoded em componentes, controllers ou Services MUST ser
  rejeitadas em code review".
- Estrutura preparada para adicionar `en` ou `es` em fase futura
  sem refactor.

**Convenções**:

- Backend: chaves em snake_case agrupadas por domínio
  (`auth.login_failed`, `billing.subscription_active`).
- Frontend: idem, com namespace por feature (`auth.login.title`).
- Datas/números: backend usa `Carbon::setLocale('pt_BR')` global +
  `NumberFormatter`; frontend usa `Intl.DateTimeFormat`/
  `Intl.NumberFormat` via composable `useI18nFormat()`.

---

## R10. Segurança operacional (rate limiting, hashing, TLS)

**Decisão**:

- **Hashing**: argon2id como driver default (`config/hashing.php`),
  com `time_cost: 4`, `memory_cost: 65536` (64 MiB), `threads: 1`.
  Bcrypt cost ≥ 12 como fallback se infra não suportar argon2id.
- **TLS 1.3**: terminado em Nginx em produção, com cert Let's
  Encrypt renovado via certbot. HTTP/1.1 retornando 426 para
  forçar HTTPS via HSTS.
- **Rate limiting**: per-tenant + per-endpoint via
  `RateLimiter::for('api')` em `RouteServiceProvider`. Limites
  default:
  - Login: 5 tentativas/min/IP/tenant.
  - Cadastro de tenant: 3 tentativas/hora/IP.
  - Recuperação de senha: 3 tentativas/hora/e-mail.
  - API geral autenticada: 60 req/min por usuário.
  - Webhooks Stripe: sem limite (Stripe controla).

**Rationale**: Princípio VII (Segurança Operacional) é
NON-NEGOTIABLE. Cada item tem teste automatizado (PHPUnit feature)
verificando o comportamento.

---

## Resumo das resoluções

| ID  | Tópico | Status |
|-----|--------|--------|
| R1  | Multi-tenancy: single-DB + tenant_id | ✅ Resolvido |
| R2  | Migrations & rollout | ✅ Resolvido |
| R3  | Cashier + Stripe (webhook, proration, idempotência) | ✅ Resolvido |
| R4  | Auditoria: listener custom + AuditLog próprio | ✅ Resolvido |
| R5  | Sanctum SPA (sem 2FA — removido em v1.3.0) | ✅ Resolvido |
| R6  | SPA Vue para tenant; Filament só para super-admin | ✅ Resolvido |
| R7  | Reverb instalado, sem eventos nesta fase | ✅ Resolvido |
| R8  | Observabilidade parcial (Prometheus adiado) | ⚠️ Parcial — justificado em plan.md |
| R9  | i18n pt-BR padrão, ready para outros locales | ✅ Resolvido |
| R10 | Segurança operacional: argon2id, TLS 1.3, rate limit | ✅ Resolvido |

Nenhum item permanece em status "NEEDS CLARIFICATION". Pronto para
Phase 1 (data-model, contracts, quickstart).
