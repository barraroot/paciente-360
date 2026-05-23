<?php

declare(strict_types=1);

namespace App\Services\Panel\Collectors;

use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Prescription\Prescription\Prescription;
use App\Models\Agenda\Appointment;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * **T018 (Fase 10 — Spec 010 / US-1)** — Coleta os 4 KPIs do Dashboard Home.
 *
 * Cada KPI é computado via query agregada (`count()`) — nunca materializa
 * lista para contar. Filtros de scope ('user' ou 'clinic') aplicados
 * conforme contracts/api-panel-home.md § 3.
 *
 * @see specs/010-dashboard-home/data-model.md § 1.2
 */
final class KpiCollector
{
    /**
     * @return array{
     *   appointments_today: array{total:int,confirmed:int,pending:int},
     *   conversations_pending: array{total:int,unassigned:int},
     *   leads_new_7d: array{total:int},
     *   prescriptions_expiring_30d: array{total:int}
     * }
     */
    public function collect(Tenant $tenant, User $user, string $scope): array
    {
        $professionalIds = $scope === 'user' ? $this->professionalIdsForUser($user) : [];

        return [
            'appointments_today' => $this->appointmentsToday($user, $scope, $professionalIds),
            'conversations_pending' => $this->conversationsPending($user, $scope),
            'leads_new_7d' => $this->leadsNew7d($user, $scope, $professionalIds),
            'prescriptions_expiring_30d' => $this->prescriptionsExpiring30d($user, $scope),
        ];
    }

    /**
     * @return list<int>
     */
    private function professionalIdsForUser(User $user): array
    {
        return Professional::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->all();
    }

    /**
     * @param list<int> $professionalIds
     * @return array{total:int,confirmed:int,pending:int}
     */
    private function appointmentsToday(User $user, string $scope, array $professionalIds): array
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $base = Appointment::query()
            ->whereBetween('starts_at', [$today, $tomorrow])
            ->whereIn('status', ['scheduled', 'confirmed', 'realizada']);

        if ($scope === 'user') {
            $base->whereIn('professional_id', $professionalIds ?: [-1]);
        }

        $rows = (clone $base)
            ->selectRaw('status, COUNT(*) AS qty')
            ->groupBy('status')
            ->pluck('qty', 'status');

        $confirmed = (int) ($rows['confirmed'] ?? 0) + (int) ($rows['realizada'] ?? 0);
        $pending = (int) ($rows['scheduled'] ?? 0);
        $total = $confirmed + $pending;

        return [
            'total' => $total,
            'confirmed' => $confirmed,
            'pending' => $pending,
        ];
    }

    /**
     * @return array{total:int,unassigned:int}
     */
    private function conversationsPending(User $user, string $scope): array
    {
        $base = Conversation::query()
            ->where('status', 'aberta');

        if ($scope === 'user') {
            $base->where(function ($q) use ($user) {
                $q->whereNull('assigned_user_id')
                    ->orWhere('assigned_user_id', $user->id);
            });
        }

        $total = (clone $base)->count();
        $unassigned = (clone $base)->whereNull('assigned_user_id')->count();

        return [
            'total' => $total,
            'unassigned' => $unassigned,
        ];
    }

    /**
     * @param list<int> $professionalIds
     * @return array{total:int}
     */
    private function leadsNew7d(User $user, string $scope, array $professionalIds): array
    {
        $since = Carbon::now()->subDays(7);

        $base = Paciente::query()
            ->where('created_at', '>=', $since)
            ->whereHas('funilColuna', function ($q) {
                $q->where('is_terminal', false);
            });

        if ($scope === 'user') {
            $base->whereIn('profissional_responsavel_id', $professionalIds ?: [-1]);
        }

        return ['total' => $base->count()];
    }

    /**
     * @return array{total:int}
     */
    private function prescriptionsExpiring30d(User $user, string $scope): array
    {
        $today = Carbon::today();
        $thirtyDaysOut = Carbon::today()->addDays(30);

        $base = Prescription::query()
            ->where('status', 'active')
            ->whereBetween('expires_at', [$today, $thirtyDaysOut]);

        if ($scope === 'user') {
            $base->where('professional_id', $user->id);
        }

        return ['total' => $base->count()];
    }
}
