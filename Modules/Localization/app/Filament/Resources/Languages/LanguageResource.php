<?php

namespace Modules\Localization\Filament\Resources\Languages;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Localization\Filament\Resources\Languages\Pages\CreateLanguage;
use Modules\Localization\Filament\Resources\Languages\Pages\EditLanguage;
use Modules\Localization\Filament\Resources\Languages\Pages\ListLanguages;
use Modules\Localization\Filament\Resources\Languages\Schemas\LanguageForm;
use Modules\Localization\Filament\Resources\Languages\Tables\LanguagesTable;
use Modules\Localization\Models\Language;

class LanguageResource extends Resource
{
    protected static ?string $model = Language::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('pim.resource.language.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pim.resource.language.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return LanguageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LanguagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLanguages::route('/'),
            'create' => CreateLanguage::route('/create'),
            'edit' => EditLanguage::route('/{record}/edit'),
        ];
    }
}
