<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages\CreatePlan;
use App\Filament\Resources\PlanResource\Pages\EditPlan;
use App\Filament\Resources\PlanResource\Pages\ListPlans;
use App\Models\Plan;
use App\Services\Billing\PlanService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Resource Filament para gestão do catálogo de planos comerciais (T284).
 *
 * Ações de criação/edição delegam ao `PlanService`.
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Planos';

    protected static ?string $modelLabel = 'Plano';

    protected static ?string $pluralModelLabel = 'Planos';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->maxLength(50),

                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(100),

                Textarea::make('description')
                    ->label('Descrição')
                    ->rows(3),

                TextInput::make('base_price_cents')
                    ->label('Preço mensal (centavos)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                TextInput::make('included_professionals')
                    ->label('Profissionais incluídos')
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                TextInput::make('max_users')
                    ->label('Usuários máximos')
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                TextInput::make('included_ai_messages')
                    ->label('Créditos IA mensais')
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                TextInput::make('overage_price_cents')
                    ->label('Preço excedente IA (centavos)')
                    ->numeric()
                    ->minValue(0),

                TextInput::make('stripe_price_id_base')
                    ->label('Stripe Price ID base')
                    ->maxLength(100),

                TextInput::make('stripe_price_id_overage')
                    ->label('Stripe Price ID excedente')
                    ->maxLength(100),

                Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('base_price_cents')
                    ->label('Preço Mensal')
                    ->formatStateUsing(fn (int $state): string => 'R$ '.number_format($state / 100, 2, ',', '.'))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('toggle_active')
                    ->label(fn (Plan $record): string => $record->is_active ? 'Desativar' : 'Ativar')
                    ->icon(fn (Plan $record): string => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->action(function (Plan $record): void {
                        app(PlanService::class)->toggleActive($record);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
