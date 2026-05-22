# Quickstart: Finalização do MVP (Fase 8)

**Branch**: `008-finalizacao-mvp` | **Date**: 2026-05-21 | **Phase**: 1

> Roteiros de smoke E2E para QA em staging. Cada cenário valida uma das jornadas críticas da §Definição de Pronto do MVP. Executar **após** implementação dos lotes correspondentes.

---

## Pré-requisitos comuns

- Tenant de QA criado em staging com plano `pro` (daily_campaign_limit=1000, api_rate_limit=1000/min, webhook_max=20)
- Usuários seedados:
  - Super Admin: `superadmin@paciente360.com.br`
  - Admin Clínica: `admin@qa-clinic.com.br`
  - Médico: `medico@qa-clinic.com.br`
  - Atendente: `atendente@qa-clinic.com.br`
- 50 pacientes seedados, dos quais:
  - 30 com opt-in de marketing
  - 20 sem opt-in de marketing
  - 10 com última `ConsultaRealizada` há > 6 meses (elegíveis para reativação)
  - 5 com receita controlada vigente
- WhatsApp Business Cloud API conectado com sandbox + 2 templates HSM aprovados (`reactivation_v1`, `seasonal_vaccination_v1`)
- Reverb conectado (não usado nesta fase mas mantido como sanity check)
- Sail rodando: `vendor/bin/sail up -d`

---

## Cenário 1 — Disparar Campanha de Reativação (Lote C)

**Cobertura**: AC-9.1.1 → AC-9.1.7, AC-9.3.1 → AC-9.3.3, SC-9.1, SC-9.2 + Gate Compliance.

### Passos

1. **Login Admin Clínica** em `https://qa.paciente360.com.br`.
2. **Navegar**: Campanhas → Nova campanha de reativação.
3. **Preencher**:
   - Nome: "Reativação Maio QA"
   - Critério de inatividade: 6 meses
   - Filtros: tag `vacinação` (assume seed tem 8 pacientes nessa intersecção)
   - Canal: WhatsApp
   - Template: `reactivation_v1` (aprovado, contém comando `/sair`)
4. **Clicar "Pré-visualizar"**.
   - **Validar**: contador exibe "8 pacientes elegíveis"
   - **Validar**: aviso "3 pacientes sem opt-in de marketing serão excluídos"
   - **Validar**: aviso "Template `reactivation_v1` — status: approved"
5. **Clicar "Disparar agora"**.
   - **Validar**: campanha transita para `dispatching`
   - **Validar**: polling do relatório atualiza a cada 30s (visual)
6. **Aguardar conclusão (~30s)**.
   - **Validar**: status final `completed`
   - **Validar**: relatório mostra 5 enviados, 3 bloqueados (`no_marketing_opt_in`)
   - **Validar**: 5 `MensagemCampanhaEnviada` eventos emitidos (verificar via `php artisan tinker → \App\Models\AuditLog::where('auditable_type', 'Campaign')->count()`)
7. **Simular destinatário enviando `/sair`** (via webhook simulado da Meta sandbox).
   - **Validar**: `ConsentimentoRevogado{finalidade='marketing'}` emitido para o paciente
   - **Validar**: paciente sai automaticamente da lista de elegíveis em nova campanha

### Critério de sucesso

- ✅ Todos os 7 ACs verdes
- ✅ Tempo total de criação ao primeiro envio: ≤ 5 minutos (SC-9.1)
- ✅ 0 destinatários bloqueados por motivo errado (SC-9.2 — 100% precisão)
- ✅ Audit log contém entry de criação, dispatch, e cada envio individual

---

## Cenário 2 — Executar Direito ao Esquecimento (Lote A)

**Cobertura**: AC-13.2.1 → AC-13.2.7, SC-13.2, SC-13.4 + Gate Mapa Anonimização.

### Passos

1. **Como paciente QA** (`paciente.qa@gmail.com`, 1 receita controlada, 3 agendamentos, 50 mensagens em conversas):
   - Acessar `https://qa.paciente360.com.br/privacy/forgetting-request` (formulário público)
   - Preencher dados + comprovação de identidade
   - Submeter solicitação
2. **Validar**:
   - `DireitoEsquecimentoSolicitado` evento emitido
   - Tarefa criada na inbox interna do Admin Clínica com countdown D+15 dias úteis
3. **Login Admin Clínica** → Privacidade → Solicitações de esquecimento.
   - **Validar**: tarefa aparece com prioridade alta
4. **Clicar "Executar"** → revisar mapa de anonimização → confirmar.
5. **Validar pós-execução**:
   - `DireitoEsquecimentoExecutado` evento emitido com `fields_anonymized` populado
   - Tabela `patients` row do paciente: nome=`"Paciente Anonimizado #123"`, cpf=`"000.000.000-00"`, telefone=`"00000000000"`, email=NULL
   - Tabela `prescriptions` (controlada): **preservada** com banner "Dados preservados — retenção até DD/MM/AAAA (Portaria 344/98)"
   - Tabela `messages`: corpo deletado, metadados (timestamps, canal, direção) preservados
   - Tabela `appointments`: preservada (referência para audit), nome do paciente nas listas mostra placeholder
   - Tabela `audit_logs`: preservada por 1 ano (LGPD Art. 16)
   - E-mail enviado ao requerente com confirmação + lista de categorias afetadas
6. **Tentar consultar paciente em qualquer tela** → vê dados anonimizados.

### Critério de sucesso

- ✅ Anonimização executada em ≤ 30 segundos
- ✅ Mapa Q26 aplicado integralmente (3 categorias — anonimizar, deletar, preservar)
- ✅ Sem FK violation (audit_logs e prescriptions controladas mantêm integridade)
- ✅ Audit log granular do processamento
- ✅ Receita controlada **continua acessível** ao médico emissor mas com banner "Dados preservados"

---

## Cenário 3 — Portabilidade de Dados (Lote A)

**Cobertura**: AC-13.2.8, SC-13.5.

### Passos

1. **Paciente solicita portabilidade** via formulário público.
2. **Validar**: `PortabilidadeDadosSolicitada` evento + tarefa na inbox.
3. **Admin Clínica clica "Gerar arquivo"**.
4. **Aguardar job** (~10–20s para paciente médio).
5. **Validar**:
   - Arquivo S3 gerado em `privacy/portability/{patient_id}/{request_id}.json`
   - URL assinada de 7 dias enviada por e-mail ao paciente
   - `PortabilidadeDadosExecutada` evento emitido
6. **Paciente baixa o arquivo** via URL assinada.
   - **Validar**: status muda para `downloaded`
   - **Validar**: JSON contém `schema_version: "1.0"`, dados cadastrais, timeline, agendamentos, receituários (controladas com items mascarados)
7. **Tentar acesso após 7 dias** (simular via override de `url_expires_at`).
   - **Validar**: URL retorna 403
   - **Validar**: paciente pode solicitar novo link sem reiniciar deadline LGPD

### Critério de sucesso

- ✅ Arquivo JSON estruturado disponível em ≤ 30s
- ✅ Receita controlada aparece com `items[].medication: "<protected>"`
- ✅ Audit log registra `PortabilidadeDadosExecutada` com `file_size_bytes`
- ✅ URL expira após 7 dias

---

## Cenário 4 — Super Admin Impersonate (Lote B)

**Cobertura**: AC-12.1.5, AC-12.1.6, SC-12.2 + Gate Impersonate Audit Granular.

### Passos

1. **Login Super Admin** em `https://qa.paciente360.com.br/admin` (Filament).
2. **Tenants** → buscar `qa-clinic` → clicar **"Impersonate"**.
3. **Preencher motivo** (≥10 chars): "Ticket #1234 — paciente reporta erro ao salvar agendamento".
4. **Confirmar**.
5. **Validar**:
   - Sessão de impersonate inicia (sair do Filament, entra na SPA do tenant)
   - Banner amarelo persistente no topo: "MODO IMPERSONATE — tenant QA Clinic — você está visualizando como suporte"
   - `ImpersonateIniciado` evento emitido
6. **Navegar por 5 telas** do tenant (Inbox, Pacientes, Agenda, Receituários, Configurações).
7. **Validar**:
   - 5 entries em `super_admin_audit_screens` (uma por tela)
   - Banner persiste em **todas** as telas
   - Ações executadas no tenant ficam vinculadas ao Super Admin no audit_log
8. **Sair do impersonate** (botão "Sair do impersonate" no banner).
9. **Validar**:
   - `ImpersonateEncerrado` evento emitido com `screens_visited_count=5`, `duration` calculada
   - Sessão Super Admin restaurada (volta ao Filament)
10. **Verificar audit log** (em Filament → Auditoria de impersonate).
    - **Validar**: log mostra início, 5 telas visitadas, fim — com IPs e timestamps.

### Critério de sucesso

- ✅ Banner presente em 100% das telas (SC-12.2)
- ✅ Audit granular de cada tela (Gate 7)
- ✅ Motivo ≥10 chars obrigatório
- ✅ Apenas 1 sessão ativa por Super Admin simultaneamente (constraint PARTIAL UNIQUE)

---

## Cenário 5 — Receber Webhook Externo (Lote D)

**Cobertura**: AC-11.1.1 → AC-11.1.6, AC-11.1.8, SC-11.1, SC-11.4.

### Pré-setup

- Servidor mock externo escutando em `https://webhook.site/abc123` (uso temporário)
- Endpoint segredo: `qa-secret-12345`

### Passos

1. **Login Admin Clínica** → Configurações → Webhooks → "Novo webhook".
2. **Preencher**:
   - URL: `https://webhook.site/abc123`
   - Segredo: `qa-secret-12345`
   - Eventos: marcar `AgendamentoCriado`, `AgendamentoCancelado`, `PrescricaoCriada`, `ConsultaRealizada`
3. **Salvar**.
4. **Validar**: `WebhookConfigurado` evento + `webhook_endpoints` row criada com `secret_hash` populado (segredo plaintext **não** persistido).
5. **Como Atendente do tenant**: criar um agendamento na agenda.
6. **Validar**:
   - Em ≤ 5 segundos, webhook chega em `webhook.site` com:
     - Header `X-CRM-Signature: sha256=<hex>` (HMAC válido contra `qa-secret-12345`)
     - Header `X-CRM-Event-Type: AgendamentoCriado`
     - Header `X-CRM-Correlation-Id: <uuid>`
     - Body JSON com `event_type`, `tenant_id`, `correlation_id`, `occurred_at`, e corpo do evento
   - `WebhookEntregue` evento emitido
   - `webhook_deliveries` row com status `delivered`, `latency_ms`, `response_status_code`
7. **Simular falha**: alterar URL para `https://webhook.site/inexistente-xyz` que retorna 500.
8. **Criar outro agendamento**.
9. **Validar retry policy**:
   - Tentativa 1: imediata, falha
   - Tentativa 2 após 30s
   - Tentativa 3 após 2min
   - (continuar acompanhando até 5 tentativas)
10. **Após esgotar**:
    - `WebhookFalhou` evento emitido
    - Row movida para `webhook_dead_letter` com `expires_at = now() + 30d`
    - Aparece em "Webhooks → Falhas" no painel Admin
11. **Admin Clínica corrige URL** e clica **"Reenviar"** no DLQ.
12. **Validar**:
    - `WebhookReagendado` evento emitido
    - Nova entry em `webhook_deliveries` com novo `correlation_id`
    - Entry no DLQ marcada com `resent_at`

### Critério de sucesso

- ✅ p95 webhook delivery ≤ 5s (SC-11.1)
- ✅ HMAC valida corretamente no destinatário
- ✅ Retry exponencial respeitado (Q16: 30s, 2m, 10m, 1h, 6h)
- ✅ DLQ acessível e reenvio manual funcional (SC-11.4)

---

## Cenário 6 — Consumir API Pública v1 (Lote D)

**Cobertura**: AC-11.2.1 → AC-11.2.8.

### Passos

1. **Admin Clínica** → Configurações → Tokens de API → "Novo token".
2. **Preencher** nome "QA Integration", escopo `read+write`.
3. **Validar**: token plaintext exibido **uma única vez** (`paciente360_qa_xxxxx`), depois apenas hash visível.
4. **Externo (curl)**: consumir endpoint:

   ```bash
   curl -H "Authorization: Bearer paciente360_qa_xxxxx" \
        -H "Accept: application/json" \
        https://qa.paciente360.com.br/api/public/v1/patients?per_page=10
   ```

5. **Validar resposta**:
   - 200 OK
   - Body com paginação (`data[]`, `meta`, `links`)
   - Headers: `X-RateLimit-Limit: 1000`, `X-RateLimit-Remaining: 999`, `X-RateLimit-Reset: <unix>`
   - Tenant resolvido implicitamente pelo token
6. **Tentar acessar endpoint fora do escopo** (Q14):

   ```bash
   curl -H "Authorization: Bearer paciente360_qa_xxxxx" \
        https://qa.paciente360.com.br/api/public/v1/campaigns
   ```

   - **Validar**: 404 (não 401, para não vazar existência)
7. **Tentar acessar receita controlada**:

   ```bash
   curl -H "Authorization: Bearer paciente360_qa_xxxxx" \
        https://qa.paciente360.com.br/api/public/v1/prescriptions/123
   ```

   - **Validar**: 200 com payload **mascarado** (tipo, datas, status; sem `items[].medication`)
8. **Stress rate limit**: fazer 1100 requisições em 1 minuto.
   - **Validar**: 1000 com 200; 100 últimas com 429 e header `Retry-After`
9. **Revogar token** no painel.
10. **Tentar novamente** com token revogado.
    - **Validar**: 401 Unauthorized + `TokenApiRevogado` audit log

### Critério de sucesso

- ✅ Documentação OpenAPI acessível em `/docs/api/v1.yaml`
- ✅ Rate limit aplicado corretamente (SC-11.3)
- ✅ Escopo restrito (Q14) — endpoints fora retornam 404
- ✅ Receitas controladas sempre mascaradas independente do scope

---

## Cenário 7 — Dashboard Executivo (Lote E)

**Cobertura**: AC-10.1.1 → AC-10.1.7, SC-10.1, SC-10.3.

### Passos

1. **Login Admin Clínica** → Dashboard.
2. **Validar carregamento**:
   - p95 ≤ 1,5 segundos (SC-10.1)
   - 5 cards exibidos: leads/canal, conversão, no-show, NPS (placeholder "Em breve"), faturamento estimado
   - Variação % vs. período anterior em cada card
3. **Mudar filtro de período** para "Últimos 90 dias".
   - **Validar**: cards recalculam usando agregação horária (`metric_aggregations`)
   - **Validar**: timestamp da última agregação aparece no rodapé
4. **Clicar em "Leads por Instagram: 23"**.
   - **Validar**: navegação para lista de leads filtrada por canal=Instagram + período
5. **Exportar PDF**.
   - **Validar**: PDF gerado em ≤ 3s com:
     - Cabeçalho da clínica (logo se configurado)
     - Sumário executivo
     - Gráficos vetoriais SVG
     - Rodapé com filtros + timestamp
   - **Validar**: audit log `RelatorioExportado` registrado
6. **Login como Médico** (não proprietário).
7. **Abrir Dashboard**.
   - **Validar**: vê **apenas** dados onde é `professional_id` (Q13)
   - **Validar**: tentar manipular query string para ver tenant inteiro retorna 403

### Critério de sucesso

- ✅ p95 ≤ 1,5s para 50k pacientes em 30d (SC-10.1)
- ✅ Drill-down funcional (Q10)
- ✅ PDF em layout próprio formatado (Q12)
- ✅ Escopo Médico restrito (Q13/SC-10.3)
- ✅ 100% das exportações em audit_log (SC-10.2)

---

## Cenário 8 — Auditoria de Pseudonimização (Lote A + cron)

**Cobertura**: AC-13.3.3, AC-13.3.5, Gate 4 (CI estático).

### Pré-requisito de CI

- PR de teste introduz evento novo `EventoTesteIA` consumido pela IA **sem** marker `ContainsNoClinicalData`
- **Validar**: CI **falha** com mensagem clara apontando o evento e a linha onde declarar o marker

### Smoke runtime

1. **Rodar manualmente o cron semanal**:

   ```bash
   vendor/bin/sail artisan privacy:audit-pseudonymization-weekly --force
   ```

2. **Validar**:
   - Job processa amostra de 1% dos eventos persistidos da última semana
   - `pseudonymization_audits` row criada com mode=`runtime_replay`, sample_size populado, findings (esperado: vazio se tudo limpo)
   - Painel de Privacidade exibe timestamp da última auditoria e total escaneado
3. **Forçar finding**: inserir manualmente um evento com PII em texto plano (CPF não pseudonimizado).
4. **Re-rodar cron**.
5. **Validar**:
   - Finding aparece na tabela com `pattern_matched=cpf`, `severity=critical`
   - Ticket Sentry criado (verificar `tags.module=privacy`)

### Critério de sucesso

- ✅ CI gate bloqueia merge de evento sem marker (estática)
- ✅ Job semanal detecta PII inserida (runtime)
- ✅ 0 prompts ao LLM contêm CPF/telefone/email em texto plano (SC-13.3)

---

## Cenário 9 — Detecção de Anomalia + Notificação Super Admin (Lote B)

**Cobertura**: AC-12.3.4, SC-12.4, Q22.

### Passos

1. **Simular anomalia**: para um tenant QA, gerar 1000 mensagens IA em 1 hora (10× a média histórica).
2. **Aguardar cron** (≤15 min): `super-admin:detect-anomalies`.
3. **Validar**:
   - Row em `anomalies_detected` com categoria=`ai_usage_spike`, severity=`critical`
   - Item criado na inbox interna do Super Admin
   - E-mail crítico enviado ao endereço de plataforma cadastrado
4. **Como Super Admin**: abrir inbox → ver alerta → clicar "Acknowledged".
5. **Validar**: `acknowledged_at` populado; alerta sai do contador "não lido".

### Critério de sucesso

- ✅ Anomalia detectada em ≤15min do início
- ✅ Notificação dual (inbox + e-mail) recebida (SC-12.4)
- ✅ Cooldown 30min impede flood de mesma categoria

---

## Cenário 10 — Cancelamento de Tenant com Retenção Diferenciada (Lote B)

**Cobertura**: AC-12.1.10, Q20, Gate 6 (Retention Policy).

### Passos

1. **Como Super Admin**: cancelar tenant QA com receita controlada vigente.
2. **Validar imediato**:
   - `TenantCancelado` evento emitido com `retention_policy=differentiated_per_category`
   - Tenant aparece em status `cancelado` na listagem
   - Sessões ativas revogadas
   - Jobs em fila do tenant pausados
3. **Aguardar cron diário** `super-admin:apply-retention-policy` (2:00 BRT).
4. **Validar após 30 dias** (simular via override de `canceled_at`):
   - Tabela `tenants.config` deletada
   - Receitas controladas: preservadas (2a ainda restantes)
   - Billing/financeiro: preservado (5a)
   - Audit logs: preservados (1a)
5. **Validar após 90 dias** (simular):
   - Dados de paciente: anonimizados (mapa Q26 aplicado)
   - Receitas controladas: preservadas
   - Billing/audit_logs: preservados
6. **Validar após 2 anos** (simular):
   - Receitas controladas: deletadas
   - Billing: preservado (5a ainda restantes)
7. **Validar após 5 anos** (simular):
   - Billing: deletado
   - Audit logs: deletados há ~4 anos (1a apenas)

### Critério de sucesso

- ✅ Política diferenciada por categoria aplicada corretamente em cada checkpoint
- ✅ Audit_log de cada transição de retenção
- ✅ Sem FK violation em nenhum momento

---

## Sequência recomendada para QA staging

1. **Lote A entregue** → rodar Cenários 2, 3, 8
2. **Lote B entregue** → rodar Cenários 4, 9, 10
3. **Lote C entregue** → rodar Cenário 1
4. **Lote D entregue** → rodar Cenários 5, 6
5. **Lote E entregue** → rodar Cenário 7
6. **Suite full verde** → smoke completo (todos 10) em ambiente espelho de produção

---

## Conclusão Phase 1 (quickstart)

✅ 10 cenários de smoke E2E cobrindo:
- Os 5 lotes (A–E)
- Os 7 gates constitucionais
- As 4 jornadas críticas da §Definição de Pronto do MVP
- Métricas chave (SC-9.*, SC-10.*, SC-11.*, SC-12.*, SC-13.*)

Roteiros serão exercitados manualmente em staging + automatizados como E2E Playwright após Lote E (≥4 jornadas críticas — gate constitucional Princípio IV).
