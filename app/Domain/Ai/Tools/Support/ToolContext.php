<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tools\Support;

/**
 * Feature 017 (US5) — contexto imutável passado a cada ferramenta da IA.
 *
 * Define a fronteira de dados: o tenant da conversa e o contato atual (telefone).
 * As ferramentas SEMPRE filtram por `tenantId` no data layer (FR-034) e resolvem
 * paciente apenas por este contexto (nunca outro paciente — FR-029).
 */
final readonly class ToolContext
{
    public function __construct(
        public int $tenantId,
        public int $conversationId,
        public ?int $patientId,
        public ?string $contactPhone,
        public ?string $correlationId = null,
    ) {}

    /**
     * Apenas dígitos do telefone do contato (alinha com a coluna gerada
     * `pacientes.telefone_primario_normalizado`).
     */
    public function normalizedPhoneDigits(): ?string
    {
        if ($this->contactPhone === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $this->contactPhone) ?? '';

        return $digits !== '' ? $digits : null;
    }
}
