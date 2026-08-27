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
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Taxonomies\Models\TaxonomyTerm;

class TermsRelationManager extends RelationManager
{
    protected static string $relationship = 'terms';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->maxLength(255)
                    ->helperText('Leave blank to generate it from the name.'),
                Select::make('parent_id')
                    ->label('Parent')
                    ->searchable()
                    ->options(fn (?TaxonomyTerm $record): array => $this->getOwnerRecord()
                        ->terms()
                        ->when(
                            $record,
                            fn (Builder $query) => $query
                                ->whereKeyNot($record->getKey())
                                ->whereNotIn('id', $record->descendantIds()),
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('children'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('slug')
                    ->toggleable(),
                TextColumn::make('children_count')
                    ->label('Children'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
