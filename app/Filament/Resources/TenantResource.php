<?php

namespace App\Filament\Resources;

use App\Domain\SuperAdmin\Services\ImpersonateService;
use App\Domain\SuperAdmin\Services\TenantLifecycleService;
use App\Filament\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Resources\TenantResource\Pages\ListTenants;
use App\Filament\Resources\TenantResource\Pages\ViewTenant;
use App\Models\Plan;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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

                // T099 (Fase 8 — Lote B US-12.1) — AC-12.1.3 com motivo ≥10 chars + tracking SA.
                Action::make('suspend')
                    ->label('Suspender')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->modalHeading('Suspender tenant')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Motivo (mínimo 10 caracteres)')
                            ->required()
                            ->minLength(10)
                            ->maxLength(2000)
                            ->rows(3),
                    ])
                    ->visible(fn (Tenant $record): bool => ! in_array($record->status, ['suspended', 'cancelled']))
                    ->action(function (Tenant $record, array $data): void {
                        app(TenantLifecycleService::class)->suspend($record, auth()->user(), $data['reason']);
                        Notification::make()->title("Tenant {$record->slug} suspenso.")->danger()->send();
                    }),

                // T099 — AC-12.1.4
                Action::make('reactivate')
                    ->label('Reativar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Reativar tenant')
                    ->visible(fn (Tenant $record): bool => $record->status === 'suspended')
                    ->action(function (Tenant $record): void {
                        app(TenantLifecycleService::class)->reactivate($record, auth()->user());
                        Notification::make()->title("Tenant {$record->slug} reativado.")->success()->send();
                    }),

                // T099 — AC-12.1.10 com motivo ≥10 chars + retention policy Q20
                Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading('Cancelar tenant')
                    ->modalDescription('Atenção: cancela o tenant e inicia a retenção diferenciada Q20 (config 30d, paciente 90d, audit 1a, controladas 2a, billing 5a).')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Motivo do cancelamento (mínimo 10 caracteres)')
                            ->required()
                            ->minLength(10)
                            ->maxLength(2000)
                            ->rows(3),
                    ])
                    ->visible(fn (Tenant $record): bool => $record->status !== 'cancelled')
                    ->action(function (Tenant $record, array $data): void {
                        app(TenantLifecycleService::class)->cancel($record, auth()->user(), $data['reason']);
                        Notification::make()->title("Tenant {$record->slug} cancelado.")->danger()->send();
                    }),

                // T099 — AC-12.1.5 Impersonate com motivo ≥10 chars + banner persistente
                Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->modalHeading('Iniciar sessão de impersonate')
                    ->modalDescription('Você visualizará dados como suporte (Q19 — acesso total + audit por tela). Todas as telas visitadas serão registradas.')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Motivo (ticket, incidente — mínimo 10 caracteres)')
                            ->required()
                            ->minLength(10)
                            ->maxLength(2000)
                            ->rows(3)
                            ->placeholder('Ex.: Ticket #1234 — paciente reporta erro ao salvar agendamento'),
                    ])
                    ->visible(fn (Tenant $record): bool => $record->status !== 'cancelled')
                    ->action(function (Tenant $record, array $data): void {
                        try {
                            app(ImpersonateService::class)->start(
                                superAdmin: auth()->user(),
                                tenant: $record,
                                ipAddress: request()->ip() ?? '0.0.0.0',
                                userAgent: request()->userAgent(),
                                reason: $data['reason'],
                            );
                            Notification::make()
                                ->title("Sessão de impersonate iniciada em {$record->slug}.")
                                ->body('Banner amarelo permanecerá visível até você encerrar a sessão.')
                                ->warning()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Falha ao iniciar impersonate')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
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
