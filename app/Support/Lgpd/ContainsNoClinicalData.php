<?php

declare(strict_types=1);

namespace App\Support\Lgpd;

/**
 * **Marker interface — Princípio I (LGPD) + Princípio III (Auditabilidade IA).**
 *
 * Eventos de domínio consumidos pela camada de IA (LLM) DEVEM implementar
 * esta interface declarando que SEU PAYLOAD NÃO CONTÉM dados clínicos
 * identificáveis (nome de medicamento, posologia, diagnóstico, etc.).
 *
 * Adicionalmente, eventos que carregam PII identificadora (CPF, RG, telefone,
 * carteirinha, e-mail) também NÃO devem implementar esta interface — ou
 * devem pseudonimizar essas propriedades antes (substituir por placeholders
 * em memória de processo, jamais persistir o mapeamento).
 *
 * ## Histórico
 *
 * - **Fase 7 (Receituários)**: introduzido o marker. Evento `ReceitaProximaDoVencimento`
 *   foi o primeiro a implementá-lo, com allowlist de exatamente 7 propriedades
 *   validada por reflection em `PrescriptionEventPayloadLgpdTest`.
 *
 * - **Fase 8 (Finalização — T011/T012)**: o padrão é estendido a TODOS os eventos
 *   listados em {@see config('finalization.ai_consumed_events')} via gate CI
 *   {@see \Tests\Feature\Constitutional\EventsForAiPseudonymizationTest}.
 *   Quando a fase IA real for entregue, ela registra na config quais eventos
 *   consome — o gate falha automaticamente se algum evento listado não
 *   implementar esta marker interface.
 *
 * ## Lista candidata (Q17 — catálogo de eventos webhook)
 *
 * Mesmos 13 eventos do catálogo de webhooks são candidatos a consumo pela IA
 * futura — todos DEVEM implementar `ContainsNoClinicalData` no momento em que
 * forem listados em `ai_consumed_events`:
 *
 *   - PacienteCriado, PacienteAtualizado (Fase 2)
 *   - AgendamentoCriado/Confirmado/Cancelado/Reagendado (Fase 5)
 *   - ConsultaRealizada (Fase 5)
 *   - PrescricaoCriada / PrescricaoCancelada / ReceitaRenovada / ReceitaProximaDoVencimento (Fase 7)
 *   - RetornoDisparado (Fase 6 — gated)
 *   - EscalonamentoParaHumano (Fase IA — gated)
 *
 * @see specs/008-finalizacao-mvp/plan.md §6 Test Strategy
 * @see specs/008-finalizacao-mvp/research.md §1 Q29
 */
interface ContainsNoClinicalData
{
}
