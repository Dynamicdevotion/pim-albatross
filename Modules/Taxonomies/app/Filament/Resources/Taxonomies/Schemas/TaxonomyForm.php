<?php

namespace Modules\Taxonomies\Filament\Resources\Taxonomies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TaxonomyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank to generate it from the name.'),
            ]);
    }
}
