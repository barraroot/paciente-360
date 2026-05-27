<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tools;

use App\Models\Paciente;
use App\Support\Telefone\TelefoneNormalizer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Feature 017 (US5, FR-029) — contexto NÃO-identificável do contato ATUAL.
 *
 * Resolve SOMENTE o paciente da conversa (por patient_id ou telefone do contato),
 * nunca outro paciente e nunca por nome. NÃO retorna nome/CPF/telefone ao modelo
 * (o nome real é injetado só na saída via {{primeiro_nome}}). Dados além da
 * existência respeitam o consentimento de integrações.
 */
final class GetCurrentPatientTool extends ConversationTool
{
    public function description(): Stringable|string
    {
        return 'Retorna o contexto do contato ATUAL da conversa (se já é conhecido/lead/paciente). Não retorna nome nem dados de outras pessoas.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function toolName(): string
    {
        return 'get-current-patient';
    }

    protected function run(Request $request): string
    {
        $patient = $this->resolvePatient();

        if ($patient === null) {
            return 'Contato ainda não cadastrado (novo lead). Não há histórico no sistema.';
        }

        $statusLabel = match ($patient->status) {
            'lead' => 'lead (ainda não é paciente)',
            'ativo' => 'paciente ativo',
            'inativo' => 'paciente inativo',
            default => 'contato conhecido',
        };

        $consent = (bool) ($patient->share_with_integrations_consent ?? false);
        $consentNote = $consent
            ? 'Consentiu compartilhar dados para integrações.'
            : 'Sem consentimento para detalhes adicionais — trate com dados mínimos.';

        return "Contato conhecido: {$statusLabel}. {$consentNote}";
    }

    private function resolvePatient(): ?Paciente
    {
        if ($this->context->patientId !== null) {
            return Paciente::query()
                ->where('tenant_id', $this->context->tenantId)
                ->whereKey($this->context->patientId)
                ->first();
        }

        $digits = $this->phoneDigits();
        if ($digits === null) {
            return null;
        }

        return Paciente::query()
            ->where('tenant_id', $this->context->tenantId)
            ->where('telefone_primario_normalizado', $digits)
            ->first();
    }

    private function phoneDigits(): ?string
    {
        if ($this->context->contactPhone === null) {
            return null;
        }

        try {
            return preg_replace('/\D/', '', TelefoneNormalizer::normalize($this->context->contactPhone));
        } catch (Throwable) {
            return $this->context->normalizedPhoneDigits();
        }
    }
}
