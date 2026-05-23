<?php

declare(strict_types=1);

namespace App\Services\Panel\Collectors;

use App\Models\Agenda\Appointment;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * **T026 (Fase 10 — Spec 010 / US-2)** — Coleta próximas consultas em
 * janela configurável (default 6h fixas conforme Q2 da clarification).
 *
 * Limit 5, ordem cronológica crescente. Eager loading rigoroso para evitar
 * N+1 (gate G2).
 *
 * @see specs/010-dashboard-home/data-model.md § 1.3
 */
final class UpcomingAppointmentsCollector
{
    /**
     * @return Collection<int, Appointment>
     */
    public function collect(Tenant $tenant, User $user, string $scope): Collection
    {
        $now = Carbon::now();
        $until = (clone $now)->addMinutes((int) config('panel.upcoming_window_minutes', 360));

        $query = Appointment::query()
            ->with(['appointmentType', 'paciente', 'professional'])
            ->whereBetween('starts_at', [$now, $until])
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->orderBy('starts_at')
            ->limit(config('panel.limits.upcoming_appointments', 5));

        if ($scope === 'user') {
            $ids = Professional::query()->where('user_id', $user->id)->pluck('id')->all();
            $query->whereIn('professional_id', $ids ?: [-1]);
        }

        return $query->get();
    }
}
