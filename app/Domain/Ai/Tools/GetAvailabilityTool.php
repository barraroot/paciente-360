<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tools;

use App\Models\Agenda\AppointmentType;
use App\Models\Professional;
use App\Services\Agenda\SlotGeneratorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Feature 017 (US5) — horários REAIS disponíveis na agenda (Fase 5), não uma
 * lista estática. Resolve profissional/tipo (ou os primeiros ativos) e janela.
 */
final class GetAvailabilityTool extends ConversationTool
{
    public function __construct($context, $logger, private readonly SlotGeneratorService $slots)
    {
        parent::__construct($context, $logger);
    }

    public function description(): Stringable|string
    {
        return 'Consulta os horários realmente disponíveis na agenda. Use quando o paciente perguntar por horários/disponibilidade. NÃO confirma o agendamento.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'professional_id' => $schema->integer()->description('Opcional — profissional específico.'),
            'appointment_type_id' => $schema->integer()->description('Opcional — tipo de atendimento.'),
            'date_from' => $schema->string()->description('Opcional — data inicial (YYYY-MM-DD).'),
            'date_to' => $schema->string()->description('Opcional — data final (YYYY-MM-DD).'),
        ];
    }

    protected function toolName(): string
    {
        return 'get-availability';
    }

    protected function run(Request $request): string
    {
        $professional = $this->resolveProfessional($request['professional_id'] ?? null);
        $type = $this->resolveType($request['appointment_type_id'] ?? null);

        if ($professional === null || $type === null) {
            return 'Não há profissional ou tipo de atendimento ativo para consultar a agenda.';
        }

        $from = $this->parseDate($request['date_from'] ?? null) ?? Carbon::now();
        $to = $this->parseDate($request['date_to'] ?? null) ?? (clone $from)->addDays(14);

        $slots = $this->slots->generate($professional, $type, $from, $to);

        if ($slots->isEmpty()) {
            return "Sem horários disponíveis para {$professional->name} no período consultado.";
        }

        $formatted = $slots->take(8)->map(
            fn (array $slot): string => '- '.Carbon::parse($slot['starts_at'])->locale('pt_BR')->isoFormat('ddd, DD/MM [às] HH:mm'),
        );

        return "Horários disponíveis com {$professional->name}:\n".$formatted->implode("\n");
    }

    private function resolveProfessional(mixed $id): ?Professional
    {
        $query = Professional::query()
            ->where('tenant_id', $this->context->tenantId)
            ->where('is_active', true);

        if ($id !== null) {
            return $query->whereKey((int) $id)->first();
        }

        return $query->orderBy('name')->first();
    }

    private function resolveType(mixed $id): ?AppointmentType
    {
        $query = AppointmentType::query()
            ->where('tenant_id', $this->context->tenantId)
            ->where('is_active', true);

        if ($id !== null) {
            return $query->whereKey((int) $id)->first();
        }

        return $query->orderBy('nome')->first();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
