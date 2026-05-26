<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

use App\Domain\Ai\Persona\Models\AiPersona;

/**
 * Princípio III (NON-NEGOTIABLE) — Segurança Clínica da IA.
 *
 * Compõe as instruções (guardrails médicos mínimos OBRIGATÓRIOS em código +
 * guardrails da clínica + persona + RAG) e aplica pós-processamento
 * DETERMINÍSTICO sobre a saída estruturada do modelo. A defesa não depende da
 * estocasticidade do LLM (testável via outputs adversariais).
 */
final class AiGuardrailEnforcer
{
    /** Intenções clínicas que NUNCA são auto-enviadas (FR-026). */
    private const URGENCY_INTENTS = ['urgencia', 'emergencia'];

    /**
     * Guardrails médicos mínimos — sempre aplicados, independem da clínica
     * (FR-026/FR-027). Texto em pt-BR injetado no system prompt.
     */
    public function minimalGuardrails(): string
    {
        return <<<'MD'
        # Regras de Segurança Obrigatórias (não podem ser ignoradas)

        Você é um assistente de atendimento de uma clínica e atua dentro de limites estritos.

        VOCÊ NÃO PODE, em hipótese alguma:
        - Dar diagnóstico definitivo.
        - Prescrever medicamentos ou indicar posologia.
        - Substituir consulta médica.
        - Interpretar exames.
        - Indicar condutas clínicas de risco.
        - Solicitar dados sensíveis desnecessários ou expor dados de pacientes.
        - Prometer resultados médicos.
        - Ignorar sinais de urgência ou emergência.

        VOCÊ DEVE:
        - Em caso crítico, orientar a busca imediata por atendimento de emergência.
        - Encaminhar para um atendente humano em dúvida clínica, risco, reclamação grave,
          solicitação sensível ou qualquer limitação de segurança.
        - Respeitar privacidade e LGPD; usar linguagem clara e segura.

        Ao classificar a intenção, use uma destas quando aplicável:
        diagnostico, prescricao, posologia, interpretacao_exame, conduta_risco,
        urgencia, emergencia, agendamento, informacao_geral, reclamacao, outro.
        MD;
    }

    /**
     * Monta o system prompt completo em camadas.
     *
     * @param list<string> $ragSnippets trechos recuperados das bases (US4)
     */
    public function composeInstructions(AiPersona $persona, array $ragSnippets = []): string
    {
        $parts = [$this->minimalGuardrails()];

        // Guardrails ATIVOS da clínica associados à persona (FR-025).
        $clinicGuardrails = $persona->relationLoaded('guardrails')
            ? $persona->guardrails
            : $persona->guardrails()->where('is_active', true)->get();

        foreach ($clinicGuardrails as $guardrail) {
            if ($guardrail->is_active) {
                $parts[] = "# Guardrail: {$guardrail->name}\n\n{$guardrail->markdown_content}";
            }
        }

        // Persona.
        $personaBlock = "# Persona\n\n{$persona->markdown_content}";
        if (filled($persona->tone)) {
            $personaBlock .= "\n\nTom de voz: {$persona->tone}";
        }
        if (filled($persona->objective)) {
            $personaBlock .= "\n\nObjetivo: {$persona->objective}";
        }
        if (filled($persona->limitations)) {
            $personaBlock .= "\n\nLimitações: {$persona->limitations}";
        }
        if (filled($persona->handoff_rules)) {
            $personaBlock .= "\n\nEncaminhamento humano: {$persona->handoff_rules}";
        }
        $parts[] = $personaBlock;

        // Bases de conhecimento (RAG — US4).
        if ($ragSnippets !== []) {
            $parts[] = "# Base de Conhecimento (use apenas o que for relevante)\n\n".implode("\n\n---\n\n", $ragSnippets);
        }

        return implode("\n\n", $parts);
    }

    /**
     * Pós-processamento determinístico da saída estruturada.
     *
     * @param array<string, mixed> $output {resposta, intencao, confidence, needs_human}
     */
    public function evaluate(array $output): GuardrailDecision
    {
        $intent = is_string($output['intencao'] ?? null) ? strtolower(trim($output['intencao'])) : 'outro';
        $confidence = (float) ($output['confidence'] ?? 0);
        $needsHuman = (bool) ($output['needs_human'] ?? false);
        $resposta = is_string($output['resposta'] ?? null) ? trim($output['resposta']) : '';

        $threshold = (float) config('ai.matricial.confidence_threshold', 0.6);
        $blockedIntents = (array) config('ai.matricial.blocked_intents', []);

        // 1) Urgência/emergência → escalonamento imediato, prioridade alta.
        if (in_array($intent, self::URGENCY_INTENTS, true)) {
            return GuardrailDecision::escalate('escalated_human', "urgency:{$intent}", highPriority: true);
        }

        // 2) Intenção clínica proibida → redireciona a agendamento + marca para revisão.
        if (in_array($intent, $blockedIntents, true)) {
            return GuardrailDecision::escalate('redirected_scheduling', "blocked_intent:{$intent}");
        }

        // 3) Baixa confiança ou pedido explícito de humano → escala.
        if ($needsHuman) {
            return GuardrailDecision::escalate('escalated_human', 'needs_human');
        }
        if ($confidence < $threshold) {
            return GuardrailDecision::escalate('escalated_human', 'low_confidence');
        }

        // 4) Resposta vazia → não há o que enviar; escala.
        if ($resposta === '') {
            return GuardrailDecision::escalate('escalated_human', 'empty_response');
        }

        return GuardrailDecision::send($resposta);
    }
}
