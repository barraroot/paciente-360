<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use App\Services\Tenant\TenantStateService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Widget com tabela de tenants elegíveis para suspensão (T287).
 *
 * Elegível: status = 'overdue' E overdue_since <= 30 dias atrás.
 */
class SuspensionEligibleTenantsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Clínicas elegíveis para suspensão';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tenant::query()
                    ->where('status', 'overdue')
                    ->where('overdue_since', '<=', now()->subDays(30))
            )
            ->columns([
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),

                TextColumn::make('overdue_since')
                    ->label('Em atraso desde')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('suspend')
                    ->label('Suspender')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Tenant $record): void {
                        app(TenantStateService::class)->suspend($record, 'Suspensão automática por atraso > 30 dias');
                    }),
            ]);
    }
}
