<?php

namespace Modules\Taxonomies\Filament\Resources\Taxonomies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;

class TaxonomyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('translations')
                    ->columnSpanFull()
                    ->tabs(fn (): array => Locales::active()
                        ->map(fn (Language $language): Tabs\Tab => Tabs\Tab::make(
                            $language->name.($language->is_base ? ' — base' : ''),
                        )->schema([
                            TextInput::make("translations.{$language->code}.name")
                                ->label('Name')
                                ->maxLength(255)
                                ->required($language->is_base),
                        ]))
                        ->all()),
                TextInput::make('slug')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank to generate it from the base name.'),
            ]);
    }
}
