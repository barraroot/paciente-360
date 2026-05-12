<?php

namespace App\Domain\Messaging\QuickReply\Services;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\User;

/**
 * T235 — Serviço de substituição de variáveis em respostas rápidas.
 *
 * Variáveis suportadas (NC-8.b):
 *  - {nome_paciente}           → $conv->patient->nome ?? ''
 *  - {primeiro_nome_paciente}  → primeiro token de nome_paciente
 *  - {nome_profissional}       → $conv->patient->profissionalResponsavel->name ?? ''
 *  - {nome_clinica}            → tenant name
 *  - {nome_atendente}          → $atendente->name
 *  - {data_proxima_consulta}   → '' (placeholder — implementado na Fase 5)
 *
 * Variáveis null/vazias rendem como string vazia.
 * Variáveis desconhecidas rendem como string vazia.
 */
final class VariableSubstitutor
{
    /**
     * Substitui todas as variáveis `{nome}` no template com os valores do contexto.
     *
     * @param array<string, string|null> $context
     */
    public function substitute(string $template, array $context): string
    {
        return preg_replace_callback(
            '/\{([a-z_]+)\}/',
            static fn (array $matches): string => (string) ($context[$matches[1]] ?? ''),
            $template,
        ) ?? $template;
    }

    /**
     * Resolve o contexto de variáveis a partir da conversa e do atendente autenticado.
     *
     * @return array<string, string> Mapa de variável → valor resolvido
     */
    public function resolveContext(Conversation $conv, User $atendente): array
    {
        $conv->loadMissing(['patient.profissionalResponsavel', 'tenant']);

        $patient = $conv->patient;
        $nomePaciente = $patient?->nome ?? '';
        $primeiraNome = $nomePaciente !== '' ? explode(' ', trim($nomePaciente))[0] : '';

        $nomeProfissional = $patient?->profissionalResponsavel?->name ?? '';
        $nomeClinica = $conv->tenant?->name ?? '';

        return [
            'nome_paciente' => $nomePaciente,
            'primeiro_nome_paciente' => $primeiraNome,
            'nome_profissional' => $nomeProfissional,
            'nome_clinica' => $nomeClinica,
            'nome_atendente' => $atendente->name,
            'data_proxima_consulta' => '', // Fase 5 placeholder — sempre vazio
        ];
    }
}
