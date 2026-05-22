<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\SuperAdmin\Services\GlobalMetricsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * **T136 (Fase 8 — Lote B US-12.3)** — Painel global de KPIs (AC-12.3.1).
 *
 * Lê snapshot cached pelo cron `super-admin:compute-global-metrics`. Action
 * "Recalcular agora" força recálculo (útil para validação manual após
 * mudanças relevantes).
 *
 * **Gate 5**: nenhuma métrica exibe dados individuais de paciente — só
 * agregados por tenant.
 */
class GlobalMetricsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Métricas Globais';

    protected static ?string $title = 'Métricas Globais da Plataforma';

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected string $view = 'filament.pages.global-metrics';

    protected static ?string $slug = 'global-metrics';

    /**
     * @return list<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('recompute')
                ->label('Recalcular agora')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function (): void {
                    $snapshot = app(GlobalMetricsService::class)->snapshot();
                    Cache::put('super_admin.global_metrics.snapshot', $snapshot, now()->addMinutes(65));

                    Notification::make()
                        ->title('Snapshot recalculado')
                        ->body(sprintf(
                            'MRR R$ %s • Tenants ativos: %d • Churn: %s%%',
                            number_format($snapshot['mrr_cents'] / 100, 2, ',', '.'),
                            $snapshot['tenants_active'],
                            $snapshot['churn_primary']['rate_percent'],
                        ))
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $snapshot = Cache::get('super_admin.global_metrics.snapshot');

        if (! is_array($snapshot)) {
            // Cache vazio (primeiro run) — calcula on-demand.
            $snapshot = app(GlobalMetricsService::class)->snapshot();
            Cache::put('super_admin.global_metrics.snapshot', $snapshot, now()->addMinutes(65));
        }

        return ['snapshot' => $snapshot];
    }
}
