<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\SuperAdmin\Models\AnomalyDetected;
use App\Domain\SuperAdmin\Services\AnomalyDetectorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * **T137 (Fase 8 — Lote B US-12.3)** — Painel de anomalias detectadas (AC-12.3.4).
 *
 * Lista as últimas 50 anomalias com filtro implícito (open vs todas). Action
 * "Detectar agora" força rodada manual dos 4 detectores. Ações inline
 * permitem reconhecer (acknowledge) ou resolver (resolve) anomalia.
 */
class AnomaliesPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Anomalias';

    protected static ?string $title = 'Anomalias Detectadas';

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected string $view = 'filament.pages.anomalies';

    protected static ?string $slug = 'anomalies';

    public bool $onlyOpen = true;

    /**
     * @return list<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('detect-now')
                ->label('Detectar agora')
                ->icon('heroicon-o-magnifying-glass')
                ->color('primary')
                ->action(function (): void {
                    $detected = app(AnomalyDetectorService::class)->detectAll();

                    Notification::make()
                        ->title(sprintf('Detectadas %d anomalia(s) neste ciclo', count($detected)))
                        ->{count($detected) === 0 ? 'success' : 'warning'}()
                        ->send();
                }),

            Action::make('toggle-filter')
                ->label(fn () => $this->onlyOpen ? 'Mostrar histórico' : 'Apenas abertas')
                ->icon('heroicon-o-funnel')
                ->action(function (): void {
                    $this->onlyOpen = ! $this->onlyOpen;
                }),
        ];
    }

    public function acknowledgeAnomaly(int $id): void
    {
        $anomaly = AnomalyDetected::query()->find($id);
        if ($anomaly === null || $anomaly->acknowledged_at !== null) {
            return;
        }

        $anomaly->update([
            'acknowledged_at' => Carbon::now(),
            'acknowledged_by_user_id' => auth()->id(),
        ]);

        Notification::make()->title('Anomalia reconhecida.')->success()->send();
    }

    public function resolveAnomaly(int $id): void
    {
        $anomaly = AnomalyDetected::query()->find($id);
        if ($anomaly === null || $anomaly->resolved_at !== null) {
            return;
        }

        $anomaly->update(['resolved_at' => Carbon::now()]);

        Notification::make()->title('Anomalia marcada como resolvida.')->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $query = AnomalyDetected::query()->orderByDesc('detected_at');

        if ($this->onlyOpen) {
            $query->open();
        }

        $anomalies = $query->limit(50)->get();

        $counts = [
            'open' => AnomalyDetected::query()->open()->count(),
            'critical_open' => AnomalyDetected::query()->open()->critical()->count(),
            'last_24h' => AnomalyDetected::query()
                ->where('detected_at', '>=', Carbon::now()->subDay())
                ->count(),
        ];

        return [
            'anomalies' => $anomalies,
            'counts' => $counts,
            'only_open' => $this->onlyOpen,
        ];
    }
}
