<?php

declare(strict_types=1);

namespace App\Domain\Ai\Tools;

use App\Models\Professional;
use App\Services\Agenda\SlotConflictException;
use App\Services\Agenda\SlotReservationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Feature 017 (US5, FR-018/030) — reserva PROVISÓRIA de horário (holder='ia',
 * com TTL). NÃO confirma o agendamento nem solicita pagamento — isso é handoff.
 */
final class HoldSlotTool extends ConversationTool
{
    public function __construct($context, $logger, private readonly SlotReservationService $reservations)
    {
        parent::__construct($context, $logger);
    }

    public function description(): Stringable|string
    {
        return 'Reserva PROVISORIAMENTE um horário escolhido pelo paciente (a confirmação e o sinal são feitos depois por um atendente). Use após o paciente escolher um horário disponível.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'professional_id' => $schema->integer()->description('Profissional do horário escolhido.')->required(),
            'appointment_type_id' => $schema->integer()->description('Tipo de atendimento.')->required(),
            'starts_at' => $schema->string()->description('Início do horário (ISO 8601).')->required(),
        ];
    }

    protected function toolName(): string
    {
        return 'hold-slot';
    }

    protected function run(Request $request): string
    {
        $professional = Professional::query()
            ->where('tenant_id', $this->context->tenantId)
            ->where('is_active', true)
            ->whereKey((int) ($request['professional_id'] ?? 0))
            ->first();

        if ($professional === null) {
            return 'Profissional não encontrado para reservar o horário.';
        }

        try {
            $startsAt = Carbon::parse((string) $request['starts_at']);
        } catch (\Throwable) {
            return 'Horário inválido. Confirme a data e a hora desejadas.';
        }

        try {
            $reservation = $this->reservations->reserve($professional, $startsAt, [
                'appointment_type_id' => (int) $request['appointment_type_id'],
                'holder_type' => SlotReservationService::HOLDER_IA,
                'holder_id' => (string) $this->context->conversationId,
                'idempotency_key' => (string) Str::uuid(),
            ]);

            return 'Horário reservado provisoriamente (reserva #'.$reservation->id.'). '
                .'A confirmação e o sinal serão tratados por um atendente — não solicite pagamento.';
        } catch (SlotConflictException) {
            return 'Esse horário acabou de ficar indisponível. Posso oferecer outro horário?';
        }
    }
}
