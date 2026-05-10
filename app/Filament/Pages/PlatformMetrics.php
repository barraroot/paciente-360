<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveTenantsWidget;
use App\Filament\Widgets\SuspensionEligibleTenantsWidget;
use Filament\Pages\Page;

/**
 * Página de métricas da plataforma para o Super Admin (T285).
 */
class PlatformMetrics extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Métricas';

    protected string $view = 'filament.pages.platform-metrics';

    protected static ?string $slug = 'platform-metrics';

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            ActiveTenantsWidget::class,
            SuspensionEligibleTenantsWidget::class,
        ];
    }
}
