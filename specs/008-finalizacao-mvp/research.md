# Research: Finalização do MVP (Fase 8)

**Branch**: `008-finalizacao-mvp` | **Date**: 2026-05-21 | **Phase**: 0

---

## 0. Propósito

Phase 0 do `/speckit-plan` consolida:

1. **Decisões já tomadas** nas 29 clarifications da spec (resolvidas em lote em 2026-05-21).
2. **Tradeoffs e alternativas consideradas** para cada uma — material para reabertura futura.
3. **Pesquisa adicional sobre tecnologias** mencionadas no plan (DOMPDF vs. Browsershot, Passport vs. Sanctum-OAuth-custom, regex PII robusta).
4. **Patterns reutilizáveis das fases 0–7** que são aplicados aqui sem repensar.

> ✅ **Resultado**: Nenhum `NEEDS CLARIFICATION` aberto. Todas as 29 decisões têm rationale + alternatives. Phase 1 pode prosseguir.

---

## 1. Decisões consolidadas das 29 clarifications

> Formato: **Decision** → **Rationale** → **Alternatives considered** → **Reabrir quando**.

### Módulo 1 — Campanhas

#### Q1 — Critério de "inativo"

- **Decision**: Última `ConsultaRealizada` (status = `realizada`).
- **Rationale**: Reativação é trazer paciente que parou de ser **atendido**. Mensagens trocadas (mesmo só "obrigado") superestimam engajamento real. Métrica de eficácia fica auditável: input = `last_realized < D-N`, output = nova `ConsultaRealizada` em ≤60d.
- **Alternatives considered**:
  - (B) Última `MensagemRecebida` → rejeitada: superestima base ativa, polui campanha de reativação com pacientes que estão em conversa ativa.
  - (C) Última interação (consulta OR mensagem OR agendamento) → rejeitada: definição demasiado ampla, dilui a métrica.
- **Reabrir quando**: clínica reportar que pacientes em "tratamento conversacional" (sem consulta presencial) ficam excluídos da reativação.

#### Q2 — Limite diário por plano

- **Decision**: Definido pelo plano de assinatura (sugestão: básico 200, pro 1000, enterprise 5000). Admin pode reduzir, não aumentar.
- **Rationale**: Protege quality rating WhatsApp + alinha monetização + previne noisy neighbor (Princípio II).
- **Alternatives considered**:
  - (A) Fixo sistêmico → rejeitada: ignora diversidade de tamanho de clínica.
  - (B) Configurável por tenant sem teto → rejeitada: tenant pode degradar quality rating com volume excessivo.
- **Reabrir quando**: Meta mudar política de quality rating ou tenants relatarem que limites são apertados.

#### Q3 — Canal único por campanha

- **Decision**: Cada campanha tem exatamente 1 canal alvo. Multi-canal exige campanhas separadas.
- **Rationale**: Deduplicação cross-canal + fallback + métricas split aumentam superfície. Modelo 1:1 cobre 95% dos casos reais (WhatsApp dominante).
- **Alternatives considered**:
  - Multi-canal com fallback (WhatsApp → Instagram → SMS) → rejeitada: pesadelo de UI/UX e métricas.
- **Reabrir quando**: clínica reportar friction operacional ao criar 2 campanhas para mesma audiência.

#### Q4 — Sem aprovação intermediária

- **Decision**: Admin Clínica cria e dispara em fluxo único. Pré-visualização é a checagem final.
- **Rationale**: Workflow approval adiciona estado + papel + UX de filas — fora do tempo MVP.
- **Alternatives considered**:
  - Aprovação por médico responsável → rejeitada: gargalo operacional.
- **Reabrir quando**: clínica grande pedir compliance internal sign-off.

#### Q5 — Disparo único (sem nurturing multi-step)

- **Decision**: 1 step por campanha.
- **Rationale**: Nurturing degrada quality rating + complexidade de orquestração. 1 envio resolve 80%.
- **Alternatives considered**:
  - Multi-step D+3/D+7/D+14 → rejeitada: incremento de complexidade desproporcional ao ROI MVP.
- **Reabrir quando**: tenant reportar taxa de conversão menor que 8% (justifica nurturing).

#### Q6 — Polling cliente 30s

- **Decision**: Atualização do relatório de campanha via polling HTTP a cada 30s durante disparo + consolidação ao fim.
- **Rationale**: WebSocket via Reverb exigiria autorização de canal + reconnect. Polling 30s é suficiente para batches de minutos. Reverb existe (Fase 4) mas não usar aqui.
- **Alternatives considered**:
  - Broadcast via Reverb → rejeitada: overhead de canal autenticado para feature de leitura simples.
  - Polling 5s → rejeitada: 12× mais carga sem ganho de UX percebido.
- **Reabrir quando**: usuários reportarem latência de relatório insuficiente.

#### Q7 — Apenas `business_hours` do tenant

- **Decision**: Sem blackout sistêmico extra. Disparo respeita `business_hours` configurado na Fase 5.
- **Rationale**: Blackout duplicado gera regra confusa. Tenant é dono do seu horário.
- **Alternatives considered**:
  - Blackout sistêmico (22h-08h + domingos) → rejeitada: paternalismo desnecessário; cada clínica conhece seu paciente.
- **Reabrir quando**: regulador impuser horário máximo (não existe hoje no Brasil).

### Módulo 2 — Relatórios

#### Q8 — NPS como placeholder

- **Decision**: Card de NPS exibe "Em breve — disponível em fase futura".
- **Rationale**: NPS exige timer pós-consulta + UI survey + scoring — módulo separado não trivial.
- **Alternatives considered**:
  - Coleta manual pelo atendente → rejeitada: dados ruidosos, viés de seleção.
  - Pesquisa automática pós-consulta → adotada para pós-MVP (fase futura).
- **Reabrir quando**: priorização de Customer Success no roadmap.

#### Q9 — Atualização horária + queries live ≤24h

- **Decision**: Agregação horária para ≥7d; queries on-demand para ≤24h.
- **Rationale**: Tradeoff fresh vs. performance. Pesquisa: PostgreSQL com índice composto e materialized view atualizada hourly cobre 10k consultas / 50k pacientes < 1s.
- **Alternatives considered**:
  - Tempo real always → rejeitada: ≤1.5s p95 inviável com 50k pacientes.
  - Nightly only → rejeitada: dado de "hoje" indisponível.
- **Reabrir quando**: dataset crescer 10× (>500k pacientes/tenant) — pode exigir Apache Druid ou similar.

#### Q10 — Drill-down abre lista filtrada

- **Decision**: Clicar em KPI abre a lista correspondente (pacientes / conversas / agendamentos) com filtros aplicados.
- **Rationale**: Drill-down multiplica valor. Reutiliza views das Fases 2–7.
- **Alternatives considered**:
  - Modal com top-N → rejeitada: amputa a navegação natural.
  - Sem drill-down → rejeitada: KPI vira "número bonito sem ação".
- **Reabrir quando**: análise de uso mostrar baixa taxa de clique em KPIs (<5%).

#### Q11 — Variação % vs. período anterior

- **Decision**: Cada KPI mostra Δ% versus período imediatamente anterior de mesmo tamanho.
- **Rationale**: Leitura mais comum em dashboard executivo. Comparativo lado a lado é nice-to-have.
- **Alternatives considered**:
  - Lado a lado com toggle → adotada para pós-MVP.
- **Reabrir quando**: usuário pedir explicitamente.

#### Q12 — PDF em layout próprio

- **Decision**: PDF com cabeçalho da clínica, sumário, gráficos vetoriais, rodapé com filtros + timestamp.
- **Rationale**: Apresentável a stakeholders externos. Screenshot é frágil (resolução, viewport).
- **Alternatives considered**:
  - Screenshot via headless browser → rejeitada: 5-10× mais lento + frágil.
- **Pesquisa adicional**: ver §3.1 abaixo para decisão entre DOMPDF e Browsershot.

#### Q13 — Escopo por papel

- **Decision**: Médico = própria agenda apenas; Admin Clínica = tenant inteiro; Atendente/Recepcionista = tenant exceto controladas.
- **Rationale**: Princípio do menor privilégio (Princípio I). Evita vazamento entre médicos.
- **Alternatives considered**:
  - Médico vê tenant inteiro → rejeitada: viola menor privilégio.
  - Toggle entre os modos → rejeitada: complexidade sem ROI.
- **Reabrir quando**: clínica com modelo de "todos veem tudo" pedir flag.

### Módulo 3 — Integrações

#### Q14 — Escopo API pública v1

- **Decision**: pacientes (RW), agendamentos (RW), mensagens (R), receituários (R mascarado controladas), tipos (R), profissionais (R). Excluídos: campanhas, métricas, billing, audit logs, decisões IA.
- **Rationale**: Superfície mínima protege LGPD + Meta + integridade. Faltantes em v1.1 sem breaking change.
- **Alternatives considered**:
  - Tudo exposto → rejeitada: risco regulatório alto.
  - Apenas read-only em tudo → rejeitada: bloqueia integrações de ERP que sincronizam paciente/agenda.
- **Reabrir quando**: parceiro técnico pedir endpoint não exposto.

#### Q15 — Rate limit por token + plano + IP

- **Decision**: Limites primários por token de tenant diferenciados por plano (100/1000/5000 req/min). Cap defensivo por IP 10k req/min anti-DDoS.
- **Rationale**: Token alinha cobrança; IP é hard cap defensivo.
- **Alternatives considered**:
  - Só IP → rejeitada: tenant compartilha NAT corporativo, falsos positivos.
  - Limites globais sem plano → rejeitada: ignora monetização.
- **Reabrir quando**: definir limites concretos para enterprise customizados.

#### Q16 — 5 retries + backoff + DLQ 30d

- **Decision**: 5 tentativas com cadência 30s, 2min, 10min, 1h, 6h (~7,5h total). Após esgotar, evento vai para DLQ com retenção 30 dias e botão de reenvio manual.
- **Rationale**: 5 tentativas é padrão Stripe; DLQ resolve "operacional não bloqueante".
- **Alternatives considered**:
  - 3 tentativas → rejeitada: muitos falsos positivos por flakiness de 5xx.
  - Descarte automático sem DLQ → rejeitada: perda de evento sem recoverability.
- **Reabrir quando**: análise de uso mostrar que DLQ raramente é tocada (sugere reduzir retries).

#### Q17 — Catálogo 13 eventos

- **Decision**: Inclui eventos materiais de Fases 2/3/5/7. Exclui: campanhas, webhooks, audit logs, decisões IA.
- **Rationale**: Cobre o que sistemas externos esperam (BI, ERP, automação). Excluídos evitam loop/vazamento.
- **Alternatives considered**:
  - Apenas 5 do PRD original → rejeitada: subutiliza valor.
  - Catálogo completo → rejeitada: eventos internos não são úteis para integradores.
- **Reabrir quando**: tenant pedir evento não exposto.

#### Q18 — Sanctum default + OAuth opt-in

- **Decision**: Sanctum hashado (Fase 4 reaproveitado) default. OAuth 2.0 Client Credentials opt-in para enterprise via `laravel/passport`.
- **Rationale**: Sanctum cobre 90% dos parceiros. OAuth é upgrade para BI corporativo / ERP hospitalar.
- **Alternatives considered**:
  - Só Sanctum → rejeitada: bloqueia integradores enterprise.
  - Só OAuth → rejeitada: overhead de fluxo padrão para parceiros pequenos.
- **Pesquisa adicional**: ver §3.2 abaixo sobre Passport vs. alternative custom.

### Módulo 4 — Super Admin

#### Q19 — Impersonate total + banner + audit granular

- **Decision**: Super Admin impersonando tem acesso total ao tenant (incluindo controladas), banner persistente, audit log granular por tela visitada.
- **Rationale**: Suporte precisa diagnosticar incidentes reais; transparência via banner + audit endereça o risco LGPD.
- **Alternatives considered**:
  - Acesso limitado a config/billing → rejeitada: suporte cego é inútil.
  - Acesso total sem audit granular → rejeitada: viola Princípio I (auditabilidade).
- **Reabrir quando**: encontro com DPO/jurídico revelar restrição adicional.

#### Q20 — Retenção diferenciada por categoria

- **Decision**: Billing 5a, controladas 2a, audit 1a, paciente 90d, config 30d.
- **Rationale**: Única política juridicamente defensável; respeita LGPD + Portaria 344/98 + Lei 12.682/2012.
- **Alternatives considered**:
  - Tudo 90d → rejeitada: viola Portaria 344/98 (2 anos de controladas).
  - Tudo 5a → rejeitada: violação LGPD (princípio da necessidade).
- **Reabrir quando**: mudança em lei fiscal ou regulatória.

#### Q21 — Churn conservador + revenue churn separado

- **Decision**: Churn primário = cancelados / ativos no início do período. Revenue churn separado captura downgrades.
- **Rationale**: Padrão SaaS; revenue churn não infla a métrica principal.
- **Alternatives considered**:
  - Tudo somado (cancel + downgrade significativo) → rejeitada: definição imprecisa de "significativo".
- **Reabrir quando**: investidor pedir métrica específica.

#### Q22 — Inbox + e-mail crítico + 4 categorias

- **Decision**: 4 anomalias monitoradas (conversão trial→pago, consumo IA, falha webhook, inadimplência). Canal: inbox interna + e-mail crítico para `severity=critical`.
- **Rationale**: Inbox garante visibilidade no painel; e-mail cobre "ninguém logado agora". Sem Slack/PagerDuty no MVP.
- **Alternatives considered**:
  - Só inbox → rejeitada: pode demorar para ser visto.
  - Slack webhook → rejeitada: dependência externa.
- **Reabrir quando**: time de plataforma crescer e justificar PagerDuty.

#### Q23 — Criação manual de tenant com `billing_mode`

- **Decision**: Super Admin pode criar tenant; `billing_mode ∈ {stripe, offline_invoice}`. Offline ignora gates Cashier.
- **Rationale**: Enterprise sales é caso real; sem modo offline, time comercial não consegue fechar contratos enterprise.
- **Alternatives considered**:
  - Só self-service → rejeitada: bloqueia funil enterprise.
- **Reabrir quando**: modelo de PaaS revenue share for considerado.

### Módulo 5 — Privacidade/LGPD

#### Q24 — Consentimento hierárquico

- **Decision**: 3 níveis — transacional implícito ao cadastro; marketing opt-in explícito; pesquisa opt-in explícito (placeholder).
- **Rationale**: Equilibra UX e conformidade. 4 perguntas no primeiro contato afasta paciente.
- **Alternatives considered**:
  - Tudo de uma vez → rejeitada: paciente revoga "tudo" e quebra canal transacional.
  - Tudo-ou-nada com aviso → rejeitada: friction excessivo.
  - Granularidade fina (compartilhamento com convênio etc.) → adotada parcialmente, restante pós-MVP.
- **Reabrir quando**: regulador exigir granularidade adicional ou integração TISS for priorizada.

#### Q25 — Revogação granular; `/sair` revoga só marketing

- **Decision**: `/sair` revoga apenas marketing; `/sair tudo` revoga tudo; formulário de privacidade tem menu.
- **Rationale**: Revogação total via `/sair` quebra canal transacional (paciente perde confirmação de consulta).
- **Alternatives considered**:
  - `/sair` revoga tudo → rejeitada: anula utilidade do canal.
  - Menu interativo único → rejeitada: friction para o paciente.
- **Reabrir quando**: paciente reclamar que `/sair` "não funciona" (eduacação ou ajuste).

#### Q26 — Mapa de anonimização explícito

- **Decision**: Mapa por campo (anonimizar / deletar / preservar). Ver detalhe em §Clarifications da spec.
- **Rationale**: Única forma de tornar a execução auditável.
- **Alternatives considered**:
  - Anonimização ad-hoc no código → rejeitada: interpretação por desenvolvedor.
  - Apenas delete físico → rejeitada: viola obrigação legal de retenção (controladas, billing).
- **Reabrir quando**: novo campo de PII for adicionado a `patients` (force atualização do mapa).

#### Q27 — D-5 inbox / D-2 inbox + e-mail + alerta visual

- **Decision**: Notificação progressiva. D-5 inbox; D-2 inbox + e-mail + alerta visual persistente; D-0 vencido → alerta crítico ao Super Admin.
- **Rationale**: Dois pontos de contato com escalonamento; alerta visual persistente impede "esquecer no rodapé".
- **Alternatives considered**:
  - Só inbox → rejeitada: pode passar despercebido.
  - SMS → rejeitada: dependência externa + custo.
- **Reabrir quando**: análise de uso mostrar que tarefas LGPD vencem (sugere notificar antes).

#### Q28 — Portabilidade implementada no MVP

- **Decision**: Arquivo JSON estruturado via URL assinada 7d. Escopo: cadastrais + timeline pública + agendamentos + receituários (controladas mascaradas).
- **Rationale**: LGPD Art. 18º V é direito explícito; transferir para pós-MVP é risco regulatório.
- **Alternatives considered**:
  - CSV → rejeitada: estruturas aninhadas (mensagens, prescriptions com itens) ficam ruins em CSV plano.
  - PDF → rejeitada: não é portável (paciente não pode importar em outro sistema).
- **Reabrir quando**: paciente solicitar formato alternativo (XML, etc.).

#### Q29 — Auditoria dual: estática + replay

- **Decision**: Varredura estática via reflection (gate CI estendendo `ContainsNoClinicalData` da Fase 7) + replay semanal de 1% randômico contra detector regex.
- **Rationale**: Defesa em profundidade. Estática previne em design; replay valida em runtime.
- **Alternatives considered**:
  - Só estática → rejeitada: cobre só design-time; eventos podem ter dados inesperados em runtime.
  - Só replay → rejeitada: cobre só runtime; merge de evento mal feito passa.
  - Revisão humana → rejeitada: não escala.
- **Reabrir quando**: detector regex tiver muito falso positivo (ajustar pattern) ou cobertura semanal mostrar gap (aumentar amostra).

---

## 2. Padrões reutilizáveis das fases anteriores

Esta fase **não inventa pattern novo** — reaproveita tudo que já está consolidado. Documentação por referência para reduzir drift.

| Pattern | Origem | Uso na Fase 8 |
|---|---|---|
| **`BelongsToTenant` global scope** | Fase 1 | Todas as 24 tabelas novas exceto 5 globais do Super Admin |
| **`Auditable` trait + `RegistraEventoTimelineListener`** | Fase 2 | Toda ação de exportar/impersonate/esquecimento grava em audit_logs |
| **`pg_trgm` + `immutable_unaccent`** | Fase 2 | Busca de tenants no Filament (super admin) |
| **Sanctum hashado SHA-256** | Fase 4 | API tokens da Fase 8 + OAuth client secrets (mesmo pattern de hash) |
| **`User::guardName()` pin guard web** | Fase 4 | Já aplicado globalmente; nada novo nesta fase |
| **`Sanctum::actingAs($user, ['*'])` em testes** | Fase 4 | Todos os ~175 feature tests da fase |
| **PARTIAL UNIQUE constraint** | Fase 5 | `consent_records` UNIQUE `(patient_id, finalidade) WHERE revoked_at IS NULL` |
| **`withoutOverlapping()` em crons** | Fase 5 | 8 schedules desta fase |
| **TZ tenant + override profissional** | Fase 5 | Dashboards/relatórios formatam datas no TZ correto |
| **Stubs com TODO real implementation** | Fase 5 (`GoogleCalendarApiClient`) | OAuth Passport instalado lazy; PiiDetector com regex inicial cobrindo Brasil |
| **Idempotência dual: Redis NX + DB UNIQUE** | Fase 7 | `DispatchWebhookJob` + `SendCampaignMessageJob` |
| **`ContainsNoClinicalData` marker interface** | Fase 7 | Estendida para os 13 eventos do catálogo Q17 |
| **Mascaramento server-side de receitas controladas** | Fase 7 | `PublicPrescriptionResource` para API pública (Q14) reaplica |
| **Cron diário com lock Redis TTL > intervalo** | Fase 7 (`prescriptions:process-alerts`) | `campaigns:dispatch-scheduled` + `super-admin:detect-anomalies` |
| **Métricas Prometheus em classe dedicada** | Fase 4/7 (`AuthMetrics`, `PrescriptionMetrics`) | 5 classes novas: `CampaignMetrics`, `ReportMetrics`, `WebhookMetrics`, `SuperAdminMetrics`, `PrivacyMetrics` |

---

## 3. Pesquisa adicional (tecnologias mencionadas no plan)

### 3.1 PDF Renderer — DOMPDF vs. Browsershot

**Contexto**: Q12 escolheu "layout próprio formatado" para exportação PDF do dashboard. Precisa de PDF gerado server-side com gráficos.

| Critério | DOMPDF | Browsershot (puppeteer-php) |
|---|---|---|
| Já no projeto? | ❌ não | ❌ não |
| Performance | ⚡ rápido (10–50ms página) | 🐢 lento (1–5s — boot headless Chrome) |
| Gráficos vetoriais SVG | ✅ suportado bem | ✅ suportado (Chart.js renderiza) |
| CSS moderno (flexbox, grid) | ❌ limitado | ✅ completo |
| Containerização (Sail) | ✅ fácil — só PHP | ⚠️ requer Chromium no container |
| Tamanho do arquivo gerado | ✅ pequeno (~50KB) | ⚠️ maior (~200KB) |

**Decision**: **DOMPDF** (`barryvdh/laravel-dompdf`). Adicionado em `composer.json`. Razões: performance, sem dependência externa de Chromium, suficiente para dashboard executivo com gráficos SVG estáticos (não interativos no PDF).

**Mitigação do CSS limitado**: layout do PDF usa tabela + flex-fallback. DOMPDF suporta CSS3 limitado mas suficiente para template formatado.

**Tasks**: Lote E adiciona dependência e configura template. `DashboardPdfRenderer` é uma única classe que aceita data + filters e retorna PDF stream.

### 3.2 OAuth 2.0 Client Credentials — Passport vs. custom

**Contexto**: Q18 decidiu OAuth opt-in para enterprise. Sanctum não tem fluxo client_credentials nativo.

| Critério | `laravel/passport` v12 | Custom (em cima de Sanctum) |
|---|---|---|
| Maintenance burden | 🟢 mantido pelo Laravel team | 🔴 alto (recriar grant types, JWT signing, etc.) |
| Compliance OAuth 2.0 | ✅ full RFC 6749 | ⚠️ exige cuidado |
| Tamanho da dependência | ⚠️ ~30MB (com migrations) | ✅ zero overhead |
| Já familiar? | ✅ comum em Laravel | ❌ ad-hoc |
| Migration overhead | 8 tabelas novas | 1–2 tabelas |

**Decision**: **`laravel/passport` instalado lazy** (apenas quando primeiro tenant enterprise habilita `tenant.settings.api.oauth_enabled = true`). Razões: maturidade, compliance, redução de superfície de bug em código próprio de criptografia.

**Mitigação do "30MB extra"**: aceito porque enterprise é onde o valor é maior; tenants básico/pro continuam Sanctum puro sem overhead.

**Tasks Lote D**:
- Migration adiciona tabelas Passport (gated behind `if (config('integrations.oauth_enabled'))` no service provider — config default `false`).
- Setting `oauth_enabled` é toggle Filament no resource de tenant.
- Toggle dispara `php artisan passport:install` lazy + roda migrations Passport via `php artisan migrate --path=database/migrations/passport`.

**R-8-9** captura o risco operacional dessa decisão (vide plan.md §9).

### 3.3 Regex de PII para detector Q29

**Contexto**: Detector de PII roda em replay semanal sobre 1% amostra dos eventos persistidos consumidos pela IA.

Padrões alvos:

```regex
# CPF (BR) — tolera com ou sem máscara
\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b

# Telefone (BR) — celular ou fixo, com ou sem DDD
\(?\d{2}\)?\s?9?\d{4,5}-?\d{4}\b

# E-mail (RFC simplificado — match generoso para auditoria)
[\w.+-]+@[\w-]+\.[\w.-]+

# RG (BR) — varia por estado; padrão SP/RJ
\d{2}\.?\d{3}\.?\d{3}-?[\dXx]\b

# Cartão SUS — 15 dígitos
\b\d{15}\b
```

**Decision**: começar com **5 padrões acima** em `App\Support\Lgpd\PiiDetector::PATTERNS`. Detector retorna `{pattern_name, matched_substring, event_id, field_path}` — não armazena o valor matched (princípio da menor exposição).

**Falsos positivos esperados**:
- Sequências de 11 dígitos podem casar telefone sem ser telefone (ex.: `id_externo: "12345678901"`).
- RG sem máscara pode casar qualquer 9 dígitos.

**Mitigação**: relatório do replay gera ticket, não bloqueia. Time revisa falsos positivos e ajusta whitelist de campos (ex.: `external_ids` é safelisted).

**Reabrir quando**: detector gerar > 5% de falsos positivos no primeiro mês — adicionar Luhn check para CPF, validador de DDD para telefone.

### 3.4 PDF de portabilidade — formato estruturado JSON

**Contexto**: Q28 decidiu JSON. Decisão sobre versionamento e schema:

```json
{
  "schema_version": "1.0",
  "exported_at": "2026-05-21T14:00:00Z",
  "patient": {
    "id": 123,
    "nome": "Maria Silva",
    "cpf": "***.***.***-**",   // mascarado se opt-out compartilhamento
    "telefone": "+5511999999999",
    "...": "..."
  },
  "timeline": [...],
  "appointments": [...],
  "prescriptions": [...]  // controladas com items.medication = "<protected>"
}
```

**Decision**: `schema_version` semântico fixo no MVP (`1.0`). Schema documentado em `contracts/portability-schema-v1.json` (Phase 1).

### 3.5 HMAC SHA-256 para webhooks

**Contexto**: Não há decisão a tomar — é padrão indústria (GitHub, Stripe, Slack todos usam).

**Implementação**: `hash_hmac('sha256', $payload, $secret)` em `App\Domain\Integrations\Services\HmacSigner`. Header retornado: `X-CRM-Signature: sha256=<hex>`. Documentado em `contracts/webhook-events.yaml`.

### 3.6 Anomaly detection — algoritmo

**Contexto**: Q22 — 4 categorias de anomalia. Pesquisa sobre algoritmo.

| Categoria | Métrica | Algoritmo |
|---|---|---|
| Queda de conversão trial→pago | Conversão semanal | Comparar com média móvel das últimas 4 semanas; threshold = queda > 20% |
| Consumo IA > 10× média | `ai_messages_total` por tenant | Comparar mês corrente com média dos últimos 6 meses do mesmo tenant; threshold = > 10× |
| Falha de webhook > 50% em 1h | `webhook_delivered_total{status=failed}` | Janela móvel de 1h; threshold = (failed/total) > 0.5 com volume mínimo de 10 entregas |
| Inadimplência > 30 dias | `tenant.last_payment_at` | Comparar com `now() - 30 days`; severity escalates após 60d e 90d |

**Decision**: algoritmo simples baseado em thresholds (sem ML). Roda em `super-admin:detect-anomalies` every 15min com `withoutOverlapping()`.

**Mitigação de falso positivo**: cooldown de 30min entre alertas da mesma categoria do mesmo tenant. Métricas Prometheus permitem ajuste posterior sem deploy.

---

## 4. Considerações de Performance (medidas Esperadas)

| Operação | Target p95 | Estratégia |
|---|---|---|
| Criar campanha + preview de 1000 pacientes | < 2s | Query indexada em `appointments` (status=`realizada`) + tags via `pg_trgm` |
| Dispatch de 1000 mensagens | < 5min | Concurrency 10 workers fila `campaigns`; cada msg ~300ms |
| Webhook delivery (do evento ao destinatário) | < 5s | Listener síncrono → enfileira → worker fila `webhooks` (concurrency 20) |
| Dashboard executivo (50k pacientes, 30d) | < 1,5s | `metric_aggregations` pré-computado hourly + cache Redis 5min |
| Exportar PDF dashboard | < 3s | DOMPDF (~500ms) + Chart.js SVG (~200ms por gráfico) + 4 gráficos = ~1,3s |
| API pública GET /v1/patients (paginated 50) | < 200ms | Index existente `(tenant_id, created_at)`; resource serialization |
| Anonimização de paciente (esquecimento) | < 30s | Transação única com UPDATE em ~10 tabelas + S3 delete |

---

## 5. Conclusão Phase 0

✅ **Todas as 29 clarifications consolidadas com decision + rationale + alternatives**.
✅ **3 pesquisas técnicas adicionais** resolvidas (PDF renderer, OAuth, regex PII).
✅ **15 patterns reutilizáveis** de fases anteriores identificados — zero pattern novo precisa ser inventado.
✅ **0 NEEDS CLARIFICATION** restantes.

→ Phase 1 (data-model, contracts, quickstart) pode iniciar imediatamente.
