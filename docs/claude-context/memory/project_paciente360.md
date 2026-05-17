---
name: Paciente360 — escopo e stack
description: Visão geral do projeto Paciente360 (CRM Médico SaaS multi-tenant) — escopo MVP, stack obrigatória e decisões de produto já fechadas.
type: project
originSessionId: 4a060098-b474-4267-8848-9ed5e241d075
---
Paciente360 é uma plataforma SaaS multi-tenant de CRM médico com atendimento
omnichannel (WhatsApp Cloud API, Instagram Direct via Graph API, widget de
chat web embutível) e camada de IA matricial para qualificação de leads,
agendamento, retornos e renovação de receituários.

**Why:** O projeto opera sob LGPD (dados sensíveis de pacientes) e sob
restrições da Meta (templates aprovados, janela de 24h do WhatsApp). Cada
decisão técnica precisa equilibrar conformidade regulatória, isolamento
multi-tenant e custo variável de LLM/WhatsApp.

**How to apply:** Ao planejar features, sempre considerar (1) escopo do
tenant ativo em todo acesso a dados, (2) pseudonimização antes de chamar
LLM, (3) guardrails clínicos (a IA NUNCA dá diagnóstico/prescrição) e
(4) auditoria completa de decisões da IA com retenção mínima de 6 meses.

**Stack obrigatória (MVP, fixada na constituição v1.2.0):**

- Backend: Laravel 13 + PHP 8.5 via Laravel Sail; Sanctum, Spatie
  Permissions, Horizon (filas), Reverb (WebSockets), Pail, Pint.
- Frontend (tenants): Vue 3 SPA (Composition API) + Pinia + Vue Router +
  Tailwind v4, consumindo API REST versionada (`/api/v1/...`).
- Painel super-admin: Filament 5, **exclusivamente** para gestão de
  tenants/planos/métricas globais. Filament NÃO deve ser usado para
  fluxos de tenant (inbox, agenda, pacientes, IA, etc.).
- Persistência: PostgreSQL/MySQL + Redis.
- Comandos: SEMPRE prefixados com `vendor/bin/sail`.

**Arquitetura em camadas (obrigatória para todo endpoint da API):**

`Form Request → Controller → Service → (Eloquent/Job/Integração) → Resource`

- **Regras de negócio MORAM em `app/Services/...`** — única camada
  autorizada a orquestrar lógica, despachar jobs e chamar integrações
  externas (WhatsApp, Instagram, LLM, Stripe).
- Controllers ficam finos: recebem Form Request, delegam ao Service,
  retornam Resource. Sem queries Eloquent, sem `request()->validate()`,
  sem regra.
- Resources só formatam saída — sem queries, sem regra.
- Models carregam scopes (incluindo tenant scope) + relações + casts;
  não orquestram fluxos entre múltiplos modelos.
- Filament e API compartilham os mesmos Services — não duplicar lógica.

**Fora do escopo do MVP** (não implementar sem amendment): telemedicina
nativa, multi-unidade por tenant, prontuário eletrônico, pré-pagamento
de consultas pelo paciente. Modelo de cobrança é híbrido: base por
profissional ativo + cota mensal de mensagens IA com excedente cobrado
(via Stripe).

**Documentos canônicos** (ler antes de planejar features):

- `docs/project-description.md` — RFs/RNFs em PT-BR.
- `docs/user-stories.md` — 53 user stories organizadas em 13 épicos.
- `.specify/memory/constitution.md` — **7 princípios** (v1.3.0), 5 dos
  quais NON-NEGOTIABLE: LGPD, isolamento multi-tenant, segurança
  clínica da IA, conformidade Meta nos disparos, segurança operacional
  (rate limiting por tenant+endpoint, brute force lock, argon2id ou
  bcrypt cost ≥ 12, TLS 1.3). **2FA TOTP foi removido do MVP** em
  v1.3.0 — pode ser reintroduzido como opt-in voluntário em fase
  futura. E2E (Playwright/Cypress) obrigatório nas jornadas críticas;
  migrações imutáveis após produção; pt-BR padrão com arquitetura
  i18n-ready.

Idioma do projeto: PT-BR. Manter docs/specs/comentários em português
salvo quando convencional usar inglês (nomes de classe, identificadores,
mensagens de log estruturado).
