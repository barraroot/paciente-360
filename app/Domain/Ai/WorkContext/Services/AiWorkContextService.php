<?php

declare(strict_types=1);

namespace App\Domain\Ai\WorkContext\Services;

use App\Domain\Ai\WorkContext\Models\AiWorkContext;

/**
 * Feature 017 (US2) — regras do Contexto de Trabalho por clínica.
 *
 * Singleton por tenant: `upsert` cria ou atualiza o único registro, incrementando
 * `version` a cada salvamento (auditoria FR-025). `renderForPrompt` produz o bloco
 * markdown injetado no system prompt (precedência FR-011: voz/política).
 */
final class AiWorkContextService
{
    public function getForTenant(int $tenantId): ?AiWorkContext
    {
        return AiWorkContext::query()->where('tenant_id', $tenantId)->first();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsert(int $tenantId, array $data): AiWorkContext
    {
        $context = $this->getForTenant($tenantId);

        $payload = [
            'services' => $data['services'] ?? null,
            'pricing' => $data['pricing'] ?? null,
            'locations' => $data['locations'] ?? null,
            'deposit_policy' => $data['deposit_policy'] ?? null,
            'tone' => $data['tone'] ?? null,
            'qualification_questions' => $data['qualification_questions'] ?? null,
            'free_form' => $data['free_form'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        if ($context === null) {
            return AiWorkContext::create([
                ...$payload,
                'tenant_id' => $tenantId,
                'version' => 1,
            ]);
        }

        $context->fill($payload);
        $context->version = $context->version + 1;
        $context->save();

        return $context->refresh();
    }

    /**
     * Bloco markdown para o system prompt. Vazio quando não configurado.
     */
    public function renderForPrompt(?AiWorkContext $context): string
    {
        if ($context === null || ! $context->is_active) {
            return '';
        }

        $lines = ['# Contexto de Trabalho da Clínica'];

        if (filled($context->tone)) {
            $lines[] = "\nTom de voz: {$context->tone}";
        }

        if (filled($context->services)) {
            $lines[] = "\n## Serviços";
            foreach ($context->services as $service) {
                $nome = $service['nome'] ?? null;
                if (! $nome) {
                    continue;
                }
                $desc = filled($service['descricao'] ?? null) ? " — {$service['descricao']}" : '';
                $lines[] = "- {$nome}{$desc}";
            }
        }

        if (filled($context->pricing)) {
            $lines[] = "\n## Valores";
            foreach ($context->pricing as $price) {
                $item = $price['item'] ?? null;
                if (! $item) {
                    continue;
                }
                $parts = array_filter([
                    filled($price['valor_a_vista'] ?? null) ? "{$price['valor_a_vista']} à vista" : null,
                    filled($price['valor_cartao'] ?? null) ? "{$price['valor_cartao']} no cartão" : null,
                    $price['observacao'] ?? null,
                ]);
                $lines[] = "- {$item}: ".implode(' / ', $parts);
            }
        }

        if (filled($context->locations)) {
            $lines[] = "\n## Locais de atendimento";
            foreach ($context->locations as $location) {
                $cidade = $location['cidade'] ?? null;
                if (! $cidade) {
                    continue;
                }
                $endereco = filled($location['endereco'] ?? null) ? " ({$location['endereco']})" : '';
                $lines[] = "- {$cidade}{$endereco}";
            }
        }

        if (filled($context->deposit_policy['texto'] ?? null) || ($context->deposit_policy['exige_sinal'] ?? false)) {
            $policy = $context->deposit_policy;
            $texto = $policy['texto'] ?? null;
            if (! $texto && ($policy['exige_sinal'] ?? false)) {
                $pct = $policy['percentual'] ?? null;
                $meio = $policy['meio'] ?? null;
                $texto = trim('Confirmação com sinal antecipado'.($pct ? " de {$pct}%" : '').($meio ? " via {$meio}" : '').'.');
            }
            $lines[] = "\n## Política de confirmação";
            $lines[] = $texto;
            $lines[] = 'A coleta do sinal/pagamento e a confirmação são feitas por um atendente humano — você NÃO solicita pagamento nem confirma o agendamento.';
        }

        if (filled($context->qualification_questions)) {
            $lines[] = "\n## Perguntas de qualificação (faça UMA por vez, na ordem, de forma acolhedora)";
            foreach ($context->qualification_questions as $i => $question) {
                $n = $i + 1;
                $lines[] = "{$n}. {$question}";
            }
        }

        if (filled($context->free_form)) {
            $lines[] = "\n## Diferenciais e abordagem";
            $lines[] = $context->free_form;
        }

        return implode("\n", $lines);
    }
}
