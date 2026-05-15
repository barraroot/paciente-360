<?php

namespace App\Services\Agenda;

use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\ExternalCalendarBusy;
use App\Models\Agenda\ProfessionalSchedule;
use App\Models\Agenda\ScheduleException;
use App\Models\Agenda\SlotReservation;
use App\Models\Professional;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * T070 — Geração determinística de slots disponíveis (R7).
 *
 * Algoritmo:
 *  1. Carrega ProfessionalSchedule por (day_of_week, effective range).
 *  2. Para cada dia da janela, corta blocos em slots de (duration + buffer).
 *  3. Filtra slots ocupados por: Appointment ativo / ScheduleException /
 *     ExternalCalendarBusy / SlotReservation ativa.
 *  4. Output: Collection<{starts_at, ends_at, professional_id, type_id}>.
 *
 * Pure function-ish — recebe entidades, retorna collection. Cache 60s aplicado
 * no caller (controller) via Redis tag `agenda:slots:{tenant}:{prof}:{type}:{date}`.
 */
final class SlotGeneratorService
{
    /**
     * @return Collection<int, array{starts_at: Carbon, ends_at: Carbon, professional_id: int, appointment_type_id: int}>
     */
    public function generate(
        Professional $professional,
        AppointmentType $type,
        Carbon $from,
        Carbon $to,
    ): Collection {
        $slotMinutes = $type->duration_minutes + $type->buffer_minutes;

        // Carrega tudo o que pode bloquear slots
        $schedules = ProfessionalSchedule::query()
            ->where('professional_id', $professional->id)
            ->where('effective_from', '<=', $to->toDateString())
            ->where(function ($q) use ($from): void {
                $q->whereNull('effective_until')->orWhere('effective_until', '>=', $from->toDateString());
            })
            ->get()
            ->groupBy('day_of_week');

        $exceptions = ScheduleException::query()
            ->where('professional_id', $professional->id)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get();

        $appointments = Appointment::query()
            ->where('professional_id', $professional->id)
            ->whereIn('status', Appointment::ACTIVE_STATUSES)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get();

        $externalBusy = ExternalCalendarBusy::query()
            ->where('professional_id', $professional->id)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get();

        $reservations = SlotReservation::query()
            ->where('professional_id', $professional->id)
            ->whereNull('released_at')
            ->where('expires_at', '>', now())
            ->get();

        $slots = collect();

        // Itera dia a dia
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lt($to)) {
            $dow = $cursor->dayOfWeekIso; // 1..7 (Mon..Sun)

            $daySchedules = $schedules->get($dow, collect());

            foreach ($daySchedules as $schedule) {
                foreach ($schedule->blocks as $block) {
                    [$startH, $startM] = explode(':', $block['start']);
                    [$endH, $endM] = explode(':', $block['end']);

                    $blockStart = $cursor->copy()->setTime((int) $startH, (int) $startM);
                    $blockEnd = $cursor->copy()->setTime((int) $endH, (int) $endM);

                    // Slot loop dentro do bloco
                    $slotStart = $blockStart->copy();
                    while ($slotStart->copy()->addMinutes($slotMinutes)->lte($blockEnd)) {
                        $slotEnd = $slotStart->copy()->addMinutes($type->duration_minutes);

                        if (
                            $slotStart->lt($from) || $slotStart->gte($to)
                            || $this->isBlockedByException($slotStart, $slotEnd, $exceptions)
                            || $this->isOccupiedByAppointment($slotStart, $slotEnd, $appointments)
                            || $this->isBlockedByExternalBusy($slotStart, $slotEnd, $externalBusy)
                            || $this->isReserved($slotStart, $reservations)
                        ) {
                            $slotStart->addMinutes($slotMinutes);

                            continue;
                        }

                        $slots->push([
                            'starts_at' => $slotStart->copy(),
                            'ends_at' => $slotEnd,
                            'professional_id' => $professional->id,
                            'appointment_type_id' => $type->id,
                        ]);

                        $slotStart->addMinutes($slotMinutes);
                    }
                }
            }

            $cursor->addDay();
        }

        return $slots;
    }

    private function isBlockedByException(Carbon $start, Carbon $end, Collection $exceptions): bool
    {
        return $exceptions->contains(fn ($e) => $start->lt($e->ends_at) && $end->gt($e->starts_at));
    }

    private function isOccupiedByAppointment(Carbon $start, Carbon $end, Collection $appointments): bool
    {
        return $appointments->contains(fn ($a) => $start->lt($a->ends_at) && $end->gt($a->starts_at));
    }

    private function isBlockedByExternalBusy(Carbon $start, Carbon $end, Collection $busy): bool
    {
        return $busy->contains(fn ($b) => $start->lt($b->ends_at) && $end->gt($b->starts_at));
    }

    private function isReserved(Carbon $start, Collection $reservations): bool
    {
        return $reservations->contains(fn ($r) => $r->starts_at->equalTo($start));
    }
}
