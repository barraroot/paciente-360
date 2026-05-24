# Roteiro de Demo — Paciente360 (MVP)

> CRM médico SaaS multi-tenant, omnichannel, com IA matricial e LGPD by-design.
> Duração-alvo: **15–18 min** de demo + 5 min de Q&A. Stack: Laravel 13 + Vue 3.

---

## 0. Pré-demo (fazer ANTES, com 15 min de folga)

```bash
vendor/bin/sail up -d                      # sobe containers
vendor/bin/sail artisan migrate:fresh --seed --seeder=RolesSeeder
vendor/bin/sail artisan db:seed --class=DevSeeder   # dados ricos (clinica-alfa/beta)
vendor/bin/sail npm run build              # build do front (ou `npm run dev` se for editar)
```

**Checklist de abas do navegador** (deixar abertas e logadas):
1. `http://crm.lvh.me/login` — SPA do painel (Bearer).
2. `http://crm.lvh.me/admin` — Super Admin (Filament, cookie-session).
3. Uma aba anônima para mostrar isolamento multi-tenant (opcional).

**Credenciais (senha de todos: `password123`):**

| Persona | E-mail | Papel |
|---|---|---|
| Gestora da clínica | `admin@clinica-alfa.test` | admin-clinica |
| Médico | `medico@clinica-alfa.test` | medico |
| Recepção | `atendente@clinica-alfa.test` | atendente |
| Super Admin (SaaS) | `admin@flowsys.com.br` | super-admin (`/admin`) |

> Dica: faça login com a **admin** antes de começar (login resolve o tenant pelo e-mail — sem precisar digitar slug).

---

## 1. Abertura — o problema (1 min)

**Fale, não mostre ainda:**
- "Clínicas perdem pacientes por **canais desconectados** (WhatsApp, Instagram, telefone), agenda manual e zero memória do relacionamento."
- "O Paciente360 unifica **atendimento + agenda + prontuário + receituário** num só lugar, com **IA que agenda sozinha** dentro de guardrails clínicos, e **LGPD desde o design**."
- "É **SaaS multi-tenant**: cada clínica isolada, planos e cobrança por assinatura."

---

## 2. Onboarding guiado (1–2 min) — *persona: admin-clinica*

- Tela: `/panel/onboarding` (ou cadastro novo em `/register-clinic` se quiser mostrar do zero).
- **Mostrar:** wizard com etapas que **destravam em sequência** — Dados da clínica → 1º Profissional → Configurar agenda.
- **Falar:** "O sistema guia a clínica até o primeiro valor. Cada etapa concluída libera a próxima — sem tela em branco."
- Conclua a etapa **"Primeiro profissional"** abrindo o modal ali mesmo (destrava a agenda). *(entregue na Spec 012)*

---

## 3. Gestão de Profissionais (1 min) — *admin-clinica*

- Tela: `/panel/profissionais`.
- **Mostrar:** cadastrar profissional, escolher conselho (CRM/CRO/...), e o fluxo de **convite por e-mail** vs **vincular usuário existente**.
- **Falar:** "Validação de conselho única por clínica, e o profissional vira usuário ao aceitar o convite. Tudo auditado."

---

## 4. Agenda (3 min) — *admin-clinica → medico*

- Tela: `/panel/agenda`.
- **Mostrar:**
  1. Visão de calendário; criar uma consulta (modal acessível).
  2. **Drag-and-drop** para reagendar.
  3. **Lista de espera** (`/panel/agenda/lista-espera`) — FIFO automática.
  4. Mencionar **sincronização Google Calendar** (sub-calendário por clínica, sem PII no payload — LGPD).
- **Falar:** "Trava atômica no banco impede **duplo agendamento** no mesmo horário. Confirmação automática T-24h/T-2h. Quando o paciente não responde, vira tarefa na inbox."
- Troque para o **médico** e mostre que ele vê **só a própria agenda**.

---

## 5. CRM de Pacientes (2 min) — *admin-clinica*

- Tela: `/panel/pacientes` → abrir um paciente (`/panel/pacientes/:id`).
- **Mostrar:**
  1. **Linha do tempo unificada** do paciente (eventos de agenda, mensagens, notas).
  2. **Funil de leads (Kanban)** em `/panel/pacientes/funil` — arrastar card entre colunas.
  3. Busca por nome/telefone (trigram + unaccent).
- **Falar:** "Notas têm **visibilidade por tipo** (clínica/financeira/comportamental) controlada por permissão. Importação em massa e mesclagem de duplicados também existem."

---

## 6. Inbox Omnichannel + IA Matricial (3 min) — *o diferencial*

- Tela: `/panel/inbox`.
- **Mostrar:**
  1. Caixa unificada (WhatsApp/Instagram/web no mesmo lugar).
  2. Abrir uma conversa — **IA classifica intenção** e responde dentro da base de conhecimento.
  3. **"Humano assume"** — atendente toma o controle a qualquer momento.
  4. **Agendamento via chat pela IA** (reserva slot e confirma).
- **Falar (guardrails — ponto forte):** "A IA **nunca dá conselho clínico**. Eventos enviados ao modelo são **pseudonimizados por design** (sem CPF/dados clínicos) — temos um gate de CI que falha o build se vazar PII. Toda decisão da IA é **auditável**."

---

## 7. Receituários (1–2 min) — *medico*

- Tela: `/panel/receituarios` → abrir uma receita.
- **Mostrar:** emissão; alerta de vencimento (D-15/D-7/D-1); **renovação assistida por IA**.
- **Falar (compliance):** "Receitas **controladas (Portaria 344/98)** têm validade fixa, item único e **mascaramento** — só o emissor e o admin veem o conteúdo, e cada visualização é auditada."

---

## 8. Dashboards (1–2 min) — *admin-clinica*

- Telas: `/panel` (Home) e `/panel/reports/executive` (Executivo).
- **Mostrar:** Home com KPIs do dia, próximos atendimentos, itens que pedem atenção; Executivo com filtros de período (24h/7d/30d/90d) e export PDF.
- **Falar:** "Uma request consolida o dashboard; cache de 30s; degradação graciosa — se uma seção falha, as outras continuam."

---

## 9. LGPD + Super Admin (2 min) — *fecha com confiança*

- **LGPD** (`/panel/privacy/consents`): mostrar registro de **consentimento por finalidade** e o fluxo de **direito ao esquecimento** (anonimização com preservação do que a lei obriga — receitas controladas, audit).
- **Super Admin** (`http://crm.lvh.me/admin`): logar como `admin@flowsys.com.br`.
  - Mostrar gestão de **tenants/planos**, **suspensão por inadimplência**, **impersonate com banner + auditoria**, e **detecção de anomalias**.
- **Falar:** "Operação do SaaS toda auditada. Impersonate exige rastro — cada tela visitada é logada."

---

## 10. Fechamento (30s)

- "Em resumo: **um só lugar** para conversar, agendar, atender e cobrar — com **IA que trabalha sozinha com segurança**, **LGPD de fábrica** e **multi-tenant** pronto para escalar."
- "Stack moderna (Laravel 13 + Vue 3), **suíte de testes verde de ponta a ponta** (1500+ testes)."

---

## Plano B / perguntas difíceis

| Situação | Resposta / ação |
|---|---|
| Vite/manifest error | `vendor/bin/sail npm run build` e recarregar |
| Tela vazia / 403 | confirmar que está logado e que o usuário tem a permission do módulo |
| "A IA é segura clinicamente?" | guardrails + pseudonimização + gate de CI + auditoria de decisão |
| "E isolamento entre clínicas?" | tenant scope automático; mostrar 2ª clínica (clinica-beta) não vê dados da alfa |
| WhatsApp/Google ao vivo falham | são integrações externas com stubs em dev; mostrar o fluxo, não a chamada real |
| Sincronização real Google/Outlook | Google via sub-calendário tenant-scoped; Outlook é fase seguinte |

## O que NÃO mostrar (ainda incompleto / fora do MVP de demo)
- Billing/Stripe ao vivo (cobrança real) — falar como roadmap.
- Sparkline real no dashboard executivo (placeholder).
- Webhooks/API pública — mencionar como "pronto para integradores", sem demo ao vivo.
