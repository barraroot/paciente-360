<?php

declare(strict_types=1);

namespace App\Services\Panel;

use App\Models\User;
use App\Policies\Panel\PanelHomePolicy;
use App\Services\Panel\Collectors\AttentionItemsCollector;
use App\Services\Panel\Collectors\KpiCollector;
use App\Services\Panel\Collectors\RecentActivityCollector;
use App\Services\Panel\Collectors\UpcomingAppointmentsCollector;
use App\Support\Metrics\PanelHomeMetricsContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * **T009 (Fase 10 — Spec 010)** — Orquestrador do Dashboard Home.
 *
 * Aplica scope policy (Q1: força 'user' se sem permissão de admin), monta
 * cache key isolada por tenant+user+scope, invoca os 4 collectors com
 * degradação graceful (R13 — falha em 1 collector não derruba response).
 *
 * @see specs/010-dashboard-home/research.md R1, R2, R13
 */
final class PanelHomeService
{
    public function __construct(
        private readonly KpiCollector $kpis,
        private readonly UpcomingAppointmentsCollector $upcoming,
        private readonly AttentionItemsCollector $attention,
        private readonly RecentActivityCollector $activity,
        private readonly PanelHomePolicy $policy,
        private readonly PanelHomeMetricsContract $metrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getHome(User $user, string $requestedScope): array
    {
        $tenant = $user->tenant;

        if ($tenant === null) {
            throw new \RuntimeException('User has no tenant context.');
        }

        // Q1: usuário sem permissão de admin força scope='user'.
        $canToggleScope = $this->policy->canSeeClinicScope($user);
        $scopeApplied = ($requestedScope === 'clinic' && $canToggleScope) ? 'clinic' : 'user';

        $cacheKey = "panel_home:{$tenant->id}:{$user->id}:{$scopeApplied}";
        $ttl = (int) config('panel.cache_ttl_seconds', 30);

        $startedAt = microtime(true);
        $cacheHit = true;

        $payload = Cache::remember($cacheKey, $ttl, function () use ($user, $scopeApplied, &$cacheHit) {
            $cacheHit = false;

            return $this->buildPayload($user, $scopeApplied);
        });

        $duration = microtime(true) - $startedAt;
        $this->metrics->recordRequest((string) $tenant->id, $scopeApplied, $cacheHit, $duration);
        if ($cacheHit) {
            $this->metrics->recordCacheHit((string) $tenant->id);
        }

        return [
            'scope_requested' => $requestedScope,
            'scope_applied' => $scopeApplied,
            'can_toggle_scope' => $canToggleScope,
            'generated_at' => Carbon::now()->toIso8601String(),
            'cache_hit' => $cacheHit,
            'sections' => $payload,
        ];
    }

    /**
     * @return array<string, array{data: mixed, error: bool}>
     */
    private function buildPayload(User $user, string $scope): array
    {
        $tenant = $user->tenant;

        return [
            'kpis' => $this->safeRun('kpis', fn () => $this->kpis->collect($tenant, $user, $scope)),
            'upcoming_appointments' => $this->safeRun('upcoming_appointments', fn () => $this->upcoming->collect($tenant, $user, $scope)),
            'attention_items' => $this->safeRun('attention_items', fn () => $this->attention->collect($tenant, $user, $scope)),
            'recent_activity' => $this->safeRun('recent_activity', fn () => $this->activity->collect($tenant, $user, $scope)),
        ];
    }

    /**
     * Executa o callable de um collector com degradação graceful. Em caso
     * de exceção: registra Sentry tag + métrica + retorna `error=true`.
     *
     * @return array{data: mixed, error: bool}
     */
    private function safeRun(string $section, \Closure $fn): array
    {
        $startedAt = microtime(true);

        try {
            $data = $fn();

            $this->metrics->recordSectionDuration($section, microtime(true) - $startedAt);

            return ['data' => $data, 'error' => false];
        } catch (Throwable $e) {
            $this->metrics->recordSectionFailure($section);
            Log::error('panel_home.section_failed', [
                'section' => $section,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (class_exists('\Sentry\State\Scope')) {
                \Sentry\configureScope(function ($scope) use ($section, $e): void {
                    $scope->setTag('panel_home.section_failed', $section);
                    \Sentry\captureException($e);
                });
            }

            return ['data' => null, 'error' => true];
        }
    }
}
