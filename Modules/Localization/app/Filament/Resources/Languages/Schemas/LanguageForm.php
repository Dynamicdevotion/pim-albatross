<?php

namespace Modules\Localization\Filament\Resources\Languages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LanguageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->helperText('ISO 639-1, lowercase (e.g. "it", "en").')
                    ->required()
                    ->maxLength(5)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): string => strtolower(trim((string) $state))),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Toggle::make('active'),
            ]);
    }
}
