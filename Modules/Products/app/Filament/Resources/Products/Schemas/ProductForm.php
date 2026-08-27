<?php

namespace Modules\Products\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
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
                    ->tabs(fn (): array => Locales::active()
                        ->map(fn (Language $language): Tabs\Tab => Tabs\Tab::make(
                            $language->name.($language->is_base ? ' — base' : ''),
                        )->schema([
                            TextInput::make("translations.{$language->code}.name")
                                ->label('Name')
                                ->maxLength(255)
                                ->required($language->is_base),
                            RichEditor::make("translations.{$language->code}.description")
                                ->label('Description'),
                        ]))
                        ->all()),
            ]);
    }
}
