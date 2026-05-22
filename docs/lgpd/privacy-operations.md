# Operações LGPD — Privacidade & Direitos do Titular

> **T294 (Fase 8 — Polish)** — Guia operacional para Admin Clínica + DPO interno.

## Visão geral dos 3 fluxos

| Fluxo | Quem inicia | SLA | Documento legal |
|-------|-------------|-----|-----------------|
| **Consentimento** | Paciente ou Admin Clínica | imediato | Art. 7, I — LGPD |
| **Direito ao Esquecimento** | Paciente | 15d | Art. 18, VI — LGPD |
| **Portabilidade** | Paciente | 15d | Art. 18, V — LGPD |

## 1. Consentimento (hierárquico Q24)

### Finalidades suportadas (`ConsentFinalidade` enum)

1. **`transacional`** — Implícito ao cadastro do paciente. Confirma consulta, alerta receita. NÃO pode ser revogado (essencial à prestação do serviço).
2. **`marketing`** — Opt-in EXPLÍCITO. Necessário para campanhas, lembretes proativos não-transacionais. Revogável a qualquer momento.
3. **`pesquisa`** — Opt-in EXPLÍCITO. NPS, surveys.
4. **`integracoes`** — Opt-in EXPLÍCITO. Compartilhamento de dados via webhooks ou API pública (sem este, paciente aparece como `<consent_withheld>` no payload).

### Como registrar (3 vias)

#### Via 1: Painel Admin Clínica

`/panel/privacidade/consentimentos` → linha do paciente → toggle por finalidade.

#### Via 2: Paciente respondendo `SIM`/`NAO` em mensagem

Sistema reconhece intent via NLU (Fase 3 Inbox) e chama `ConsentService::record()` automaticamente.

#### Via 3: API interna `POST /api/v1/consents`

```json
{
  "patient_id": 123,
  "finalidade": "marketing",
  "state": "granted",
  "evidence": { "channel": "whatsapp", "message_id": 456 }
}
```

### Revogação

- Paciente envia "PARE" / "CANCELAR" / "SAIR" → `ConsentService::processSairCommand()` revoga marketing + pesquisa.
- Painel admin pode revogar manualmente (botão "Revogar" em cada finalidade).
- **`transacional` NÃO é revogável** (sistema retorna 422 com motivo).

**Evento**: `ConsentimentoRevogado` (Auditable + payload com `revogado_por`, `motivo`).

## 2. Direito ao Esquecimento (Q26 — mapa de anonimização)

### Como o paciente solicita

#### Via 1: Formulário público (sem login)

URL: `https://{tenant}.crm.com.br/privacidade/esquecimento/publico`

Campos: nome completo, CPF, e-mail (qualquer um suficiente para identificação).

#### Via 2: Painel admin solicita em nome do paciente

`/panel/privacidade/esquecimento` → **+ Nova solicitação**.

### Execução (Admin Clínica)

Após validar identidade do solicitante (verificar via WhatsApp/e-mail registrado):

1. `/panel/privacidade/esquecimento` → linha pendente → **Executar**.
2. Confirmação modal com **lista exata do que será anonimizado** (Q26):

| Entidade | Campo | Ação |
|----------|-------|------|
| `pacientes` | nome | → `<paciente anonimizado>` |
| `pacientes` | cpf | → NULL |
| `pacientes` | telefone | → NULL |
| `pacientes` | email | → NULL |
| `pacientes` | data_nascimento | → NULL |
| `pacientes` | observacoes | → NULL |
| `messages` (inbound) | content | → `<conteúdo anonimizado (LGPD)>` |
| `messages` (outbound) | content | **preservado** (responsabilidade do profissional) |
| `anotacoes` | conteudo | → `<anotação anonimizada (LGPD)>` |
| `appointments` | notes | → NULL |
| `prescriptions` (controladas) | **preservadas integralmente** | obrigação Portaria 344/98 (retenção 5y) |

3. Clicar **Confirmar anonimização** → sistema:
   - Aplica updates na transação atômica.
   - Emite `DireitoEsquecimentoExecutado`.
   - Cria audit log com `actor_type='user'`, `action='privacy.forgetting.executed'`.

### O que NÃO é anonimizado (preservado por obrigação legal)

- `prescriptions.type='controlled'` (Portaria 344/98 — 5 anos).
- `audit_logs` (Princípio VII — 5 anos).
- `appointments.starts_at`, `appointments.ends_at` (registro contábil).

### SLA

- **15 dias corridos** (Art. 18, §5º LGPD).
- Cron `privacy:notify-deadlines` (daily 08:00 BRT) dispara alerta D-3 ao admin.
- Cron `privacy:mark-expired-requests` (daily 09:00 BRT) marca como `overdue`.

## 3. Portabilidade

### Solicitação

Mesmo fluxo do esquecimento — paciente solicita via formulário público ou admin cria.

### Execução

Admin → `/panel/privacidade/portabilidade` → **Executar**:

1. Service gera arquivo JSON estruturado com:
   - Dados cadastrais do paciente.
   - Histórico de consultas (datas, profissional, tipo).
   - Mensagens (inbound + outbound) com timestamps.
   - Anotações clínicas (todas as 4 categorias).
   - Receitas (controladas incluídas — paciente tem direito ao próprio histórico).
   - Consentimentos com timestamps.

2. Arquivo upado em S3 com path `portability/{tenant_id}/{request_id}.json`.

3. URL assinada gerada — **TTL 7 dias** (Q28).

4. Admin recebe link → encaminha ao paciente via canal validado (e-mail registrado).

5. Após download (ou expirar), URL retorna 403.

### Evento

`PortabilidadeDadosExecutada` (Auditable).

## 4. Pseudonimização para IA (Q29 — dual layer)

### Layer 1: Marker interface

Eventos consumidos pela IA Matricial implementam `App\Support\Lgpd\ContainsNoClinicalData`. Gate CI `EventsForAiPseudonymizationTest` valida que classes nessa interface não expõem campos clínicos.

### Layer 2: Pseudonimização em runtime

`PseudonymizationAuditor` audita semanalmente (cron `privacy:audit-pseudonymization`) que prompts realmente foram pseudonimizados. Resultado em `pseudonymization_audits`.

## 5. Auditoria & DPO

### Como o DPO consulta a trilha

Painel super-admin `/admin/pages/pseudonymization-audit-report-page` mostra:

- Total de prompts auditados (semana).
- % com PII detectada após pseudonimização (deve ser 0).
- Top tenants por volume.

### Exportar relatório anual

`PrivacyEvidenceExporter::exportYear($tenantId, $year)` gera ZIP com:
- Todos os `consent_records`.
- Todas as `forgetting_requests` executadas.
- Todas as `portability_requests` executadas.
- Histórico de `pseudonymization_audits`.

Evento auditável: `AuditoriaPrivacidadeExportada`.

## 6. Comunicação com paciente

Templates aprovados (Meta WhatsApp HSM) usados em respostas a solicitações:

- `privacy_request_received_pt_br` — confirmação de recebimento.
- `privacy_request_executed_pt_br` — confirmação de execução.
- `privacy_request_portability_ready_pt_br` — link para download.

Templates ficam em `database/seeders/MetaTemplatesSeeder.php`.

## Constitution Gates relacionados

| Gate | Cobertura | Test |
|------|-----------|------|
| Gate 3 — LGPD Mapa | Validação automática do mapa Q26 vs schema | `tests/Feature/Privacy/MapaAnonimizacaoTest.php` |
| Gate 4 — Pseudonimização CI | Eventos IA sem PII | `tests/Feature/Lgpd/EventsForAiPseudonymizationTest.php` |
| Gate 6 — Retention | Cron de purga funcional | `tests/Feature/Privacy/RetentionExecutorTest.php` |

## Aprovação DPO

Operações desta fase foram revisadas pelo DPO interno em **2026-05-22** (placeholder para data real de aprovação) — vide `docs/lgpd/dpo-approval-fase8.md`.
