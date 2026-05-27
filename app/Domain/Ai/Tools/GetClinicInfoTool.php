<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tools;

use App\Models\Agenda\AppointmentType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Feature 017 (US5) — serviços e preços VIVOS da clínica (de `appointment_types`).
 * Horário/endereço não têm fonte no DB → vêm do Contexto de Trabalho (FR-011).
 */
final class GetClinicInfoTool extends ConversationTool
{
    public function description(): Stringable|string
    {
        return 'Consulta os serviços/procedimentos oferecidos pela clínica e seus valores atuais. Use quando o paciente perguntar o que a clínica faz ou quanto custa.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()
                ->description('Opcional: services | pricing | all')
                ->enum(['services', 'pricing', 'all']),
        ];
    }

    protected function toolName(): string
    {
        return 'get-clinic-info';
    }

    protected function run(Request $request): string
    {
        $types = AppointmentType::query()
            ->where('tenant_id', $this->context->tenantId)
            ->where('is_active', true)
            ->orderBy('nome')
            ->get();

        if ($types->isEmpty()) {
            return 'Sem serviços cadastrados no sistema. Use o contexto de trabalho da clínica para informar serviços e valores.';
        }

        $lines = $types->map(function (AppointmentType $type): string {
            $valor = $type->valor_particular > 0
                ? ' — R$ '.number_format((float) $type->valor_particular, 2, ',', '.')
                : '';
            $desc = filled($type->descricao) ? " ({$type->descricao})" : '';

            return "- {$type->nome}{$valor}{$desc}";
        });

        return "Serviços e valores atuais da clínica:\n".$lines->implode("\n");
    }
}
