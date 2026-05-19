# Feature Specification: Gestão de Receituários (Fase 7 — Épico 8)

**Feature Branch**: `007-gestao-receituario`
**Created**: 2026-05-17
**Status**: Clarified — 13/13 NEEDS CLARIFICATION resolvidos em 2026-05-17; pronto para `/speckit-plan`
**Input**: User description — Cobertura completa do Épico 8 (RF-048 a RF-053) — US-8.1 Cadastro, US-8.2 Alerta de Vencimento, US-8.3 Renovação via IA, US-8.4 Relatório.

---

## Clarifications

### Session 2026-05-17

- **Q1** (validade de receita comum) → **A: Opção A** — Médico escolhe **duração** em preset `{30, 60, 90, 180}` dias. `expires_at` é calculado server-side como `issued_at + duração`. Teto fixo do sistema em **180 dias**. Sem configuração por tenant nesta fase.
  - *Justificativa*: equilibra UX (presets eliminam erro de data passada), cobertura clínica real (90-180d cobrem prescrições crônicas típicas) e simplicidade de modelo (sem migration extra de `tenant.settings`). Pode evoluir para configurável por tenant em fase posterior sem breaking change.

- **Q2** (estrutura de medicamentos) → **A: Opção A** — Lista de **1 a 10 medicamentos** por receita do tipo `comum` ou `especial`; **exatamente 1** para `controlada` (Portaria 344/98 — Notificação A é per-substância). Nome em **texto livre** com autocomplete via histórico do próprio médico (sem dependência externa). Campos por item: `nome`, `concentracao`, `forma_farmaceutica`, `posologia`, `quantidade`, `duracao_tratamento`. Três medicamentos diferentes com mesmo vencimento = **1 receita com 3 itens**.
  - *Justificativa*: espelha a folha física (1 receita = 1 documento legal); evita lock-in com catálogo ANVISA/TISS (futuro upgrade sem breaking change); limite 10 protege relatório e PDF; restrição `controlada → max=1` enforça Portaria 344/98.

- **Q3** (cancelamento vs. substituição) → **A: Opção A** — Status terminal único `cancelada` com `cancellation_reason_category` ∈ `{erro_emissao, desistencia_paciente, substituicao, outro}` + `cancellation_reason_text` (livre, ≤500 chars). Vinculação `previous_prescription_id` ocorre **somente** quando o médico aciona explicitamente "Renovar esta receita" (gera `ReceitaRenovada`). Criação de nova receita do mesmo medicamento sem clicar em "Renovar" produz registros independentes. **Cancelamento é irreversível** (sem janela de undo).
  - *Justificativa*: status único + categoria evita explosão combinatória de estados; vinculação explícita evita falso positivo por homonímia/mudança de dose; irreversibilidade alinha com risco real (farmácia já pode ter dispensado).

- **Q4** (cadência de alerta) → **A: Opção A** — (4a) receita cadastrada com validade restante < checkpoint → o checkpoint passado é marcado `skipped` em audit (sem disparo retroativo). (4b) Desabilitar alerta permitido **apenas** para `comum`; `especial`/`controlada` têm alerta obrigatório. (4c) Cadência é **cancelada imediatamente** em `PrescricaoCancelada` ou `ReceitaRenovada`. (4d) Múltiplas receitas vencendo: envios **separados por receita** com **debounce de 4h** entre alertas ao mesmo destinatário; **sem agrupamento** em mensagem única.
  - *Justificativa*: skip evita disparo de "vencimento em 15d" para receita criada com 3d restantes (ruído); envios separados preservam clareza (paciente entende qual receita renovar); debounce protege contra políticas anti-spam Meta.

- **Q5** (payload da IA) → **A: Opção A** — Payload contém exatamente: `patient_id`, `prescription_id`, `professional_id`, `professional_name`, `days_until_expiry`, `prescription_type` ∈ `{comum|especial|controlada}`, `default_appointment_type_id`. **Nome do medicamento e posologia nunca saem no payload**, **mesmo para receita comum**. IA refere-se sempre como "sua receita" ou "sua receita de uso contínuo". Sem pseudonimização condicional — postura uniforme.
  - *Justificativa*: postura conservadora elimina uma classe inteira de bug de PII clínica e simplifica os guardrails da IA (regra única: "nunca falar nome de medicamento"); se houver necessidade futura (médicos pedem mais contexto na conversa), a Fase IA pode propor expansão controlada com gate de tipo.

- **Q6** (relatório) → **A: Opção A** — (6a) "Renovada" é definida **exclusivamente** pela cadeia `previous_prescription_id` (sem heurísticas). (6b) "A vencer" tem janela default de **30 dias**, com presets de UI `{7, 15, 30, 60}` dias. (6c) Filtros MVP: `status`, `tipo`, `professional_id`, `patient_id`, `faixa de vencimento` — combinados em interseção AND. (6d) CSV inclui `medicamento` e `posologia` **apenas das receitas que o exportador tem permissão de visualizar conteúdo** (Atendente sem `prescription.view_controlled` recebe CSV com linhas de controladas mascaradas/omitidas — vide Q8). Cabeçalho do CSV inclui marca de confidencialidade quando contém ≥1 controlada.
  - *Justificativa*: definição precisa de "renovada" evita falsos positivos no relatório de conformidade; filtros casam com o que clínica precisa em auditoria; CSV alinhado a abilities evita exfiltração via export.

- **Q7** (PDF) → **A: Opção A** — (7a) PDF é **anexável depois** do cadastro inicial (operação separada com endpoint próprio). (7b) PDF é **substituível**, mas substituição **versiona** — versões antigas ficam em soft-delete e são auditadas; histórico de versões fica acessível a Admin Clínica. (7c) Limite **10 MB** confirmado (alinhado ao PRD e RF-049).
  - *Justificativa*: anexar depois cobre fluxo real (médico salva e depois sobe PDF assinado fisicamente); versionamento preserva trilha clínica e regulatória (substituir sem histórico é violação de auditabilidade — Princípio III); 10MB cobre receita digitalizada com qualidade.

- **Q8** (acesso a controladas) → **A: Opção A** — (8a) Atendente/Recepcionista **vê linha mascarada** "Receita controlada — acesso restrito" em listas e timeline (não omitida — precisa saber que existe para coordenação operacional). Conteúdo clínico (medicamento, posologia, observações) **não é renderizado** no payload da resposta. (8b) Médico **de outra especialidade** no mesmo tenant **também vê mascarado** — apenas o médico emissor e Admin Clínica com `prescription.view_controlled` veem o conteúdo. (8c) Log de visualização registra o **evento** (`actor_user_id`, `prescription_id`, `viewed_at`, `ip`, `user_agent`) — **não** armazena snapshot dos campos renderizados.
  - *Justificativa*: princípio da menor exposição mesmo entre médicos; mascarada (vs. oculta) preserva visibilidade operacional sem vazar dado sensível; log de evento (não snapshot) cobre auditoria sem inflar storage.

- **Q9** (posologia) → **A: Opção A** — Posologia é armazenada como **texto livre** (`posologia` text) nesta fase. Campos estruturados (`frequencia_doses_dia`, `via_administracao`, `duracao_dias`) **não** são introduzidos agora — ficam para fase futura como **campos opcionais nullable** (sem breaking change).
  - *Justificativa*: texto livre cobre 100% dos casos clínicos e zero atrito para o médico (que prescreve naturalmente em frases); estrutura prematura traria campos sub-preenchidos e relatório inviável; quando relatório por substância/dose for prioridade de produto, campos estruturados podem ser adicionados sem migration disruptiva.

- **Q10** (receita ↔ consulta) → **A: Opção A** — (10a) `appointment_id` é **nullable** — receita pode ser criada sem consulta vinculada (importação histórica, prescrição emergencial, telemedicina fora da agenda). (10b) Se vinculada, a consulta pode estar em **qualquer status** (`scheduled`, `in_progress`, `completed`) — o médico pode prescrever durante a consulta em andamento. (10c) Importação histórica futura (Q12) usa `appointment_id = null` sem restrição adicional.
  - *Justificativa*: realidade clínica não exige consulta agendada para toda receita (renovação simples, paciente conhecido, telemedicina informal); nullable simplifica o modelo e habilita Q12.

- **Q11** (timeline do paciente) → **A: Opção A** — (11a) Eventos visíveis na timeline: **apenas `PrescricaoCriada`, `PrescricaoCancelada`, `ReceitaRenovada`** — eventos materiais ao histórico clínico. Alertas (`ReceitaProximaDoVencimento`, `ReceitaVencida`) **não poluem** a timeline (3 itens por receita seria ruído). (11b) Médico **não-emissor** vê item da timeline; o conteúdo é mascarado conforme Q8 (apenas se for receita controlada). Para receitas comum/especial, conteúdo é visível a qualquer médico do tenant. (11c) Receita cancelada **permanece** na timeline com badge "Cancelada — {categoria}" (Q3) — auditabilidade e transparência clínica.
  - *Justificativa*: 3 eventos materiais cobrem o ciclo legal sem ruído; manter cancelada visível preserva histórico ("essa receita foi emitida e depois retirada por erro/desistência"); alinhamento com Q8 mantém regra única de mascaramento.

- **Q12** (importação em massa) → **A: Opção A** — (12a) **Fora do escopo** desta fase (mantém §6). (12b) Modelo MUST carregar 3 campos opcionais nullable para suportar import futuro sem breaking change: `imported_at` (timestamp), `imported_source` (text — sistema de origem do legado), `historical_external_id` (text — chave da receita no sistema legado, idempotência de import). `professional_id` permanece NOT NULL — importações sem médico identificado caem em "Profissional Genérico — Histórico" criado por seed.
  - *Justificativa*: feature de import requer UX/UI dedicada + validação em batch — fora do tempo desta fase; mas modelo preparado evita migration dolorosa quando feature for priorizada; `professional_id` NOT NULL preserva integridade referencial.

- **Q13** (notificação ao médico) → **A: Opção A** — (13a) Após `RenovacaoSolicitadaPelaIA`, o médico emissor recebe **item de tarefa na Inbox interna** (Fase 3) — assunto "Renovação agendada pela IA — paciente {nome}" + link para a consulta + link para a receita original. **Sem** e-mail/push nesta fase. (13b) **Sem opt-out individual** nesta fase — todas as renovações via IA geram tarefa na inbox. Opt-out por médico fica para fase futura quando volume justificar.
  - *Justificativa*: reaproveita Inbox da Fase 3 (zero novo canal); e-mail/push exigiriam infra de notificação adicional fora do escopo; sem opt-out na v1 simplifica modelo e a frequência típica (poucas renovações/dia/médico) não justifica throttling.

---

## 0. Contexto e Visão Geral

A Fase 7 entrega o módulo de **Gestão de Receituários** do CRM Médico SaaS. É o primeiro módulo que **gerencia ciclo de vida de documento clínico-regulatório** (validade legal, retenção em farmácia, restrição de acesso por tipo) e o primeiro com **distinções regulatórias explícitas no domínio** (comum vs. especial vs. controlada — três regimes de comportamento distintos).

O módulo:

1. Permite ao médico **cadastrar** uma receita vinculada a um paciente, com posologia, validade e tipo (US-8.1).
2. **Alerta proativamente** o paciente da proximidade do vencimento via canal preferido (US-8.2).
3. **Habilita a IA** (entregue em fase posterior — ver §10 Dependências) a oferecer agendamento de renovação na conversa (US-8.3).
4. Provê **relatório** de conformidade e ciclo de renovação (US-8.4).

Esta fase **NÃO** gera receita eletrônica (sem assinatura digital, sem integração CFM, sem QR code regulatório); **NÃO** valida farmacologicamente; **NÃO** integra com farmácias; **NÃO** implementa prescrição clínica por IA (vedado pela Constituição, Princípio I).

### Encaixe na Plataforma

| Fase precedente | O que o Receituário consome | Onde está formalizado |
|---|---|---|
| 001 Fundação | `tenants`, `users`, `audit_logs`, abilities Spatie, BelongsToTenant | `specs/001-fundacao-multitenant/data-model.md` |
| 002 CRM Pacientes | `patients`, timeline do paciente (consome `PrescricaoCriada`) | `specs/002-crm-pacientes/data-model.md` |
| 003 Omnichannel Inbox | Serviço de mensageria (envio de alerta), templates HSM, janela 24h | `specs/003-omnichannel-inbox/spec.md` |
| 005 Agendamento | `appointments`, vinculação `prescription_id` (consome `RenovacaoSolicitadaPelaIA`) | `specs/005-agendamento-consultas/data-model.md` |

| Fase posterior (forward contract) | O que o Receituário expõe | Status |
|---|---|---|
| IA Matricial (futura) | Evento `ReceitaProximaDoVencimento` + payload de contexto sem PII clínica | Spec ainda **não existe** — Fase 7 publica o contrato |
| Retornos (futura) | Domínio **disjunto** — cadência própria, não reutiliza `return_cadences` | Spec ainda **não existe** |

> **NOTA DE INTEGRIDADE**: O briefing original referenciou `specs/004-ia-matricial/` e `specs/006-retornos/` como fontes. Esses artefatos **não existem no repositório nesta data**. A Fase 4 entregue foi `004-token-auth-migration` e a Fase 6 entregue foi `006-agenda-ux-polish`. Esta especificação trata IA Matricial e Retornos como **fases futuras**; os contratos forward-looking aqui descritos passam a ser **a fonte de verdade** quando aquelas specs forem criadas.

---

## 1. User Scenarios & Testing *(mandatory)*

### User Story 1 — Cadastro de Receituário (Prioridade: P1) `US-8.1`

**Como** Médico
**Quero** cadastrar uma receita vinculada a um paciente após (ou durante) uma consulta
**Para que** o sistema gerencie validade, alerte vencimento e habilite renovação assistida

**Por que P1**: é a **fundação operacional** do módulo. Sem cadastro, US-8.2/8.3/8.4 não têm dados sobre os quais operar. Toda a auditoria regulatória, a timeline do paciente e o relatório dependem desta US.

**Independent Test**: pode ser entregue isoladamente — médico cadastra receita, vê na timeline do paciente, recupera por filtro. Não exige US-8.2, US-8.3 ou US-8.4.

**Acceptance Scenarios**:

- **AC-8.1.1** 🔴 **Given** estou autenticado como Médico com `prescription.create` e um paciente do meu tenant existe, **When** submeto uma receita comum com pelo menos um medicamento, posologia, data de emissão (hoje) e validade dentro do limite legal/configurado, **Then** o sistema persiste a receita tenant-scoped, vincula a `professional_id = eu`, registra `PrescricaoCriada` em audit_logs e emite evento de domínio `PrescricaoCriada` consumível pela Fase 2 (timeline).

- **AC-8.1.2** 🔴 **Given** estou cadastrando uma receita do tipo **especial** ou **controlada**, **When** o sistema renderiza o formulário de validade, **Then** o campo é **bloqueado em 30 dias** a partir da data de emissão, sem opção de override pelo médico, conforme Portaria SVS/MS nº 344/1998 (vide §11 Implicações Regulatórias). Tentativa de POST direto com validade ≠ 30d retorna erro de validação.

- **AC-8.1.3** 🔴 **Given** estou cadastrando uma receita **comum**, **When** o formulário renderiza o seletor de validade, **Then** vejo um seletor de **duração** com preset `{30, 60, 90, 180}` dias (radio/select — sem date picker livre). O sistema persiste `expires_at = issued_at + duração_selecionada`. Tentativa de POST direto com `duração ∉ {30,60,90,180}` ou `expires_at` manipulado retorna erro de validação server-side. (Decisão Q1 — vide §Clarifications.)

- **AC-8.1.4** 🟡 **Given** anexei um PDF de até 10 MB durante o cadastro, **When** confirmo o salvamento, **Then** os campos textuais são persistidos **imediatamente** (não bloqueio por upload) e o PDF é enviado de forma assíncrona ao storage S3-compatível reutilizado da Fase 3; a UI mostra progresso de upload. Falha de upload **não invalida** a receita — fica em estado "PDF pendente" reprocessável. PDF também pode ser anexado **depois** do cadastro inicial via operação separada; substituições preservam histórico de versões (versões antigas em soft-delete + audit) — Q7.

- **AC-8.1.5** 🔴 **Given** estou autenticado com perfil **Atendente** ou **Recepcionista** (sem `prescription.create`), **When** acesso o endpoint de criação de receita, **Then** o sistema retorna 403 e registra tentativa em audit_logs.

- **AC-8.1.6** 🟡 **Given** acabei de cadastrar uma receita do tipo **controlada**, **When** outro médico do mesmo tenant (qualquer especialidade) ou Atendente/Recepcionista abre a ficha do paciente, **Then** o item aparece como **linha mascarada** "Receita controlada — acesso restrito ao emissor e Admin Clínica" (presente mas com conteúdo clínico omitido no payload da resposta). Apenas o médico emissor e Admin Clínica com `prescription.view_controlled` veem o conteúdo. (Q8.)

- **AC-8.1.7** 🟡 **Given** uma receita está cadastrada, **When** o emissor original tenta editá-la, **Then** a edição é permitida apenas para campos não-regulatórios (texto de observações livres e anexação/substituição de PDF — Q7). Validade, tipo, medicamentos e posologia são **imutáveis após save** — correção exige cancelamento (categoria `erro_emissao`) e nova receita (Q3). Toda alteração grava em audit_logs com `PrescricaoAtualizada`.

- **AC-8.1.8** 🔴 **Given** o médico emissor (ou Admin Clínica) cancela a receita, **When** o cancelamento é submetido com `cancellation_reason_category` ∈ `{erro_emissao, desistencia_paciente, substituicao, outro}` + texto livre obrigatório (≤500 chars), **Then** a receita transita para status `cancelada`, grava `PrescricaoCancelada` em audit_logs com categoria + texto, **interrompe cadência de alerta** (US-8.2) imediatamente e o evento `PrescricaoCancelada` é emitido. Cancelamento **é irreversível** — sem janela de undo (Q3).

- **AC-8.1.9** 🟡 **Given** estou cadastrando receita do tipo **controlada**, **When** abro o formulário, **Then** a UI exibe aviso visível "Receita Amarela — Notificação de Receita — sujeita à Portaria SVS/MS nº 344/1998. Toda visualização será registrada."

- **AC-8.1.10** 🟢 **Given** acabei de cadastrar uma receita, **When** abro a timeline do paciente (Fase 2), **Then** vejo o item ordenado cronologicamente com tipo, data de emissão, validade e badge de status (vigente/vencida/cancelada/renovada). Timeline mostra apenas eventos materiais — `PrescricaoCriada`, `PrescricaoCancelada`, `ReceitaRenovada` — não alerta de vencimento (Q11). Receita cancelada **permanece visível** com badge "Cancelada — {categoria}".

**Dependências**: 001 (tenant, users, audit, abilities), 002 (patient + timeline consumer), 003 (storage S3 reutilizado).

**Riscos**:
- R1.1 — Override de validade legal por médico mal-intencionado (mitigado em AC-8.1.2: campo bloqueado server-side, não apenas client-side).
- R1.2 — PDF >10 MB derruba salvamento (mitigado em AC-8.1.4: textuais persistidos antes do upload).
- R1.3 — Atendente exfiltrar dado de controlada via listagem (mitigado em AC-8.1.6: filtro no nível de query, não apenas de UI).
- R1.4 — Médico edita medicamento após farmácia já ter dispensado (mitigado em AC-8.1.7: campos clínicos imutáveis).

**Clarifications aplicadas**: Q1 (validade comum), Q2 (estrutura medicamentos), Q3 (cancelamento), Q7 (PDF), Q8 (acesso controlada), Q9 (posologia), Q10 (vínculo consulta).

---

### User Story 2 — Alerta de Vencimento de Receita (Prioridade: P1) `US-8.2`

**Como** Plataforma (sistema)
**Quero** alertar proativamente o paciente nos intervalos de 15, 7 e 1 dia antes do vencimento
**Para que** o paciente renove antes de ficar sem medicação e a clínica capture a oportunidade de retorno

**Por que P1**: é o **gatilho de receita recorrente** da clínica e o gancho que habilita US-8.3 (a IA só age porque recebe o evento gerado aqui). Sem isso, US-8.1 é apenas um repositório passivo.

**Independent Test**: pode ser entregue após US-8.1 e validada com mocks de canal de envio — basta avançar relógio do sistema e observar evento + chamada ao serviço de mensageria (Fase 3) com payload correto.

**Acceptance Scenarios**:

- **AC-8.2.1** 🔴 **Given** uma receita comum vigente com validade em D+15, D+7 e D+1, **When** o relógio do sistema atinge cada checkpoint, **Then** o sistema emite `ReceitaProximaDoVencimento{prescription_id, patient_id, days_until_expiry, alert_step}` exatamente uma vez por checkpoint (idempotência por `(prescription_id, alert_step)`) e despacha mensagem via serviço de mensageria da Fase 3.

- **AC-8.2.2** 🔴 **Given** uma receita do tipo **especial** ou **controlada**, **When** o Admin Clínica ou Médico tenta desabilitar os alertas para essa receita, **Then** o sistema **rejeita** — para esses tipos, alerta de vencimento é **obrigatório** (vide §11). Desabilitação é permitida apenas para tipo **comum**.

- **AC-8.2.3** 🔴 **Given** uma receita foi **cancelada** OU **renovada** (existe receita filha com `previous_prescription_id` apontando para ela) antes de um checkpoint, **When** o relógio atinge o checkpoint pendente, **Then** **nenhum alerta** é disparado. A cadência é **interrompida** imediatamente no evento `PrescricaoCancelada` ou `ReceitaRenovada`.

- **AC-8.2.4** 🟡 **Given** receita comum foi cadastrada com 5 dias de validade restante (entre D+1 e D+7), **When** o sistema avalia a cadência no momento do cadastro, **Then** apenas o alerta de D+1 é agendado; os checkpoints D+15 e D+7 já passados são marcados `skipped` em audit (sem disparo retroativo) — Q4a.

- **AC-8.2.5** 🟡 **Given** o canal preferido do paciente é WhatsApp e a janela de 24h **está fechada** no momento do checkpoint, **When** o alerta precisa sair, **Then** o serviço de mensageria da Fase 3 usa um **template HSM aprovado** dedicado a vencimento de receita (não envia texto livre). Se nenhum template estiver aprovado/disponível no tenant, o alerta vai para Inbox como tarefa manual com motivo "template ausente" (não falha silenciosamente).

- **AC-8.2.6** 🟡 **Given** o paciente tem **3 receitas vencendo na mesma semana**, **When** o alerta é despachado, **Then** o sistema envia **mensagens separadas por receita** com debounce mínimo de 4h entre envios ao mesmo destinatário (sem agrupamento em mensagem única) — preserva clareza ("qual receita renovar") e respeita políticas anti-spam Meta. Q4d.

- **AC-8.2.7** 🟢 **Given** uma receita ainda vigente atingiu seu `expires_at`, **When** o relógio cruza a data de vencimento, **Then** o sistema emite `ReceitaVencida{prescription_id, patient_id, expired_at}` exatamente uma vez (idempotência) e atualiza status visual da receita para "vencida" no relatório (US-8.4) e na timeline.

- **AC-8.2.8** 🟡 **Given** o paciente **opt-out de marketing/lembretes** está marcado em `patients.communication_preferences` (Fase 2), **When** o checkpoint dispara, **Then** o alerta **não é enviado** mas o evento `ReceitaProximaDoVencimento` ainda é emitido internamente (consumido por IA e relatório). O motivo `recipient_opted_out` é registrado em audit_logs.

- **AC-8.2.9** 🔴 **Given** o payload do evento `ReceitaProximaDoVencimento`, **When** for consumido pela IA (Fase futura), **Then** o payload **NÃO contém** nome do medicamento, posologia, nem indicação clínica — apenas referências (`prescription_id`, `patient_id`, `days_until_expiry`, `professional_id`, `prescription_type` em granularidade [comum|especial|controlada] sem revelar a substância). Conformidade LGPD e Princípio I da Constituição.

**Dependências**: US-8.1 (precisa existir receita), 003 Omnichannel (serviço de mensageria + templates HSM), 002 (preferências do paciente).

**Riscos**:
- R2.1 — Alerta duplicado por race entre dois workers (mitigado por AC-8.2.1: idempotência `(prescription_id, alert_step)` em nível de DB).
- R2.2 — Alerta para receita renovada/cancelada cria atrito com paciente (mitigado por AC-8.2.3: cadência interrompida imediatamente).
- R2.3 — Pacote do template HSM contém PII clínica vazada via parâmetros — mitigar revisando textos de template antes da aprovação Meta (vai para `/speckit-plan` como ação obrigatória).
- R2.4 — Saturação do canal por múltiplas receitas (mitigado por AC-8.2.6: debounce).

**Clarifications aplicadas**: Q4 (cadência completa — skip, opt-out por tipo, interrupção, debounce).

---

### User Story 3 — Renovação via IA no Chat (Prioridade: P2) `US-8.3`

**Como** Paciente
**Quero** que a IA, quando minha receita estiver perto de vencer, me ofereça naturalmente agendar uma consulta de renovação
**Para que** eu não precise ligar nem ficar sem medicação

**Por que P2**: depende de IA Matricial (fase futura ainda não construída). Esta US **publica os contratos** necessários — o consumo pela IA é trabalho daquela fase. Tem valor real apenas quando IA estiver implementada, mas o contrato precisa estar pronto antes para que a Fase 7 não vire dívida técnica.

**Independent Test**: pode ser testada com **stub de IA** — receita cadastrada, sistema dispara `ReceitaProximaDoVencimento`, stub responde simulando "paciente aceitou agendar" e publica `RenovacaoSolicitadaPelaIA`; observa-se vinculação `appointment.prescription_id` na Fase 5.

**Acceptance Scenarios**:

- **AC-8.3.1** 🔴 **Given** o evento `ReceitaProximaDoVencimento` é emitido por US-8.2, **When** a Fase 7 monta o contexto a ser entregue à IA, **Then** o payload contém **exatamente** estes 7 campos: `patient_id`, `prescription_id`, `professional_id`, `professional_name`, `days_until_expiry`, `prescription_type` ∈ `{comum|especial|controlada}`, `default_appointment_type_id`. **Nome do medicamento e posologia nunca saem no payload — mesmo para receita comum** (Q5). IA refere-se sempre como "sua receita".

- **AC-8.3.2** 🔴 **Given** a IA (fase futura) conclui com o paciente que ele aceita renovar, **When** o motor agenda a consulta via API da Fase 5, **Then** o `Appointment.prescription_id` é populado e o evento `RenovacaoSolicitadaPelaIA{prescription_id, patient_id, professional_id, appointment_id}` é emitido por esta fase (não pela IA — a IA dispara o cadastro de consulta, esta fase observa a vinculação e emite o evento).

- **AC-8.3.3** 🟡 **Given** a IA mantém conversa sobre renovação, **When** o paciente pergunta detalhes clínicos ("posso aumentar a dose?", "esse remédio é fraco?"), **Then** a IA responde com mensagem padronizada de redirecionamento "Apenas o(a) Dr(a). {nome} pode te orientar sobre isso. Posso agendar a consulta de renovação para você?" — em conformidade com Princípio I da Constituição e RF-033. Esta fase **define o guardrail** mas o cumprimento técnico é da fase IA.

- **AC-8.3.4** 🟡 **Given** **qualquer tipo de receita** (comum, especial ou controlada), **When** a IA inicia conversa de renovação, **Then** a IA **nunca menciona o nome do medicamento nem a posologia** — refere-se apenas como "sua receita" (Q5). Regra única simplifica os guardrails e elimina classe inteira de bug de PII clínica em compartilhamento de tela.

- **AC-8.3.5** 🟢 **Given** a IA agendou consulta de renovação, **When** o médico abre a consulta na agenda, **Then** vê marcador "Renovação de receita — ref. Receita #N de DD/MM/AAAA" e link direto para a receita original (sujeito a abilities — se for controlada de outro médico, mostra apenas marcador anônimo).

- **AC-8.3.6** 🟡 **Given** a receita é cancelada ou renovada manualmente pelo médico **antes** da IA fechar o agendamento, **When** a IA tenta agendar, **Then** a Fase 5 rejeita com `prescription_not_eligible_for_renewal` (porque a receita não está mais vigente). A IA encerra a conversa com mensagem cordial e o evento `RenovacaoSolicitadaPelaIA` **não é emitido**.

- **AC-8.3.7** 🟡 **Given** a IA agendou consulta de renovação, **When** o evento `RenovacaoSolicitadaPelaIA` é emitido, **Then** o médico emissor recebe **item de tarefa na Inbox interna** da Fase 3, com assunto "Renovação agendada pela IA — paciente {nome}" + link para a consulta e para a receita original. Sem e-mail/push nesta fase. Sem opt-out por médico nesta fase (Q13).

**Dependências**: US-8.1, US-8.2, Fase 5 (precisa expor `appointment.prescription_id` — esta fase requer atualização contratual da 5), IA Matricial (futura).

**Riscos**:
- R3.1 — Vazamento de PII clínica para o LLM (mitigado por AC-8.3.1: payload sem campos clínicos).
- R3.2 — IA dá orientação clínica em violação ao Princípio I (mitigado por AC-8.3.3: guardrail definido aqui, enforçado lá).
- R3.3 — Race entre cancelamento manual e agendamento da IA (mitigado por AC-8.3.6: validação na Fase 5 no momento do agendamento, não no momento do oferecimento).
- R3.4 — `appointment.prescription_id` não existe em produção da Fase 5 → esta fase **demanda migration de schema** na agenda (ver §3 FRs e §10 Dependências).

**Clarifications aplicadas**: Q5 (payload IA), Q10 (vínculo agenda), Q13 (notificação ao médico).

---

### User Story 4 — Relatório de Receitas (Prioridade: P2) `US-8.4`

**Como** Médico ou Admin Clínica
**Quero** consultar e exportar a base de receitas filtrada por status, tipo, profissional, paciente e janela temporal
**Para que** eu acompanhe conformidade regulatória, identifique oportunidade de retorno e responda a auditorias

**Por que P2**: viabilizadora de operação madura. Sem ela, a clínica não tem visão consolidada para auditoria regulatória (Portaria 344/98 exige rastreabilidade). Não bloqueia operação básica mas é exigência regulatória de médio prazo.

**Independent Test**: pode ser entregue após US-8.1 (não depende de US-8.2/8.3 — alimenta-se apenas do estado da receita).

**Acceptance Scenarios**:

- **AC-8.4.1** 🔴 **Given** estou autenticado com `prescription.view` e tenho receitas no tenant, **When** abro o relatório sem filtros, **Then** vejo a lista das receitas do meu tenant paginada, com status visual (vigente/a vencer/vencida/cancelada/renovada), tipo, data de emissão, validade e indicador de criticidade por cor (verde/amarelo/vermelho conforme proximidade do vencimento).

- **AC-8.4.2** 🔴 **Given** estou no perfil **Atendente**, **Recepcionista** ou **Médico de outra especialidade** (sem `prescription.view_controlled` ou não-emissor), **When** o relatório me é entregue, **Then** receitas **controladas** aparecem como **linha mascarada** ("Receita controlada — acesso restrito") com `tipo`, `data_emissao`, `validade`, `status` visíveis mas **medicamento, posologia e observações omitidos** do payload da resposta (Q8a/8b).

- **AC-8.4.3** 🔴 **Given** os filtros suportados, **When** aplico combinação válida (status + tipo + profissional + paciente + faixa de vencimento), **Then** o resultado reflete a interseção (AND) com paginação determinística e ordenação por validade ascendente por default.

- **AC-8.4.4** 🟡 **Given** uma receita vencida sem renovação ligada (`renewed_by_prescription_id IS NULL` E `expires_at < now()`), **When** filtro por status="Vencida", **Then** ela aparece. "Renovada" significa **exclusivamente**: existe outra receita filha cujo `previous_prescription_id` aponta para esta (cadeia explícita criada por ação "Renovar" — Q3/Q6a), independentemente de ter havido consulta no meio.

- **AC-8.4.5** 🟡 **Given** estou autenticado com `prescription.export`, **When** clico em "Exportar CSV", **Then** o sistema gera CSV com receitas **respeitando filtros aplicados E nível de acesso do exportador**: linhas de receitas controladas trazem `medicamento` + `posologia` somente se o exportador tem `prescription.view_controlled`; caso contrário, essas linhas saem mascaradas (mesmas colunas mas células sensíveis em branco) com marca de redação. Comum/especial saem completas. CSV grava entrada em audit_logs com lista de IDs exportados (não os conteúdos). Q6d.

- **AC-8.4.6** 🟡 **Given** o relatório exibe número grande de linhas (>5.000 numa janela), **When** o usuário aplica filtro, **Then** o p95 de tempo de resposta é ≤ 1,5s para a primeira página (Princípio II — performance).

- **AC-8.4.7** 🟢 **Given** export CSV de receitas controladas foi gerado, **When** o arquivo é baixado, **Then** o nome do arquivo contém prefixo `CONFIDENCIAL_` e o cabeçalho do CSV inclui linha de aviso "Documento confidencial — Portaria SVS/MS 344/98 — acesso registrado em audit_logs".

- **AC-8.4.8** 🟡 **Given** filtro de janela temporal "A vencer", **When** sem parâmetro adicional, **Then** o default são receitas com `expires_at` entre hoje e hoje+30d. Ajustável pelos presets `{7, 15, 30, 60}` dias via UI (Q6b).

- **AC-8.4.9** 🔴 **Given** cross-tenant — tenant A consulta receita do tenant B via manipulação de parâmetro de URL, **When** a tentativa chega ao backend, **Then** retorna 404 (não 403, para não vazar existência). Audit_log registra `cross_tenant_attempt`.

**Dependências**: US-8.1 (precisa ter dados), 001 (audit_logs).

**Riscos**:
- R4.1 — Atendente baixando CSV com posologia de controlada (mitigado por AC-8.4.5: filtro também na exportação).
- R4.2 — Performance degradando em tenants grandes (mitigado por AC-8.4.6: p95 ≤ 1,5s — exige índices definidos no plano).
- R4.3 — Vazamento cross-tenant via filtro `professional_id` de outro tenant (mitigado por AC-8.4.9 + BelongsToTenant herdado).

**Clarifications aplicadas**: Q6 (definição de renovada, janela default, filtros, CSV), Q8 (mascaramento de controladas).

---

### Edge Cases (cross-cutting às 4 US)

- **Receita criada por médico que foi posteriormente desativado** (Fase 2 reatribui pacientes — esta fase deve manter receita visível e auditável; emissor original permanece imutável; alertas continuam disparando).
- **Receita criada antes da inbox/canal estar ativo no tenant** (alerta cai em Inbox como tarefa manual — não pode ser perdido).
- **Receita criada em fuso horário do profissional ≠ fuso do tenant** (vencimento é calculado no fuso de emissão do profissional — herdar pattern da Fase 5: `professionals.timezone` override).
- **PDF é uploadado depois do salvamento textual** (operação separada — exige nova versão de evento `PrescricaoAtualizada` com `changed_fields=["pdf_attachment"]`). Substituições preservam histórico de versões (Q7).
- **Sistema avança o relógio (testes / DST)** — todos os checkpoints são calculados em UTC; conversão para timezone do paciente somente no momento do envio do texto.
- **Tenant que muda de plano e perde acesso ao módulo de receituário** — receitas existentes permanecem acessíveis somente em modo leitura; novos cadastros bloqueados via gate de plano (Princípio VIII — Sustentabilidade).
- **Migração futura** — receitas históricas importadas devem ter `imported_at`, `imported_source`, `historical_external_id` (Q12). `professional_id` permanece NOT NULL — emissor histórico desconhecido cai em "Profissional Genérico — Histórico" criado por seed.

---

## 2. Requirements *(mandatory)*

### Functional Requirements

#### Cadastro (US-8.1)

- **FR-001**: Sistema MUST permitir cadastro de receita com no mínimo: `patient_id`, `professional_id` (do médico autenticado), `type` (enum: `comum`, `especial`, `controlada`), `issued_at`, `expires_at`, lista de medicamentos com **1-10 itens** para `comum`/`especial` e **exatamente 1 item** para `controlada` (Q2 + Portaria 344/98).
- **FR-002**: Sistema MUST bloquear o campo `expires_at` em `issued_at + 30d` quando `type` ∈ {`especial`, `controlada`} — validação **server-side**, não apenas UI.
- **FR-003**: Sistema MUST oferecer ao médico, para `type = comum`, um seletor de **duração** com preset `{30, 60, 90, 180}` dias. `expires_at` é computado server-side como `issued_at + duração`. Sistema MUST recusar qualquer outro valor de duração ou `expires_at` posterior a `issued_at + 180d`. (Decisão Q1.)
- **FR-004**: Sistema MUST aceitar upload de PDF de até 10 MB, persistir os campos textuais antes do upload, e retentar o upload de forma assíncrona em caso de falha.
- **FR-005**: Sistema MUST registrar em `audit_logs`: `PrescricaoCriada`, `PrescricaoAtualizada`, `PrescricaoCancelada`, `PrescricaoVisualizada` (este último APENAS para `type = controlada`).
- **FR-006**: Sistema MUST emitir evento de domínio `PrescricaoCriada` consumível pela Fase 2 (timeline).
- **FR-007**: Sistema MUST impedir edição de `type`, `medicamentos[]`, `posologia`, `expires_at` após o save inicial — exige cancelamento e nova receita.
- **FR-008**: Sistema MUST permitir cancelamento por emissor original ou Admin Clínica, com motivo obrigatório (texto livre ≤500 chars). Cancelamento é **irreversível**.
- **FR-009**: Sistema MUST armazenar cada medicamento como item da lista da receita, com os campos: `nome` (texto livre), `concentracao`, `forma_farmaceutica`, `posologia` (estrutura definida em Q9), `quantidade`, `duracao_tratamento`. Sistema MUST oferecer autocomplete do `nome` baseado no histórico de prescrições do próprio médico no tenant — sem dependência de catálogo externo. (Q2.)

#### Alerta (US-8.2)

- **FR-010**: Sistema MUST agendar checkpoints de alerta nos intervalos D+15, D+7, D+1 antes de `expires_at` para receitas vigentes.
- **FR-011**: Sistema MUST emitir evento `ReceitaProximaDoVencimento` exatamente uma vez por `(prescription_id, alert_step)` — idempotência garantida em nível de persistência.
- **FR-012**: Sistema MUST cancelar checkpoints pendentes quando a receita transitar para `cancelada` ou `renovada`.
- **FR-013**: Sistema MUST emitir evento `ReceitaVencida` exatamente uma vez quando `expires_at` for cruzado e a receita não estiver `cancelada`/`renovada`.
- **FR-014**: Sistema MUST permitir desabilitar alertas para receitas do tipo `comum` (configuração por receita); MUST recusar desabilitação para `especial` e `controlada`.
- **FR-015**: Sistema MUST integrar com o serviço de mensageria da Fase 3 (não reimplementar canal/janela 24h/HSM); MUST passar template HSM aprovado de "vencimento de receita" como identificador.
- **FR-016**: Sistema MUST aplicar debounce mínimo de 4h entre dois alertas ao mesmo destinatário (anti-spam).
- **FR-017**: Sistema MUST respeitar opt-out de comunicação registrado em `patients.communication_preferences` — alerta interno emitido, envio externo suprimido.

#### Renovação via IA (US-8.3)

- **FR-018**: Sistema MUST exportar payload de contexto para IA contendo **apenas** referências não-clínicas (`patient_id`, `prescription_id`, `professional_id`, `professional_name`, `days_until_expiry`, `prescription_type` em granularidade [comum|especial|controlada], `default_appointment_type_id`). NÃO conter medicamento, posologia, indicação.
- **FR-019**: Sistema MUST emitir `RenovacaoSolicitadaPelaIA` somente após sucesso de agendamento na Fase 5 (consumir webhook/evento da Fase 5 ao detectar `appointment.prescription_id` populado por IA).
- **FR-020**: Sistema MUST requisitar à Fase 5 a adição do campo `appointment.prescription_id` (nullable, FK para prescriptions). Esta fase **publica a especificação contratual** dessa migration — implementação fica na 5 ou em fase de integração.
- **FR-021**: Sistema MUST emitir evento `ReceitaRenovada{old_prescription_id, new_prescription_id}` quando uma nova receita for criada com `previous_prescription_id` apontando para uma existente.

#### Relatório (US-8.4)

- **FR-022**: Sistema MUST oferecer relatório paginado de receitas com filtros: status, tipo, profissional, paciente, faixa de vencimento.
- **FR-023**: Sistema MUST mascarar conteúdo clínico de receitas controladas para usuários sem `prescription.view_controlled` (sem omitir a existência da linha — apenas o conteúdo).
- **FR-024**: Sistema MUST permitir exportação CSV respeitando filtros e nível de acesso do exportador.
- **FR-025**: Sistema MUST registrar em audit_logs toda exportação com lista de `prescription_id` exportados.
- **FR-026**: Sistema MUST atender p95 ≤ 1,5s na primeira página do relatório para tenants com até 50k receitas.
- **FR-027**: Sistema MUST retornar 404 (não 403) em tentativa cross-tenant de leitura de receita — não vazar existência.

#### Cross-cutting

- **FR-028**: Sistema MUST ser tenant-scoped em todas as entidades de receituário (BelongsToTenant herdado da Fase 1).
- **FR-029**: Sistema MUST registrar em audit_logs **toda visualização** de receita do tipo `controlada` (não apenas criação/edição).
- **FR-030**: Sistema MUST aplicar abilities Spatie:
  - `prescription.create` (Médico)
  - `prescription.view` (Médico, Atendente, Recepcionista)
  - `prescription.update` (Médico, restrito ao emissor original)
  - `prescription.cancel` (Médico emissor, Admin Clínica)
  - `prescription.view_controlled` (Médico, Admin Clínica)
  - `prescription.export` (Admin Clínica, Médico)
  - `prescription_alert.configure` (Admin Clínica; Médico para suas próprias receitas)
- **FR-031**: Sistema MUST armazenar PDF no S3-compatível reutilizado da Fase 3, sob disk dedicado `prescriptions` com retenção mínima de 5 anos (CFM Res. 1.821/2007 — prontuário e documentos clínicos).
- **FR-032**: Sistema MUST criptografar PDF em repouso (server-side encryption do S3 + chave per-tenant — Princípio I).
- **FR-033**: Sistema MUST gerar URL assinada com TTL ≤ 10 min para download de PDF e gravar a emissão da URL em audit_logs.

### Non-Functional Requirements

- **NFR-001**: p95 de criação de receita ≤ 800ms (excluindo upload PDF, que é async).
- **NFR-002**: p95 de listagem do relatório ≤ 1,5s para tenants com até 50k receitas.
- **NFR-003**: Backup do storage de PDFs com RPO ≤ 24h e RTO ≤ 4h.
- **NFR-004**: Suporte mínimo de 100 receitas simultâneas/min por tenant em pico (mesmo throughput da Fase 2).
- **NFR-005**: 99,5% de entrega bem-sucedida (alerta gerado → enviado ao serviço de mensageria) em janela de 5 min após o checkpoint.

---

## 3. Key Entities

> Modelagem em nível conceitual — sem decisões de schema concreto (PK, tipo de coluna, índice). Isso vai no `data-model.md` em `/speckit-plan`.

- **Prescription (Receita)**
  Representa um documento clínico-regulatório emitido por um médico para um paciente.
  Atributos lógicos: tenant, paciente, profissional emissor, tipo (enum 3 valores), data de emissão, data de validade, status (vigente/cancelada/renovada/vencida), `cancellation_reason_category` enum + texto (Q3), observações livres do médico, FK **nullable** para `appointment` que gerou a receita (Q10), FK nullable para `previous_prescription` (cadeia de renovações, criada por ação "Renovar" — Q3), opcional PDF attachment, flag `alert_disabled` (apenas para `comum` — Q4b), e os 3 campos opcionais nullable de suporte a importação futura (`imported_at`, `imported_source`, `historical_external_id` — Q12).
  Imutabilidade: `type`, `expires_at`, `medicamentos[]`, `posologia` imutáveis após salvar; observações e PDF editáveis (Q7).

- **PrescriptionMedication (Medicamento da Receita)**
  Cada item da lista de medicamentos. Atributos: `nome` (texto livre), `concentracao`, `forma_farmaceutica`, `posologia` (texto livre — Q9), `quantidade`, `duracao_tratamento`. Cardinalidade: 1-10 por receita `comum`/`especial`; **exatamente 1** por receita `controlada` (Q2 + Portaria 344/98). Autocomplete do `nome` via histórico do médico no tenant.

- **PrescriptionAlertSchedule (Cadência de Alerta)**
  Materializa os 3 checkpoints (D+15, D+7, D+1) por receita. Atributos: `prescription_id`, `alert_step` ∈ {15, 7, 1}, `scheduled_at`, `dispatched_at` nullable, `status` (pending/dispatched/skipped/canceled), `skip_reason` nullable. Garante idempotência via UNIQUE `(prescription_id, alert_step)`. Skip aplicado em checkpoints já passados no momento do cadastro (Q4a).

- **PrescriptionAccessLog (Log de Visualização de Controlada)**
  Especialização de audit_log granular para `type = controlada`. Toda abertura registrada com `actor_user_id`, `prescription_id`, `viewed_at`, `ip`, `user_agent`. Sem snapshot dos campos renderizados (Q8c).

- **PrescriptionPdfAttachment**
  Metadados do PDF: `prescription_id`, `s3_key`, `size_bytes`, `mime_type` (deve ser application/pdf), `uploaded_at`, `uploaded_by`, `version` (incrementa a cada substituição — Q7b), `superseded_at` nullable (timestamp em que a versão foi substituída — soft-delete da versão antiga).

- **PrescriptionEvent (Evento de Domínio — log de envio para outras fases)**
  Trilha auditável de quais eventos foram emitidos por receita e quando — útil para investigação de incidentes. Atributos: `prescription_id`, `event_name`, `payload_hash`, `emitted_at`, `consumer_phase` (`patients`, `inbox`, `ia`, `agenda`).

### Eventos de Domínio Emitidos

| Evento | Payload (campos mínimos) | Consumidor |
|---|---|---|
| `PrescricaoCriada` | `prescription_id, tenant_id, patient_id, professional_id, type, expires_at` | Timeline (Fase 2), Auditoria |
| `PrescricaoAtualizada` | `prescription_id, changed_fields[]` | Timeline (Fase 2), Auditoria |
| `PrescricaoCancelada` | `prescription_id, cancelled_by_user_id, reason, cancelled_at` | Timeline (Fase 2), Cadência (cancela checkpoints) |
| `ReceitaProximaDoVencimento` | `prescription_id, patient_id, professional_id, days_until_expiry, alert_step, prescription_type` | IA (Fase futura), Inbox/Mensageria |
| `ReceitaVencida` | `prescription_id, patient_id, expired_at` | Relatório, Timeline |
| `RenovacaoSolicitadaPelaIA` | `prescription_id, patient_id, professional_id, appointment_id` | Notificação ao médico, Timeline |
| `ReceitaRenovada` | `old_prescription_id, new_prescription_id, renewed_at` | Timeline, Cadência (cancela checkpoints da antiga), Relatório |

---

## 4. Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001** Médico cadastra receita completa em ≤ 90s (medido do clique "Nova Receita" ao "Salvar"). Inclui seleção de paciente, escolha de tipo, preenchimento de ≥1 medicamento e posologia.
- **SC-002** 100% das receitas do tipo `especial` e `controlada` no tenant têm `expires_at - issued_at = 30d` exatos (gate regulatório — ANVISA 344/98).
- **SC-003** ≥ 95% dos alertas de checkpoint (D+15/D+7/D+1) são despachados ao serviço de mensageria em até 5 min do horário programado.
- **SC-004** Zero alertas duplicados por checkpoint em 30 dias de produção (verificado por contagem de `(prescription_id, alert_step)` com `dispatched_at` não nulo > 1).
- **SC-005** Zero violações de cross-tenant detectadas em pen test (tentativa de leitura/edição de receita de outro tenant retorna 404).
- **SC-006** Toda visualização de receita controlada por usuário humano gera entrada em audit_logs (cobertura 100% — auditável regulatoriamente).
- **SC-007** P95 do relatório (US-8.4) ≤ 1,5s para tenants com até 50k receitas.
- **SC-008** Taxa de conversão "alerta de vencimento → consulta de renovação agendada" ≥ 35% após 30 dias com IA Matricial em produção (métrica de negócio, não de software — definida aqui para a Fase futura medir).
- **SC-009** 0% de exposição de medicamento de receita controlada na timeline visível a profissionais não-emissores (verificado por test cross-perfil).
- **SC-010** Taxa de upload bem-sucedido de PDFs ≥ 98% em 24h (com retry async); receita textual nunca bloqueada por falha de upload.

---

## 5. Mapeamento à Constituição

| Princípio | US afetadas | Como esta fase atende |
|---|---|---|
| **I — Conformidade Clínica e Ética** (NON-NEGOTIABLE) | US-8.1, US-8.3 | Imutabilidade de campos clínicos após save (FR-007); payload da IA sem dados clínicos (FR-018); guardrail explícito contra orientação clínica da IA (AC-8.3.3); auditoria 100% de visualização de controladas (FR-029). |
| **II — Multi-tenancy Estrito** (NON-NEGOTIABLE) | US-8.1, US-8.2, US-8.3, US-8.4 | BelongsToTenant em todas as entidades (FR-028); cross-tenant retorna 404 (FR-027, AC-8.4.9); todas as queries scoped. |
| **III — Auditabilidade Total** | US-8.1, US-8.4 | Audit log de criação/edição/cancelamento/visualização/exportação (FR-005, FR-025); audit granular para controladas (FR-029). |
| **IV — Domain Events como Backbone** | Todas | 7 eventos publicados (§3); consumidos por Fase 2 (timeline), Fase 3 (mensageria), Fase 5 (agenda), Fase futura IA. |
| **V — Performance e Escala** | US-8.4 | NFR-002 + SC-007 — p95 ≤ 1,5s no relatório; NFR-001 — p95 ≤ 800ms na criação. |
| **VI — Acessibilidade e UX pt-BR** | US-8.1, US-8.4 | Mensagens de erro em pt-BR; UI obedece padrões da Fase 6 (modal a11y, popover inline em vez de prompt/confirm — herdar dos patterns já consolidados em CLAUDE.md). |
| **VII — Segurança Operacional** (NON-NEGOTIABLE) | US-8.1, US-8.4 | URL S3 assinada com TTL ≤ 10min (FR-033); criptografia em repouso (FR-032); abilities granulares (FR-030); 404 cross-tenant (FR-027). |
| **VIII — Sustentabilidade do Plano SaaS** | Cross-cutting | Receituário é módulo gateable por plano (mesmo padrão da Fase 5 — `tenant.settings.modules.prescriptions.enabled`). |

---

## 6. Out of Scope (não entra na Fase 7)

- Geração eletrônica de receita com assinatura digital ICP-Brasil / CFM (futuro — requer integração com ITI e provedores).
- QR Code regulatório ANVISA (futuro — requer integração com VIGIMED / SNGPC quando publicado).
- Validação farmacêutica (interação medicamentosa, dose máxima, contraindicação) — fora do produto, requer base de medicamentos certificada.
- Integração com farmácia para dispensação / verificação eletrônica.
- Campanhas em massa de renovação (Fase 8 — Campanhas).
- Prontuário eletrônico — Fase 7 trata receita como documento isolado, não vinculado a anamnese/evolução.
- Prescrição clínica gerada pela IA — **vedado** pela Constituição Princípio I e RF-033.
- Telemedicina (vínculo da receita com videoconsulta) — Fase futura.
- Importação em massa de receitas históricas — fora do escopo (Q12); modelo carrega 3 campos opcionais nullable (`imported_at`, `imported_source`, `historical_external_id`) para suporte futuro sem breaking change.

---

## 7. Assumptions

- A1 — Serviço de mensageria da Fase 3 está estável em produção e expõe API documentada para envio de templates HSM com identificador (ex.: `prescription.expiry_warning_15d`).
- A2 — Templates HSM "vencimento de receita" serão aprovados na Meta **antes** do go-live do módulo de alertas — esta especificação não cobre o processo de aprovação, mas o gate é obrigatório.
- A3 — A Fase 5 aceitará adicionar `appointment.prescription_id` em uma migration de schema posterior, coordenada com Fase 7 — sem isso, US-8.3 fica incompleta.
- A4 — A Fase futura "IA Matricial" consumirá os eventos publicados pela Fase 7 sem exigir alterações contratuais retroativas — Fase 7 deve documentar seu contrato como **estável** desde o dia 1.
- A5 — Storage S3-compatível da Fase 3 suporta disk lógico segregado por módulo (`prescriptions/`) com retenção configurável.
- A6 — O modelo dos eventos de domínio segue o pattern Laravel 11+ Event Discovery (descoberto na Fase 5) — listeners são auto-discovered, **não registrar manualmente**.
- A7 — `professionals.timezone` (override sobre `tenants.timezone`) já existe desde a Fase 5 — esta fase reusa para cálculo de `expires_at` no fuso do emissor.
- A8 — A inbox da Fase 3 aceita "tarefa manual" como tipo de item — alertas que não puderem sair via canal automático caem ali sem perda.
- A9 — Receitas **comum** têm validade selecionada via preset de duração `{30, 60, 90, 180}` dias (teto 180d, sem config por tenant nesta fase — Q1); receitas **especial** e **controlada** têm validade FIXA de 30 dias por força regulatória (§11).
- A10 — O usuário do sistema (não o paciente) é sempre o autor do cadastro — não há fluxo de "paciente cadastra receita" no MVP.

---

## 8. Dependências externas e impacto em outras fases

### Esta fase **CONSOME**

- **Fase 1 (Fundação)**: tenants, users, audit_logs, Spatie permissions, BelongsToTenant.
- **Fase 2 (Pacientes)**: `patients` table, timeline (consome `PrescricaoCriada`), `patients.communication_preferences`.
- **Fase 3 (Inbox)**: serviço de mensageria (envio HSM), storage S3, item de tarefa manual.
- **Fase 5 (Agenda)**: vinculação `appointment.prescription_id` (requer migration coordenada).

### Esta fase **EXPÕE**

- **Para Fase 2**: 4 eventos consumidos pela timeline (`PrescricaoCriada`, `PrescricaoCancelada`, `ReceitaRenovada`, opcionalmente `ReceitaProximaDoVencimento`).
- **Para Fase IA Matricial (futura)**: 1 evento (`ReceitaProximaDoVencimento`) + endpoint de contexto sem PII clínica. **Esta é a primeira coisa que aquela fase consome.**
- **Para Fase 5 (Agenda)**: requisito contratual de adicionar `appointment.prescription_id` (FK nullable).
- **Para Fase Retornos (futura)**: nenhuma — domínios disjuntos (cadência de receituário ≠ cadência de retorno).

### Esta fase **REQUER MIGRATION COORDENADA**

- `appointments.prescription_id` (nullable FK) na Fase 5 — sem isso, AC-8.3.2 e AC-8.3.5 não passam.
- Possivelmente extensão de `patients.communication_preferences` se ainda não suportar opt-out granular por tipo de mensagem (verificar na Fase 2 antes do plan).

---

## 9. NEEDS_CLARIFICATION — Histórico (todos resolvidos em 2026-05-17)

> Todos os 13 pontos foram resolvidos em sessão única de `/speckit-clarify` em 2026-05-17. Vide §Clarifications no topo do documento para o registro Q1-Q13 e justificativas. Esta seção fica aqui como **trilha de auditoria** das decisões.

### ✅ [NEEDS CLARIFICATION 1] — Validade de receita comum — **RESOLVIDO em 2026-05-17 (vide §Clarifications, Q1)**
Resposta: presets `{30, 60, 90, 180}` dias; `expires_at = issued_at + duração` server-side; teto fixo 180d; sem config por tenant.

### ✅ [NEEDS CLARIFICATION 2] — Estrutura de medicamentos — **RESOLVIDO em 2026-05-17 (Q2)**
Resposta: lista 1-10 itens para comum/especial; exatamente 1 para controlada. Campos: nome (texto livre + autocomplete histórico), concentração, forma farmacêutica, posologia (Q9), quantidade, duração. 3 medicamentos diferentes = 1 receita com 3 itens.

### ✅ [NEEDS CLARIFICATION 3] — Cancelamento vs. substituição — **RESOLVIDO (Q3)**
Status terminal único `cancelada` + enum `cancellation_reason_category {erro_emissao, desistencia_paciente, substituicao, outro}` + texto livre obrigatório. Vinculação `previous_prescription_id` somente por ação explícita "Renovar". Cancelamento **irreversível**.

### ✅ [NEEDS CLARIFICATION 4] — Cadência de alerta — **RESOLVIDO (Q4)**
Checkpoints passados = `skipped` (sem retro); desabilitar permitido apenas para `comum`; cadência cancelada imediatamente em cancel/renovação; envios separados por receita com debounce 4h, sem agrupamento.

### ✅ [NEEDS CLARIFICATION 5] — Payload da IA na renovação — **RESOLVIDO (Q5)**
7 campos fixos no payload (`patient_id`, `prescription_id`, `professional_id`, `professional_name`, `days_until_expiry`, `prescription_type`, `default_appointment_type_id`). Nome do medicamento e posologia **nunca** saem — mesmo para receita comum. Sem pseudonimização condicional.

### ✅ [NEEDS CLARIFICATION 6] — Relatório (US-8.4) — **RESOLVIDO (Q6)**
"Renovada" = exclusivamente cadeia `previous_prescription_id`. "A vencer" default 30d com presets `{7, 15, 30, 60}`. 5 filtros MVP confirmados (status, tipo, profissional, paciente, faixa). CSV inclui medicamento/posologia obedecendo ability do exportador (linhas mascaradas para quem não tem `view_controlled`).

### ✅ [NEEDS CLARIFICATION 7] — PDF — **RESOLVIDO (Q7)**
Anexável depois do cadastro inicial (operação separada); substituível com versionamento (versões antigas em soft-delete + audit); limite 10 MB.

### ✅ [NEEDS CLARIFICATION 8] — Receita controlada — visualização — **RESOLVIDO (Q8)**
Atendente/Recepcionista vê linha **mascarada** (não oculta). Médico de outra especialidade também vê mascarado. Log de visualização registra evento (actor, prescription, when, IP, UA) — sem snapshot dos campos renderizados.

### ✅ [NEEDS CLARIFICATION 9] — Posologia — **RESOLVIDO (Q9)**
Texto livre nesta fase. Campos estruturados (frequência/via/duração) ficam para fase futura via colunas opcionais nullable (sem breaking change).

### ✅ [NEEDS CLARIFICATION 10] — Receita ↔ Consulta — **RESOLVIDO (Q10)**
`appointment_id` **nullable**. Se vinculada, consulta pode estar em qualquer status (`scheduled`/`in_progress`/`completed`). Habilita importação histórica (Q12).

### ✅ [NEEDS CLARIFICATION 11] — Timeline do paciente — **RESOLVIDO (Q11)**
Timeline exibe apenas `PrescricaoCriada`, `PrescricaoCancelada`, `ReceitaRenovada` (alertas não poluem). Médico não-emissor vê item; conteúdo mascarado para controlada (alinhado a Q8). Cancelada permanece visível com badge.

### ✅ [NEEDS CLARIFICATION 12] — Importação em massa — **RESOLVIDO (Q12)**
Fora do escopo desta fase. Modelo carrega 3 campos opcionais nullable para suporte futuro: `imported_at`, `imported_source`, `historical_external_id`. `professional_id` permanece NOT NULL — importações sem médico identificado usam "Profissional Genérico — Histórico" criado por seed.

### ✅ [NEEDS CLARIFICATION 13] — Notificação ao médico — **RESOLVIDO (Q13)**
Item de tarefa na Inbox interna (Fase 3) — sem e-mail/push. Sem opt-out individual por médico nesta fase.

---

## 10. Risk Matrix (consolidado)

| ID | Risco | Severidade | US afetadas | Mitigação |
|---|---|---|---|---|
| R-7-01 | Override de validade legal por médico | Alta | US-8.1 | AC-8.1.2 — validação server-side bloqueando alteração para especial/controlada. |
| R-7-02 | Alerta duplicado por race entre workers | Média | US-8.2 | FR-011 — idempotência `(prescription_id, alert_step)` em nível de DB. |
| R-7-03 | Alerta enviado após renovação/cancelamento | Média | US-8.2 | FR-012 — cancelamento de cadência imediato no evento. |
| R-7-04 | PII clínica vazada para LLM | **Crítica** | US-8.3 | FR-018, AC-8.3.1 — payload sem campo clínico. |
| R-7-05 | IA dá orientação clínica | **Crítica** | US-8.3 | AC-8.3.3 — guardrail definido aqui, enforce pela fase IA. |
| R-7-06 | Atendente exfiltra controlada via export | Alta | US-8.4 | AC-8.4.5 — filtro também na exportação. |
| R-7-07 | Cross-tenant via parâmetro de URL | Alta | US-8.4 | AC-8.4.9 — retorno 404, audit. |
| R-7-08 | PDF >10MB derruba salvamento | Baixa | US-8.1 | AC-8.1.4 — textuais persistidos antes do upload. |
| R-7-09 | WhatsApp fora da janela 24h sem HSM | Alta | US-8.2 | AC-8.2.5 — fallback para Inbox como tarefa manual; HSM obrigatório. |
| R-7-10 | `appointment.prescription_id` inexistente na Fase 5 | Média | US-8.3 | FR-020 — coordenação contratual; sem isso US-8.3 fica incompleta. |
| R-7-11 | Template HSM expõe nome de controlada em parâmetro | Alta | US-8.2 | Revisão obrigatória de cada template antes de aprovação Meta (ação no plan). |
| R-7-12 | Receita criada por médico desativado quebra audit | Média | US-8.1 | Imutabilidade de `professional_id`; pattern já existe (Fase 2 — ProfessionalDeactivated). |
| R-7-13 | Storage S3 indisponível no momento do upload | Baixa | US-8.1 | AC-8.1.4 — fila de retry async; receita não fica bloqueada. |

---

## 11. Implicações Regulatórias

Esta seção é **prescritiva**: as regras descritas a seguir não são preferências de produto — são exigências legais brasileiras vigentes em 2026. Toda decisão de implementação que conflite com elas exige amendment formal à Constituição.

### 11.1 Portaria SVS/MS nº 344/1998 (e atualizações — RDC 471/2021 atualizou listas)

Define o controle sanitário sobre substâncias psicotrópicas, entorpecentes e outras sujeitas a controle especial. Aplicável ao módulo:

- **Lista A (entorpecentes A1/A2) e Lista A3 (psicotrópicos)**: emissão exclusiva via **Notificação de Receita "A"** (cor amarela), em formulário específico fornecido pela autoridade sanitária local. **Validade: 30 dias** a partir da emissão. Em **três vias** (uma para a farmácia, uma para o controle sanitário, uma para o emissor).
  → Mapeamento: `type = controlada`. Validade `expires_at = issued_at + 30d` fixa por lei (FR-002).
- **Listas B1/B2 (psicotrópicos não entorpecentes)**: emissão via **Notificação de Receita "B"** (cor azul). **Validade: 30 dias**. Em **duas vias** (uma retida pela farmácia).
  → Mapeamento: `type = especial`. Validade fixa em 30d (FR-002).
- Demais medicamentos (Listas C, comuns sem controle especial): receita simples.
  → Mapeamento: `type = comum`. Validade configurável (FR-003).

**Implicação técnica**: o sistema **não pode permitir override** de validade para `especial`/`controlada`. Tentativas devem ser rejeitadas server-side e auditadas.

### 11.2 Resolução CFM nº 1.821/2007 e nº 2.299/2021 (prontuário e documentos clínicos)

Estabelece tempo mínimo de guarda de prontuário e documentos clínicos: **20 anos** após o último registro do paciente (versão atualizada). Embora receita não seja prontuário stricto sensu, está categorizada como documento médico relacionado.

- **Implicação técnica**: PDF e dados textuais da receita devem ser retidos por no mínimo **20 anos** após o último registro do paciente. FR-031 menciona 5 anos como **mínimo conservador** — refinar para 20 anos no `/speckit-clarify` se confirmado.

### 11.3 RDC ANVISA 306/2004 (atualizada por RDC 222/2018)

Regulamenta gerenciamento de resíduos de serviços de saúde. **Não impacta diretamente** o módulo de receituário, mas mencionada no briefing — relevância indireta apenas para integração futura com farmácia.

### 11.4 LGPD (Lei 13.709/2018)

- **Dado sensível**: medicamento e posologia são considerados dados de saúde (Art. 5º, II — sensível).
- **Base legal**: prestação de assistência médica (Art. 11, II, "f") — sem necessidade de consentimento granular, **mas** com finalidade, transparência e minimização.
- **Direito do titular**: solicitar exclusão (Art. 18) — colide com obrigação de guarda da Res. CFM 1.821 — prevalece a guarda regulatória; comunicar ao titular na recusa.

**Implicações técnicas mapeadas**:
- Payload da IA sem PII clínica (FR-018).
- Audit log granular de toda visualização de controlada (FR-029).
- Criptografia em repouso do PDF (FR-032).
- URL assinada com TTL ≤ 10min (FR-033).
- Mascaramento no relatório para usuários sem `prescription.view_controlled` (FR-023).

### 11.5 Resolução CFM nº 2.314/2022 (Telemedicina)

Define que receita emitida em consulta de telemedicina **DEVE** ser assinada digitalmente com certificado ICP-Brasil para ter validade legal. **Esta fase NÃO entrega assinatura digital** — modulação possível em fase futura ou exigir cadastro manual após emissão fora do sistema.

---

## 12. Definição de Pronto (DoD)

Checklist verificável — todas as caixas devem estar marcadas para esta fase ser considerada "Done":

### Funcional (User Stories)

- [ ] AC-8.1.1 a AC-8.1.10 passam em teste manual e automatizado.
- [ ] AC-8.2.1 a AC-8.2.9 passam em teste manual e automatizado.
- [ ] AC-8.3.1 a AC-8.3.7 passam em teste manual e automatizado (US-8.3 testada com stub de IA — integração real depende da Fase IA Matricial).
- [ ] AC-8.4.1 a AC-8.4.9 passam em teste manual e automatizado.

### Constitucional / Regulatório

- [ ] Toda receita do tipo especial/controlada criada com `expires_at - issued_at = 30d` exatos — gate SC-002 verde.
- [ ] Toda visualização de receita controlada registrada em audit_logs (cobertura 100% — SC-006).
- [ ] Cross-tenant retorna 404 em todas as tentativas (gate SC-005).
- [ ] Payload do evento `ReceitaProximaDoVencimento` validado por teste automatizado para **não conter** medicamento/posologia (gate Princípio I).
- [ ] PDF criptografado em repouso (verificado por inspeção do bucket) — FR-032.
- [ ] URL S3 assinada com TTL configurado em ≤ 10min — FR-033.

### Eventos de domínio

- [ ] 7 eventos publicados conforme §3 (PrescricaoCriada, PrescricaoAtualizada, PrescricaoCancelada, ReceitaProximaDoVencimento, ReceitaVencida, RenovacaoSolicitadaPelaIA, ReceitaRenovada).
- [ ] Cada evento documentado com payload mínimo + consumidor + idempotência.
- [ ] Test de auto-discovery de listeners (Laravel 11+ pattern já documentado no CLAUDE.md) — sem duplicação.

### Integração com fases prévias

- [ ] Timeline do paciente (Fase 2) recebe `PrescricaoCriada` e renderiza item.
- [ ] Mensageria (Fase 3) recebe envio de alerta com identificador HSM correto.
- [ ] Migration coordenada em Fase 5 para `appointment.prescription_id` aplicada e testada.

### Performance

- [ ] NFR-001 — p95 criação ≤ 800ms.
- [ ] NFR-002 — p95 relatório ≤ 1,5s para tenants com 50k receitas.
- [ ] NFR-005 — 99,5% de despacho de alerta em 5min do checkpoint.

### Segurança / Auditoria

- [ ] Pen test cross-tenant: zero violações.
- [ ] Test cross-perfil: Atendente não vê posologia de controlada.
- [ ] Audit log de exportação CSV contém lista de IDs exportados.

### Documentação

- [ ] OpenAPI atualizado (será produto do `/speckit-plan` e contratos).
- [ ] Postman collection da fase entregue em `docs/api/`.
- [ ] Quickstart com cenários de smoke E2E em staging.
- [ ] Templates HSM "vencimento receita" submetidos à Meta e aprovação confirmada antes do go-live.

### Clarifications

- [x] Todos os 13 itens da §9 resolvidos via `/speckit-clarify` em 2026-05-17 e propagados ao spec.

---

## 13. Índice de Acceptance Criteria

| AC | Título resumido | Prioridade | US |
|---|---|---|---|
| AC-8.1.1 | Cadastro completo persiste + evento PrescricaoCriada | 🔴 | US-8.1 |
| AC-8.1.2 | Validade fixa 30d para especial/controlada | 🔴 | US-8.1 |
| AC-8.1.3 | Validade comum dentro dos limites | 🔴 | US-8.1 |
| AC-8.1.4 | PDF assíncrono não bloqueia salvamento textual | 🟡 | US-8.1 |
| AC-8.1.5 | Atendente/Recepcionista não cria receita | 🔴 | US-8.1 |
| AC-8.1.6 | Controlada mascarada para não-emissores | 🟡 | US-8.1 |
| AC-8.1.7 | Campos clínicos imutáveis após save | 🟡 | US-8.1 |
| AC-8.1.8 | Cancelamento irreversível com motivo + evento | 🔴 | US-8.1 |
| AC-8.1.9 | Aviso de controlada visível no formulário | 🟡 | US-8.1 |
| AC-8.1.10 | Receita aparece na timeline do paciente | 🟢 | US-8.1 |
| AC-8.2.1 | Checkpoints D-15/D-7/D-1 emitem evento + envio | 🔴 | US-8.2 |
| AC-8.2.2 | Alerta obrigatório para especial/controlada | 🔴 | US-8.2 |
| AC-8.2.3 | Cadência interrompida em cancel/renovação | 🔴 | US-8.2 |
| AC-8.2.4 | Receita criada com pouca validade: skip de checkpoints passados | 🟡 | US-8.2 |
| AC-8.2.5 | Fora da janela 24h usa HSM aprovado ou Inbox manual | 🟡 | US-8.2 |
| AC-8.2.6 | Debounce 4h entre alertas ao mesmo destinatário | 🟡 | US-8.2 |
| AC-8.2.7 | ReceitaVencida emitido uma vez ao cruzar expires_at | 🟢 | US-8.2 |
| AC-8.2.8 | Opt-out suprime envio mas mantém evento interno | 🟡 | US-8.2 |
| AC-8.2.9 | Payload do evento sem PII clínica | 🔴 | US-8.2 |
| AC-8.3.1 | Contexto da IA sem dados clínicos | 🔴 | US-8.3 |
| AC-8.3.2 | Vinculação appointment.prescription_id ao renovar | 🔴 | US-8.3 |
| AC-8.3.3 | Guardrail anti orientação clínica | 🟡 | US-8.3 |
| AC-8.3.4 | IA não menciona nome de controlada | 🟡 | US-8.3 |
| AC-8.3.5 | Marcador "Renovação" visível ao médico na agenda | 🟢 | US-8.3 |
| AC-8.3.6 | IA respeita estado da receita no momento do agendamento | 🟡 | US-8.3 |
| AC-8.3.7 | Notificação ao médico após renovação agendada | 🟡 | US-8.3 |
| AC-8.4.1 | Relatório paginado com indicador de criticidade | 🔴 | US-8.4 |
| AC-8.4.2 | Mascaramento de controlada para perfis sem ability | 🔴 | US-8.4 |
| AC-8.4.3 | Filtros combinados retornam interseção determinística | 🔴 | US-8.4 |
| AC-8.4.4 | Definição clara de "renovada" via cadeia FK | 🟡 | US-8.4 |
| AC-8.4.5 | Exportação CSV respeita abilities e filtros | 🟡 | US-8.4 |
| AC-8.4.6 | p95 ≤ 1,5s na primeira página | 🟡 | US-8.4 |
| AC-8.4.7 | CSV de controladas com marcador CONFIDENCIAL_ | 🟢 | US-8.4 |
| AC-8.4.8 | Janela "A vencer" default 30d | 🟡 | US-8.4 |
| AC-8.4.9 | Cross-tenant retorna 404 (não 403) | 🔴 | US-8.4 |

**Totais**: 35 ACs — 🔴 13 críticos · 🟡 16 importantes · 🟢 6 nice-to-have.

---

## 14. Próximos Passos

1. ✅ ~~Rodar `/speckit-clarify`~~ — concluído em 2026-05-17 com 13/13 ambiguidades resolvidas.
2. Rodar `/speckit-plan` para gerar `plan.md`, `data-model.md`, `contracts/openapi.yaml`, `research.md`, `quickstart.md`.
3. Constitution Check (parte do plan): validar se os 8 princípios são atendidos ou se exige amendment (especialmente Princípio I — payload da IA e guardrail clínico).
4. `/speckit-tasks` para gerar o tasks.md por lotes.
5. `/speckit-checklist` antes da implementação para verificar artefatos derivados.
6. `/speckit-implement` para executar lotes.

---

**FIM DO SPEC** — Status: **Clarified** — pronto para `/speckit-plan`.
