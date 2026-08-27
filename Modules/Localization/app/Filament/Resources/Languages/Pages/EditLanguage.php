<?php

namespace Modules\Localization\Filament\Resources\Languages\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Localization\Filament\Resources\Languages\LanguageResource;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\LanguageContent;

class EditLanguage extends EditRecord
{
    protected static string $resource = LanguageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (Language $record): bool => ! $record->is_base
                    && ! LanguageContent::has($record)),
        ];
    }
}
