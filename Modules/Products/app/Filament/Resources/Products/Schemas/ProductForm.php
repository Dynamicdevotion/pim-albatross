<?php

namespace Modules\Products\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\Localization\Enums\Locale;
use Modules\Taxonomies\Models\TaxonomyTerm;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('external_id')
                    ->label('External ID')
                    ->maxLength(255)
                    ->default(null),
                Select::make('status')
                    ->required()
                    ->default('draft')
                    ->native(false)
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ]),
                Select::make('taxonomyTerms')
                    ->label('Taxonomy terms')
                    ->relationship(
                        'taxonomyTerms',
                        'name',
                        fn (Builder $query): Builder => $query->with('taxonomy'),
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn (TaxonomyTerm $record): string =>
                        "{$record->taxonomy->name}: {$record->name}")
                    ->columnSpanFull(),
                Tabs::make('translations')
                    ->columnSpanFull()
                    ->tabs(array_map(
                        fn (Locale $locale): Tabs\Tab => Tabs\Tab::make(
                            $locale->label().($locale->isDefault() ? ' — base' : ''),
                        )->schema([
                            TextInput::make("translations.{$locale->value}.name")
                                ->label('Name')
                                ->maxLength(255)
                                ->required($locale->isDefault()),
                            RichEditor::make("translations.{$locale->value}.description")
                                ->label('Description'),
                        ]),
                        Locale::cases(),
                    )),
            ]);
    }
}
