# Contract — Live Data Tools (US5)

Tools `laravel/ai` (`Laravel\Ai\Contracts\Tool`) em `app/Domain/Ai/Tools/`. Cada uma:
- recebe um `ToolContext` imutável `{tenant_id, conversation_id, patient_id?, contact_phone}` no construtor;
- delega a um Service de domínio existente (Fase 2/5/12) — **sem query crua na tool**;
- filtra `tenant_id` **explicitamente** (data layer, FR-034);
- registra `ai_tool_invocations` (input minimizado/pseudonimizado, outcome, latency) via `ToolInvocationLogger`;
- nunca retorna PII de terceiros nem dado clínico.

Cap global: `#[MaxSteps(config('ai.matricial.tools.max_round_trips', 3))]` no `PersonaAgent` (FR-032). Falha/timeout/empty → a tool retorna texto neutro de "indisponível" e a IA degrada (pergunta/handoff), nunca inventa (FR-033).

| Tool (`name`) | Tipo | Input (schema) | Retorno | Escopo/segurança |
|---|---|---|---|---|
| `get-clinic-info` | read | `{ topic?: enum(services\|pricing\|hours\|address\|all) }` | serviços/preços (de `appointment_types`: `nome`/`descricao`/`valor_particular`/`valor_convenio_default`/`duration_minutes`); horários e endereço quando DB-backed, senão do work context | clinic-level; **precedência DB > work context**: serviços/preços vêm do DB (valores podem mudar sem reeditar o work context — justifica o tool); horário/endereço caem no work context se não houver fonte no DB |
| `list-professionals` | read | `{}` | profissionais ativos `[{id,nome,especialidade?}]` | clinic-level (Fase 12) |
| `get-availability` | read | `{ professional_id?, location?, date_from?, date_to? }` | slots reais disponíveis | clinic-level (Fase 5); **não** expõe pacientes de outros agendamentos |
| `get-current-patient` | read | `{}` (sem parâmetros de busca) | dados do **contato da conversa** (nome p/ placeholder, status lead/ativo, último agendamento) | **só** `conversation.patient_id`/`contact_phone`; respeita `share_with_integrations_consent`; **nunca** busca por nome (FR-029) |
| `create-or-find-lead` | write (reversível) | `{}` (usa `contact_phone` do contexto) | `{patient_id, created: bool}` | lookup por `telefone_primario_normalizado`+tenant; cria `status='lead'`, `origem` do canal (R6) |
| `hold-slot` | write (reversível) | `{ professional_id, starts_at, appointment_type_id }` | `{reservation_id, expires_at}` ou conflito | cria `SlotReservation holder_type='ia'` com TTL+idempotency; **não** confirma agendamento (handoff, FR-018/R7) |

## Regras transversais

- **Precedência (FR-011)**: quando a resposta puder vir de tool e de work context, a tool (dado vivo) vence; se a tool não tiver o dado, cai no work context; nunca inventar.
- **Auditoria (FR-031)**: toda invocação gera `ai_tool_invocations`; o `AiExecutionLog.tools_used`/`tool_round_trips` registra o agregado da resposta.
- **Isolamento (FR-034/SC-007)**: testes provam que uma tool sob tenant A jamais retorna dado de tenant B nem de outro paciente, mesmo se o prompt pedir.
- **Sem confirmação financeira (Q2/FR-018)**: nenhuma tool confirma agendamento, cobra ou solicita PIX; isso é handoff.
