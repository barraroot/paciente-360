<?php

namespace App\Services\Agenda;

use App\Events\Agenda\ProfissionalAgendaConfigurada;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\ProfessionalSchedule;
use App\Models\Agenda\ScheduleException;
use App\Models\Professional;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * T041 — Configuração de agenda do profissional (US-6.1).
 *
 * Pipeline: Form Request → Controller → Service (esta classe) → Eloquent + Event.
 */
class ScheduleConfigurationService
{
    /**
     * Atualiza agenda do profissional em batch (delete + reinsert).
     *
     * @param array<string, mixed> $data
     * @return array{schedules: Collection<int, ProfessionalSchedule>, professional: Professional}
     */
    public function updateSchedule(Professional $professional, array $data, User $actor): array
    {
        $effectiveFrom = $data['effective_from'] ?? Carbon::today()->toDateString();

        DB::transaction(function () use ($professional, $data, $actor, $effectiveFrom): void {
            // Apaga registros existentes para o effective_from informado
            ProfessionalSchedule::query()
                ->where('professional_id', $professional->id)
                ->where('effective_from', $effectiveFrom)
                ->delete();

            // Cria novos schedules (1 por day_of_week)
            foreach ($data['schedules'] ?? [] as $entry) {
                $this->validateBlocks($entry['blocks'] ?? []);

                ProfessionalSchedule::create([
                    'tenant_id' => $professional->tenant_id,
                    'professional_id' => $professional->id,
                    'day_of_week' => (int) $entry['day_of_week'],
                    'blocks' => $entry['blocks'],
                    'effective_from' => $entry['effective_from'] ?? $effectiveFrom,
                    'effective_until' => $entry['effective_until'] ?? null,
                    'created_by_user_id' => $actor->id,
                ]);
            }

            // Aplica timezone override (clarify nº 13)
            if (array_key_exists('timezone', $data)) {
                $professional->timezone = $data['timezone'];
                $professional->save();
            }

            // Sync M2M tipos aceitos
            if (isset($data['accepted_appointment_type_ids'])) {
                $sync = collect($data['accepted_appointment_type_ids'])->mapWithKeys(
                    fn ($id) => [$id => ['tenant_id' => $professional->tenant_id]]
                )->all();

                $professional->appointmentTypes()->sync($sync);
            }
        });

        ProfissionalAgendaConfigurada::dispatch($professional, $actor->id, $effectiveFrom);

        $schedules = ProfessionalSchedule::query()
            ->where('professional_id', $professional->id)
            ->where('effective_from', $effectiveFrom)
            ->orderBy('day_of_week')
            ->get();

        return ['schedules' => $schedules, 'professional' => $professional->fresh()];
    }

    /**
     * Cria exceção de agenda (bloqueio pontual).
     *
     * @param array<string, mixed> $data
     * @return array{exception: ScheduleException, cascaded_cancellations: list<int>}
     */
    public function createException(Professional $professional, array $data, User $actor): array
    {
        $exception = ScheduleException::create([
            'tenant_id' => $professional->tenant_id,
            'professional_id' => $professional->id,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'reason' => $data['reason'] ?? null,
            'created_by_user_id' => $actor->id,
        ]);

        // FR-028c — cancelar appointments que sobrepõem
        $cascaded = $this->cascadeCancelOverlappingAppointments($exception);

        return ['exception' => $exception, 'cascaded_cancellations' => $cascaded];
    }

    /**
     * @return list<int> appointment IDs canceladas
     */
    private function cascadeCancelOverlappingAppointments(ScheduleException $exception): array
    {
        $appointments = Appointment::query()
            ->where('professional_id', $exception->professional_id)
            ->whereIn('status', Appointment::ACTIVE_STATUSES)
            ->where('starts_at', '<', $exception->ends_at)
            ->where('ends_at', '>', $exception->starts_at)
            ->get();

        $ids = [];
        foreach ($appointments as $appointment) {
            $appointment->update([
                'status' => 'canceled',
                'quem_cancelou' => 'sistema',
                'motivo_cancelamento' => 'schedule_exception',
                'canceled_at' => now(),
            ]);
            $ids[] = $appointment->id;
        }

        return $ids;
    }

    /**
     * Validação leve de blocks (formato + ordering).
     *
     * @param list<array{start:string,end:string}> $blocks
     */
    private function validateBlocks(array $blocks): void
    {
        $previousEnd = null;
        foreach ($blocks as $block) {
            if (! preg_match('/^\d{2}:\d{2}$/', $block['start']) || ! preg_match('/^\d{2}:\d{2}$/', $block['end'])) {
                throw new \InvalidArgumentException('Block start/end must be in HH:MM format.');
            }
            if (strcmp($block['start'], $block['end']) >= 0) {
                throw new \InvalidArgumentException('Block end must be greater than start.');
            }
            if ($previousEnd !== null && strcmp($block['start'], $previousEnd) < 0) {
                throw new \InvalidArgumentException('Blocks must be ordered and non-overlapping.');
            }
            $previousEnd = $block['end'];
        }
    }
}
