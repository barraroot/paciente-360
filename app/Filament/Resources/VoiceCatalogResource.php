<?php

namespace App\Filament\Resources;

use App\Domain\Ai\Voice\Models\VoiceCatalogEntry;
use App\Filament\Resources\VoiceCatalogResource\Pages\CreateVoiceCatalog;
use App\Filament\Resources\VoiceCatalogResource\Pages\EditVoiceCatalog;
use App\Filament\Resources\VoiceCatalogResource\Pages\ListVoiceCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * **T160 (Fase 18 — US5, FR-037c, Q-clarify-4=B)** — catálogo GLOBAL de vozes
 * TTS curado pelo super-admin. Tenants escolhem voz na Persona via API
 * (/api/v1/ai/voices) — NÃO criam/editam entradas.
 *
 * `provider_voice_id` é o ID técnico do provedor (ex.: ElevenLabs); fica
 * SOMENTE neste painel (FR-037c — não vai pro tenant).
 *
 * UNIQUE parcial em DB garante 1 `is_system_default=true` por language —
 * a action "Marcar como padrão do sistema" desfaz a anterior dentro de
 * uma transação para evitar violação.
 */
class VoiceCatalogResource extends Resource
{
    protected static ?string $model = VoiceCatalogEntry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-speaker-wave';

    protected static string|UnitEnum|null $navigationGroup = 'IA & Plataforma';

    protected static ?string $navigationLabel = 'Catálogo de Vozes';

    protected static ?string $modelLabel = 'Voz';

    protected static ?string $pluralModelLabel = 'Vozes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('provider')
                ->label('Provedor')
                ->options([
                    'elevenlabs' => 'ElevenLabs',
                ])
                ->required()
                ->default('elevenlabs'),

            TextInput::make('provider_voice_id')
                ->label('Identificador do provedor')
                ->helperText('ID técnico no provedor — não exibido aos tenants.')
                ->required()
                ->maxLength(120),

            TextInput::make('display_name')
                ->label('Nome de exibição')
                ->helperText('Visto pelos admins de clínica ao escolher voz.')
                ->required()
                ->maxLength(120),

            Select::make('gender')
                ->label('Gênero')
                ->options([
                    'f' => 'Feminino',
                    'm' => 'Masculino',
                    'neutral' => 'Neutro',
                ])
                ->required(),

            Select::make('tone')
                ->label('Tom')
                ->options([
                    'acolhedor' => 'Acolhedor',
                    'profissional' => 'Profissional',
                    'energico' => 'Enérgico',
                    'calmo' => 'Calmo',
                ])
                ->required(),

            Select::make('language')
                ->label('Idioma')
                ->options([
                    'pt-BR' => 'Português (Brasil)',
                ])
                ->required()
                ->default('pt-BR'),

            FileUpload::make('preview_audio_path')
                ->label('Áudio de prévia (MP3)')
                ->helperText('Curto (5–10s) — usado para o admin escutar antes de escolher.')
                ->disk('public')
                ->directory('voice-previews')
                ->visibility('public')
                ->acceptedFileTypes(['audio/mpeg', 'audio/mp3'])
                ->maxSize(2048),

            Toggle::make('is_active')
                ->label('Ativa')
                ->default(true)
                ->helperText('Vozes inativas não aparecem para os tenants escolherem.'),

            Toggle::make('is_system_default')
                ->label('Padrão do sistema')
                ->disabled()
                ->helperText('Use a action "Marcar como padrão" na listagem — ela cuida do unset da anterior.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('provider')->label('Provedor')->badge(),
                TextColumn::make('gender')->label('Gênero')->badge()->sortable(),
                TextColumn::make('tone')->label('Tom')->badge()->sortable(),
                TextColumn::make('language')->label('Idioma')->sortable(),
                IconColumn::make('is_active')->label('Ativa')->boolean()->sortable(),
                IconColumn::make('is_system_default')->label('Padrão')->boolean()->sortable(),
            ])
            ->filters([
                SelectFilter::make('language')->options([
                    'pt-BR' => 'pt-BR',
                ]),
                SelectFilter::make('gender')->options([
                    'f' => 'Feminino',
                    'm' => 'Masculino',
                    'neutral' => 'Neutro',
                ]),
                SelectFilter::make('tone')->options([
                    'acolhedor' => 'Acolhedor',
                    'profissional' => 'Profissional',
                    'energico' => 'Enérgico',
                    'calmo' => 'Calmo',
                ]),
                TernaryFilter::make('is_active')->label('Ativa'),
            ])
            ->recordActions([
                Action::make('toggleActive')
                    ->label(fn (VoiceCatalogEntry $record): string => $record->is_active ? 'Desativar' : 'Ativar')
                    ->icon(fn (VoiceCatalogEntry $record): Heroicon => $record->is_active ? Heroicon::EyeSlash : Heroicon::Eye)
                    ->requiresConfirmation(fn (VoiceCatalogEntry $record): bool => $record->is_active)
                    ->modalHeading('Desativar voz')
                    ->modalDescription('Personas que ainda apontam para esta voz vão cair no fallback (default do tenant ou do sistema).')
                    ->action(function (VoiceCatalogEntry $record): void {
                        $record->forceFill(['is_active' => ! $record->is_active])->save();
                    }),

                Action::make('markAsDefault')
                    ->label('Marcar como padrão do sistema')
                    ->icon(Heroicon::Star)
                    ->visible(fn (VoiceCatalogEntry $record): bool => $record->is_active && ! $record->is_system_default)
                    ->requiresConfirmation()
                    ->modalHeading('Marcar como padrão do sistema')
                    ->modalDescription('Há somente uma voz padrão por idioma. A voz padrão atual será desmarcada.')
                    ->action(function (VoiceCatalogEntry $record): void {
                        DB::transaction(function () use ($record): void {
                            VoiceCatalogEntry::query()
                                ->where('language', $record->language)
                                ->where('id', '!=', $record->id)
                                ->where('is_system_default', true)
                                ->update(['is_system_default' => false]);

                            $record->forceFill(['is_system_default' => true])->save();
                        });
                    }),
            ])
            ->defaultSort('display_name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVoiceCatalog::route('/'),
            'create' => CreateVoiceCatalog::route('/create'),
            'edit' => EditVoiceCatalog::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'display_name';
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record?->display_name;
    }
}
