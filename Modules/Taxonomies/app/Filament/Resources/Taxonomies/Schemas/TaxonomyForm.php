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
                            $language->name.($language->is_base ? __('pim.field.base_suffix') : ''),
                        )->schema([
                            TextInput::make("translations.{$language->code}.name")
                                ->label(__('pim.field.name'))
                                ->maxLength(255)
                                ->required($language->is_base),
                        ]))
                        ->all()),
                TextInput::make('slug')
                    ->label(__('pim.field.slug'))
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText(__('pim.helper.slug_from_name')),
            ]);
    }
}
