<?php

namespace App\Filament\Resources\Prescriptions;

use App\Domain\Prescription\Prescription\Prescription;
use App\Domain\Prescription\Prescription\PrescriptionStatus;
use App\Domain\Prescription\Prescription\PrescriptionType;
use App\Filament\Resources\Prescriptions\Pages\ListPrescriptions;
use App\Filament\Resources\Prescriptions\Pages\ViewPrescription;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * T169 — Filament Resource de receitas para o Super Admin.
 *
 * - Somente leitura (sem Create, Edit, Delete).
 * - `withoutGlobalScopes()` para listar receitas de todos os tenants.
 * - Audit log no ViewAction::after (acesso de super admin é registrado).
 * - Filtros: tenant, tipo, status, faixa de vencimento.
 *
 * @see specs/007-gestao-receituario/research.md §7.4
 */
class PrescriptionResource extends Resource
{
    protected static ?string $model = Prescription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Receituários';

    protected static ?string $modelLabel = 'Receituário';

    protected static ?string $pluralModelLabel = 'Receituários';

    protected static string|\UnitEnum|null $navigationGroup = 'Clínica';

    /**
     * Super Admin enxerga receitas de TODOS os tenants (sem global scope).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações Gerais')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID'),

                        TextEntry::make('tenant.name')
                            ->label('Clínica'),

                        TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->color(fn (PrescriptionType $state): string => match ($state) {
                                PrescriptionType::Common => 'info',
                                PrescriptionType::Special => 'warning',
                                PrescriptionType::Controlled => 'danger',
                            })
                            ->formatStateUsing(fn (PrescriptionType $state): string => match ($state) {
                                PrescriptionType::Common => 'Comum',
                                PrescriptionType::Special => 'Especial',
                                PrescriptionType::Controlled => 'Controlada',
                            }),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (PrescriptionStatus $state): string => match ($state) {
                                PrescriptionStatus::Active => 'success',
                                PrescriptionStatus::Cancelled => 'danger',
                                PrescriptionStatus::Superseded => 'gray',
                            })
                            ->formatStateUsing(fn (PrescriptionStatus $state): string => match ($state) {
                                PrescriptionStatus::Active => 'Ativa',
                                PrescriptionStatus::Cancelled => 'Cancelada',
                                PrescriptionStatus::Superseded => 'Substituída',
                            }),

                        TextEntry::make('patient.nome')
                            ->label('Paciente'),

                        TextEntry::make('professional.name')
                            ->label('Profissional'),

                        TextEntry::make('issued_at')
                            ->label('Data de Emissão')
                            ->date('d/m/Y'),

                        TextEntry::make('expires_at')
                            ->label('Data de Vencimento')
                            ->date('d/m/Y'),

                        TextEntry::make('notes')
                            ->label('Observações')
                            ->columnSpanFull()
                            ->placeholder('Sem observações'),
                    ]),

                Section::make('Medicamentos')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('medication_name')
                                    ->label('Medicamento'),

                                TextEntry::make('posology')
                                    ->label('Posologia'),

                                TextEntry::make('concentration')
                                    ->label('Concentração')
                                    ->placeholder('-'),

                                TextEntry::make('pharmaceutical_form')
                                    ->label('Forma Farmacêutica')
                                    ->placeholder('-'),

                                TextEntry::make('quantity')
                                    ->label('Quantidade')
                                    ->placeholder('-'),

                                TextEntry::make('treatment_duration')
                                    ->label('Duração do Tratamento')
                                    ->placeholder('-'),
                            ])
                            ->columns(3),
                    ]),

                Section::make('Cancelamento')
                    ->hidden(fn (Prescription $record): bool => ! $record->isCancelled())
                    ->schema([
                        TextEntry::make('cancellation_reason_category')
                            ->label('Categoria')
                            ->formatStateUsing(fn ($state): string => $state?->value ?? '-'),

                        TextEntry::make('cancellation_reason')
                            ->label('Motivo')
                            ->columnSpanFull(),

                        TextEntry::make('cancelled_at')
                            ->label('Cancelado em')
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('cancelledBy.name')
                            ->label('Cancelado por'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('tenant.name')
                    ->label('Clínica')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('patient.nome')
                    ->label('Paciente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('professional.name')
                    ->label('Profissional')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (PrescriptionType $state): string => match ($state) {
                        PrescriptionType::Common => 'info',
                        PrescriptionType::Special => 'warning',
                        PrescriptionType::Controlled => 'danger',
                    })
                    ->formatStateUsing(fn (PrescriptionType $state): string => match ($state) {
                        PrescriptionType::Common => 'Comum',
                        PrescriptionType::Special => 'Especial',
                        PrescriptionType::Controlled => 'Controlada',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (PrescriptionStatus $state): string => match ($state) {
                        PrescriptionStatus::Active => 'success',
                        PrescriptionStatus::Cancelled => 'danger',
                        PrescriptionStatus::Superseded => 'gray',
                    })
                    ->formatStateUsing(fn (PrescriptionStatus $state): string => match ($state) {
                        PrescriptionStatus::Active => 'Ativa',
                        PrescriptionStatus::Cancelled => 'Cancelada',
                        PrescriptionStatus::Superseded => 'Substituída',
                    }),

                TextColumn::make('issued_at')
                    ->label('Emissão')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tenant_id')
                    ->label('Clínica')
                    ->relationship('tenant', 'name'),

                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'common' => 'Comum',
                        'special' => 'Especial',
                        'controlled' => 'Controlada',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativa',
                        'cancelled' => 'Cancelada',
                        'superseded' => 'Substituída',
                    ]),

                Filter::make('expires_after')
                    ->label('Vence após')
                    ->form([
                        DatePicker::make('expires_after')
                            ->label('Vence após'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['expires_after'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('expires_at', '>=', $date))
                    ),

                Filter::make('expires_before')
                    ->label('Vence antes')
                    ->form([
                        DatePicker::make('expires_before')
                            ->label('Vence antes'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['expires_before'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('expires_at', '<=', $date))
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->after(function (Prescription $record): void {
                        try {
                            AuditLog::create([
                                'tenant_id' => null,
                                'user_id' => auth()->id(),
                                'actor_type' => 'user',
                                'action' => 'super_admin.prescription.viewed',
                                'auditable_type' => Prescription::class,
                                'auditable_id' => $record->id,
                                'payload' => [
                                    'prescription_id' => $record->id,
                                    'tenant_id' => $record->tenant_id,
                                ],
                                'ip' => request()->ip(),
                                'user_agent' => request()->userAgent(),
                            ]);
                        } catch (\Throwable) {
                            // Audit failures never block UI
                        }
                    }),
            ])
            ->defaultSort('expires_at')
            ->persistFiltersInSession()
            ->persistSearchInSession();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrescriptions::route('/'),
            'view' => ViewPrescription::route('/{record}'),
        ];
    }
}
