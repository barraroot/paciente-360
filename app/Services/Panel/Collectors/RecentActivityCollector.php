<?php

declare(strict_types=1);

namespace App\Services\Panel\Collectors;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuditLog\Humanizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * **T043 (Fase 10 — Spec 010 / US-4)** — Coleta timeline de atividade
 * recente (últimas 24h) do tenant.
 *
 * Gates LGPD:
 *   - Filtro allow-list de event types (`config('panel.recent_activity_allowlist')`)
 *     — eventos como `paciente.viewed` NÃO entram.
 *   - Filtro `actor_type='user'` — exclui eventos de sistema/webhook.
 *   - Humanização via {@see Humanizer} que NUNCA inclui CPF/email/telefone/clínico.
 *
 * @see specs/010-dashboard-home/research.md R7
 * @see specs/010-dashboard-home/data-model.md § 1.5
 */
final class RecentActivityCollector
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collect(Tenant $tenant, User $user, string $scope): Collection
    {
        $since = Carbon::now()->subDay();
        $allowlist = config('panel.recent_activity_allowlist', []);

        $logs = AuditLog::query()
            ->with('user')
            ->where('tenant_id', $tenant->id) // AuditLog não usa BelongsToTenant — escopa manualmente
            ->where('created_at', '>=', $since)
            ->where('actor_type', 'user')
            ->whereIn('action', $allowlist)
            ->orderByDesc('created_at')
            ->limit(config('panel.limits.recent_activity', 8))
            ->get();

        return $logs->map(function (AuditLog $log) {
            $humanized = Humanizer::humanize($log);

            return [
                'id' => $log->id,
                'actor' => [
                    'name' => $log->user?->name ?? 'Sistema',
                    'initials' => $this->initialsFor($log->user?->name ?? '?'),
                ],
                'description' => $humanized['description'],
                'occurred_at' => $log->created_at?->toIso8601String(),
                'link' => $humanized['link'],
            ];
        });
    }

    private function initialsFor(string $name): string
    {
        $parts = array_filter(preg_split('/\s+/', trim($name)) ?: []);
        $first = mb_substr((string) ($parts[0] ?? '?'), 0, 1);
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last) ?: '?';
    }
}
