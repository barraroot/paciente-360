<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Widget de visão geral da plataforma para o Super Admin (T285).
 *
 * Métricas com cache de 5 minutos para evitar queries pesadas por refresh.
 */
class ActiveTenantsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $activeTenants = Cache::remember('super_admin.widget.active_tenants', 300, function (): int {
            return Tenant::query()->where('status', 'active')->count();
        });

        $mrrCents = Cache::remember('super_admin.widget.mrr', 300, function (): int {
            if (! class_exists(Subscription::class)) {
                return 0;
            }

            return (int) DB::table('subscriptions')
                ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->where('subscriptions.stripe_status', 'active')
                ->sum('plans.base_price_cents');
        });

        $overdueTenants = Cache::remember('super_admin.widget.overdue_tenants', 300, function (): int {
            return Tenant::query()->where('status', 'overdue')->count();
        });

        $trialTenants = Cache::remember('super_admin.widget.trial_tenants', 300, function (): int {
            return Tenant::query()->where('status', 'trial')->count();
        });

        $mrrFormatted = 'R$ '.number_format($mrrCents / 100, 2, ',', '.');

        return [
            Stat::make('Tenants ativos', $activeTenants)
                ->color('success')
                ->icon('heroicon-o-building-office-2'),

            Stat::make('MRR estimado', $mrrFormatted)
                ->color('info')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Tenants em atraso', $overdueTenants)
                ->color('warning')
                ->icon('heroicon-o-exclamation-triangle'),

            Stat::make('Tenants em trial', $trialTenants)
                ->color('primary')
                ->icon('heroicon-o-clock'),
        ];
    }
}
