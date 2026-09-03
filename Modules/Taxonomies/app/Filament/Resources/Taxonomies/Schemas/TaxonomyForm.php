<?php

namespace Modules\Taxonomies\Filament\Resources\Taxonomies\Schemas;

use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTranslation;

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
                            TextInput::make("translations.{$language->code}.slug")
                                ->label(__('pim.field.slug'))
                                ->maxLength(255)
                                ->helperText(__('pim.helper.slug_from_name_translated'))
                                ->rule(fn (?Taxonomy $record): Closure => static function (string $attribute, $value, Closure $fail) use ($language, $record): void {
                                    $value = trim((string) $value);

                                    if ($value === '') {
                                        return;
                                    }

                                    $taken = TaxonomyTranslation::query()
                                        ->where('language_id', $language->id)
                                        ->where('slug', Str::slug($value))
                                        ->when($record, fn (Builder $q, Taxonomy $record): Builder => $q->where('taxonomy_id', '!=', $record->id))
                                        ->exists();

                                    if ($taken) {
                                        $fail(__('pim.validation.slug_taken'));
                                    }
                                }),
                            RichEditor::make("translations.{$language->code}.description")
                                ->label(__('pim.field.description')),
                        ]))
                        ->all()),
                TextInput::make('slug')
                    ->label(__('pim.field.internal_code'))
                    ->maxLength(255)
                    ->disabled()
                    ->dehydrated()
                    ->helperText(__('pim.helper.internal_code')),
            ]);
    }
}
