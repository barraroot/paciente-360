<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tools;

use App\Models\Paciente;
use App\Services\Pacientes\PacienteService;
use App\Support\Telefone\TelefoneNormalizer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Feature 017 (US5, FR-030) — cria ou encontra o LEAD do contato atual.
 *
 * Mapeia para o registro CRM existente (Paciente), buscado pelo telefone do
 * contato e criado com status 'lead' quando não existe. Ação reversível; NÃO
 * confirma agendamento nem pagamento.
 */
final class CreateOrFindLeadTool extends ConversationTool
{
    public function __construct($context, $logger, private readonly PacienteService $patients)
    {
        parent::__construct($context, $logger);
    }

    public function description(): Stringable|string
    {
        return 'Garante que o contato atual exista como lead no sistema (cria se necessário). Use quando avançar o atendimento de um novo contato.';
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
        return 'create-or-find-lead';
    }

    protected function run(Request $request): string
    {
        if ($this->context->patientId !== null) {
            return 'Contato já está registrado no sistema.';
        }

        $digits = $this->phoneDigits();

        if ($digits !== null) {
            $existing = Paciente::query()
                ->where('tenant_id', $this->context->tenantId)
                ->where('telefone_primario_normalizado', $digits)
                ->first();

            if ($existing !== null) {
                return 'Contato já registrado como '.($existing->status === 'lead' ? 'lead.' : 'paciente.');
            }
        }

        $this->patients->create([
            'nome' => 'Contato (WhatsApp)',
            'telefone_primario' => $this->context->contactPhone,
            'status' => 'lead',
            'origem' => 'whatsapp',
            'origem_origem' => 'canal',
        ]);

        return 'Novo lead registrado para este contato.';
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
