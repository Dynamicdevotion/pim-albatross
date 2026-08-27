<?php

namespace Modules\Taxonomies\Filament\Resources\Taxonomies;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Taxonomies\Filament\Resources\Taxonomies\Pages\CreateTaxonomy;
use Modules\Taxonomies\Filament\Resources\Taxonomies\Pages\EditTaxonomy;
use Modules\Taxonomies\Filament\Resources\Taxonomies\Pages\ListTaxonomies;
use Modules\Taxonomies\Filament\Resources\Taxonomies\RelationManagers\TermsRelationManager;
use Modules\Taxonomies\Filament\Resources\Taxonomies\Schemas\TaxonomyForm;
use Modules\Taxonomies\Filament\Resources\Taxonomies\Tables\TaxonomiesTable;
use Modules\Taxonomies\Models\Taxonomy;

class TaxonomyResource extends Resource
{
    protected static ?string $model = Taxonomy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TaxonomyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxonomiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TermsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxonomies::route('/'),
            'create' => CreateTaxonomy::route('/create'),
            'edit' => EditTaxonomy::route('/{record}/edit'),
        ];
    }
}
