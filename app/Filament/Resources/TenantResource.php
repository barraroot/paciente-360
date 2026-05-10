<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Resources\TenantResource\Pages\ListTenants;
use App\Filament\Resources\TenantResource\Pages\ViewTenant;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Tenant\TenantStateService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resource Filament para gestão de Tenants pelo Super Admin (T283).
 *
 * Usa `withoutGlobalScopes()` para que o Super Admin enxergue todos os
 * tenants, independentemente do tenant resolvido no container.
 * Ações de mudança de estado delegam ao `TenantStateService`.
 */
class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Clínicas';

    protected static ?string $modelLabel = 'Clínica';

    protected static ?string $pluralModelLabel = 'Clínicas';

    /**
     * Retorna todos os tenants sem o global scope de tenant (Super Admin view).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->maxLength(63),

                TextInput::make('name')
                    ->label('Nome da Clínica')
                    ->required()
                    ->maxLength(150),

                TextInput::make('cnpj')
                    ->label('CNPJ')
                    ->disabled()
                    ->maxLength(14),

                TextInput::make('responsible_name')
                    ->label('Responsável')
                    ->maxLength(150),

                TextInput::make('responsible_email')
                    ->label('E-mail do Responsável')
                    ->email()
                    ->maxLength(254),

                TextInput::make('responsible_phone')
                    ->label('Telefone do Responsável')
                    ->maxLength(20),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Ativo',
                        'overdue' => 'Em atraso',
                        'suspended' => 'Suspenso',
                        'cancelled' => 'Cancelado',
                    ])
                    ->disabled(),

                Select::make('plan_id')
                    ->label('Plano')
                    ->options(Plan::query()->pluck('name', 'id'))
                    ->searchable(),

                DateTimePicker::make('trial_ends_at')
                    ->label('Término do Trial'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'trial' => 'info',
                        'active' => 'success',
                        'overdue' => 'warning',
                        'suspended' => 'danger',
                        'cancelled' => 'gray',
                        default => 'secondary',
                    }),

                TextColumn::make('plan.name')
                    ->label('Plano')
                    ->sortable(),

                TextColumn::make('trial_ends_at')
                    ->label('Fim do Trial')
                    ->dateTime('d/m/Y')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Ativo',
                        'overdue' => 'Em atraso',
                        'suspended' => 'Suspenso',
                        'cancelled' => 'Cancelado',
                    ]),

                SelectFilter::make('plan_id')
                    ->label('Plano')
                    ->options(Plan::query()->pluck('name', 'id')),

                Filter::make('eligible_for_suspension')
                    ->label('Elegíveis para suspensão')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', 'overdue')
                        ->where('overdue_since', '<=', now()->subDays(30))
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('suspend')
                    ->label('Suspender')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record): bool => ! in_array($record->status, ['suspended', 'cancelled']))
                    ->action(function (Tenant $record): void {
                        app(TenantStateService::class)->suspend($record);
                    }),

                Action::make('reactivate')
                    ->label('Reativar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record): bool => $record->status === 'suspended')
                    ->action(function (Tenant $record): void {
                        app(TenantStateService::class)->reactivate($record);
                    }),

                Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record): bool => $record->status !== 'cancelled')
                    ->action(function (Tenant $record): void {
                        app(TenantStateService::class)->cancel($record);
                    }),
            ])
            ->persistFiltersInSession()
            ->persistSearchInSession();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'view' => ViewTenant::route('/{record}'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }
}
