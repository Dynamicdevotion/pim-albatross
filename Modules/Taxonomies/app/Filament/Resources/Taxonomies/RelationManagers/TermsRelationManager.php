<?php

namespace Modules\Taxonomies\Filament\Resources\Taxonomies\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Localization\Filament\Concerns\HandlesTranslatableName;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Taxonomies\Models\TaxonomyTerm;

class TermsRelationManager extends RelationManager
{
    use HandlesTranslatableName;

    protected static string $relationship = 'terms';

    protected static ?string $recordTitleAttribute = 'slug';

    protected function slugModelClass(): string
    {
        return TaxonomyTerm::class;
    }

    protected function slugScope(): array
    {
        return ['taxonomy_id' => $this->getOwnerRecord()->getKey()];
    }

    public function form(Schema $schema): Schema
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
                    ->helperText(__('pim.helper.slug_from_name')),
                Select::make('parent_id')
                    ->label(__('pim.field.parent'))
                    ->searchable()
                    ->options(fn (?TaxonomyTerm $record): array => $this->getOwnerRecord()
                        ->terms()
                        ->with('translations')
                        ->get()
                        ->when(
                            $record,
                            fn ($terms) => $terms
                                ->whereNotIn('id', [$record->getKey(), ...$record->descendantIds()]),
                        )
                        ->mapWithKeys(fn (TaxonomyTerm $term): array => [$term->id => $term->name])
                        ->all()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('slug')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['translations', 'parent.translations'])
                ->withCount('children'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('pim.field.name'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $q) => $q->where('language_id', Locales::idFor(Locales::baseCode()))
                            ->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('parent.name')
                    ->label(__('pim.field.parent'))
                    ->placeholder('—'),
                TextColumn::make('slug')
                    ->label(__('pim.field.slug'))
                    ->toggleable(),
                TextColumn::make('children_count')
                    ->label(__('pim.field.children')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => $this->extractNameTranslations($data))
                    ->after(fn (Model $record) => $this->saveNameTranslations($record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, TaxonomyTerm $record): array => [
                        ...$data,
                        'translations' => $this->nameTranslationsFor($record),
                    ])
                    ->mutateDataUsing(fn (array $data): array => $this->extractNameTranslations($data))
                    ->after(fn (Model $record) => $this->saveNameTranslations($record)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
