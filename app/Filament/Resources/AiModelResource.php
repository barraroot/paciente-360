<?php

namespace App\Filament\Resources;

use App\Domain\Ai\Model\Models\AiModel;
use App\Filament\Resources\AiModelResource\Pages\CreateAiModel;
use App\Filament\Resources\AiModelResource\Pages\EditAiModel;
use App\Filament\Resources\AiModelResource\Pages\ListAiModels;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Catálogo GLOBAL de modelos de IA (plataforma). Apenas super-admin.
 * Tenants selecionam modelos ativos via API; não criam/editam aqui.
 */
class AiModelResource extends Resource
{
    protected static ?string $model = AiModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|UnitEnum|null $navigationGroup = 'IA & Plataforma';

    protected static ?string $navigationLabel = 'Modelos de IA';

    protected static ?string $modelLabel = 'Modelo de IA';

    protected static ?string $pluralModelLabel = 'Modelos de IA';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(120),

            Select::make('provider')
                ->label('Provedor')
                ->options([
                    'anthropic' => 'Anthropic',
                    'openai' => 'OpenAI',
                    'gemini' => 'Google Gemini',
                    'voyageai' => 'Voyage AI',
                    'cohere' => 'Cohere',
                    'mistral' => 'Mistral',
                ])
                ->required(),

            TextInput::make('internal_identifier')
                ->label('Identificador do provedor')
                ->helperText('Ex.: claude-haiku-4-5-20251001')
                ->required()
                ->maxLength(120)
                ->unique(ignoreRecord: true),

            Textarea::make('description')
                ->label('Descrição')
                ->rows(2)
                ->columnSpanFull(),

            Toggle::make('supports_embedding')
                ->label('Usado para embeddings (RAG)')
                ->live(),

            TextInput::make('embedding_dimension')
                ->label('Dimensão do embedding')
                ->numeric()
                ->visible(fn (Get $get): bool => (bool) $get('supports_embedding')),

            Toggle::make('is_active')
                ->label('Ativo')
                ->default(true)
                ->helperText('Inativo não pode ser selecionado em novas personas.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('provider')->label('Provedor')->badge()->sortable(),
                TextColumn::make('internal_identifier')->label('Identificador')->searchable()->copyable(),
                IconColumn::make('supports_embedding')->label('Embedding')->boolean(),
                IconColumn::make('is_active')->label('Ativo')->boolean()->sortable(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiModels::route('/'),
            'create' => CreateAiModel::route('/create'),
            'edit' => EditAiModel::route('/{record}/edit'),
        ];
    }
}
