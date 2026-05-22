<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Services;

use App\Domain\SuperAdmin\Events\ImpersonateEncerrado;
use App\Domain\SuperAdmin\Events\ImpersonateIniciado;
use App\Domain\SuperAdmin\Events\ImpersonateTelaVisitada;
use App\Domain\SuperAdmin\Models\ImpersonateSession;
use App\Domain\SuperAdmin\Models\SuperAdminAuditScreen;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use RuntimeException;

/**
 * **T094 (Fase 8 — Lote B US-12.1)** — Gerencia sessões de impersonate (Q19).
 *
 * Operações:
 *   - `start(SA, Tenant, ipAddress, userAgent, reason)` — AC-12.1.5; gate
 *     reason ≥10 chars; PARTIAL UNIQUE impede 2 sessões simultâneas
 *     pelo mesmo Super Admin.
 *   - `end(session)` — AC-12.1.6; calcula `duration_seconds` automaticamente.
 *   - `recordScreenVisit(session, route, path, method, ip, query)` —
 *     Gate 7: audit granular por tela.
 *   - `activeSessionFor(SA)` — lookup convencional para middleware.
 */
final class ImpersonateService
{
    public function start(
        User $superAdmin,
        Tenant $tenant,
        string $ipAddress,
        ?string $userAgent,
        string $reason,
        string $scope = 'full',
    ): ImpersonateSession {
        if (strlen(trim($reason)) < 10) {
            throw new InvalidArgumentException('Motivo do impersonate deve ter no mínimo 10 caracteres.');
        }

        return DB::transaction(function () use ($superAdmin, $tenant, $ipAddress, $userAgent, $reason, $scope): ImpersonateSession {
            // Garante 1 sessão ativa por Super Admin (PARTIAL UNIQUE no DB também).
            $existing = ImpersonateSession::query()
                ->bySuperAdmin($superAdmin->id)
                ->active()
                ->lockForUpdate()
                ->first();

            if ($existing instanceof ImpersonateSession) {
                throw new RuntimeException(
                    "Super Admin #{$superAdmin->id} já tem sessão ativa (#{$existing->id}). Encerre antes de iniciar nova.",
                );
            }

            $session = ImpersonateSession::query()->create([
                'super_admin_id' => $superAdmin->id,
                'tenant_id' => $tenant->id,
                'started_at' => Carbon::now(),
                'ended_at' => null,
                'duration_seconds' => null,
                'scope' => $scope,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'screens_visited_count' => 0,
                'reason' => trim($reason),
            ]);

            Event::dispatch(new ImpersonateIniciado(
                sessionId: $session->id,
                superAdminId: $superAdmin->id,
                tenantId: $tenant->id,
                startedAt: $session->started_at,
                scope: $scope,
                ipAddress: $ipAddress,
                reason: trim($reason),
            ));

            return $session;
        });
    }

    public function end(ImpersonateSession $session): ImpersonateSession
    {
        if (! $session->isActive()) {
            return $session; // idempotente
        }

        return DB::transaction(function () use ($session): ImpersonateSession {
            $now = Carbon::now();
            $duration = (int) $session->started_at->diffInSeconds($now, true);

            $session->update([
                'ended_at' => $now,
                'duration_seconds' => $duration,
            ]);

            Event::dispatch(new ImpersonateEncerrado(
                sessionId: $session->id,
                superAdminId: $session->super_admin_id,
                tenantId: $session->tenant_id,
                endedAt: $now,
                durationSeconds: $duration,
                screensVisitedCount: $session->screens_visited_count,
            ));

            return $session->refresh();
        });
    }

    /**
     * Registra audit granular de tela visitada (Gate 7). Incrementa contador na sessão.
     *
     * @param  array<string, mixed>|null  $queryParams
     */
    public function recordScreenVisit(
        ImpersonateSession $session,
        string $route,
        string $path,
        string $method,
        string $ipAddress,
        ?array $queryParams = null,
    ): SuperAdminAuditScreen {
        if (! $session->isActive()) {
            throw new RuntimeException('Não é possível registrar tela em sessão encerrada.');
        }

        return DB::transaction(function () use ($session, $route, $path, $method, $ipAddress, $queryParams): SuperAdminAuditScreen {
            $now = Carbon::now();

            $screen = SuperAdminAuditScreen::query()->create([
                'impersonate_session_id' => $session->id,
                'route' => $route,
                'path' => $path,
                'method' => $method,
                'visited_at' => $now,
                'ip_address' => $ipAddress,
                'query_params' => $queryParams,
            ]);

            $session->increment('screens_visited_count');

            Event::dispatch(new ImpersonateTelaVisitada(
                sessionId: $session->id,
                superAdminId: $session->super_admin_id,
                tenantId: $session->tenant_id,
                route: $route,
                path: $path,
                method: $method,
                visitedAt: $now,
                ipAddress: $ipAddress,
            ));

            return $screen;
        });
    }

    public function activeSessionFor(User $superAdmin): ?ImpersonateSession
    {
        return ImpersonateSession::query()
            ->bySuperAdmin($superAdmin->id)
            ->active()
            ->first();
    }
}
