<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\SuperAdmin\Models\ImpersonateSession;
use App\Domain\SuperAdmin\Services\ImpersonateService;
use App\Filament\Resources\ImpersonateSessionResource\Pages\ListImpersonateSessions;
use App\Filament\Resources\ImpersonateSessionResource\Pages\ViewImpersonateSession;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * **T102 (Fase 8 — Lote B US-12.1)** — Listagem read-only de sessões de impersonate.
 *
 * Super Admin vê o histórico global; pode "encerrar" sessão ativa (caso seja
 * a dele E ainda esteja aberta) via action `end`.
 */
class ImpersonateSessionResource extends Resource
{
    protected static ?string $model = ImpersonateSession::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'Sessões de Impersonate';

    protected static string|UnitEnum|null $navigationGroup = 'Auditoria & Suporte';

    protected static ?string $modelLabel = 'Sessão de impersonate';

    protected static ?string $pluralModelLabel = 'Sessões de impersonate';

    /**
     * Não permite criação direta — sessões são criadas via Action na TenantResource.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('superAdmin.name')->label('Super Admin')->searchable()->sortable(),
                TextColumn::make('tenant.slug')->label('Tenant')->searchable()->sortable(),
                TextColumn::make('started_at')->label('Início')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('ended_at')->label('Fim')->dateTime('d/m/Y H:i')->sortable()
                    ->placeholder('Em andamento'),
                TextColumn::make('duration_seconds')->label('Duração (s)')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : (string) $state)
                    ->sortable(),
                TextColumn::make('screens_visited_count')->label('Telas visitadas')->sortable(),
                TextColumn::make('reason')->label('Motivo')->limit(60)->wrap(),
            ])
            ->filters([
                SelectFilter::make('active_status')
                    ->label('Status')
                    ->options([
                        'active' => 'Em andamento',
                        'ended' => 'Encerrada',
                    ])
                    ->query(function ($query, array $data): void {
                        if (($data['value'] ?? null) === 'active') {
                            $query->whereNull('ended_at');
                        } elseif (($data['value'] ?? null) === 'ended') {
                            $query->whereNotNull('ended_at');
                        }
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('end')
                    ->label('Encerrar sessão')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ImpersonateSession $record): bool => $record->isActive() && $record->super_admin_id === auth()->id())
                    ->action(function (ImpersonateSession $record): void {
                        app(ImpersonateService::class)->end($record);
                        Notification::make()->title('Sessão de impersonate encerrada.')->success()->send();
                    }),
            ])
            ->defaultSort('started_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImpersonateSessions::route('/'),
            'view' => ViewImpersonateSession::route('/{record}'),
        ];
    }
}
