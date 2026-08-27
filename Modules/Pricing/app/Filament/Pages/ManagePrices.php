<?php

namespace Modules\Pricing\Filament\Pages;

use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Models\ProductPrice;
use Modules\Products\Models\Product;

/**
 * One screen to edit the price of every product in a chosen price list without
 * opening each product. Pick a list at the top; edit prices inline or in bulk.
 */
class ManagePrices extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Bulk price editing';

    protected static ?string $title = 'Bulk price editing';

    protected static ?string $slug = 'prices';

    protected string $view = 'pricing::filament.pages.manage-prices';

    public ?int $priceListId = null;

    public static function canAccess(): bool
    {
        return PriceList::query()->exists();
    }

    public function mount(): void
    {
        $this->priceListId = PriceList::query()->where('is_default', true)->value('id')
            ?? PriceList::query()->value('id');
    }

    /**
     * @return array<int, string>
     */
    public function priceListOptions(): array
    {
        return PriceList::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function table(Table $table): Table
    {
        $listId = $this->priceListId;

        return $table
            ->query(Product::query()->with([
                'translations',
                'prices' => fn ($query) => $query->where('price_list_id', $listId),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->getStateUsing(fn (Product $record): ?string => $record->translate(Locales::baseCode())?->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $q) => $q->where('language_id', Locales::idFor(Locales::baseCode()))
                            ->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextInputColumn::make('price')
                    ->label('Price')
                    ->type('number')
                    ->extraInputAttributes(['step' => '0.01', 'min' => '0', 'inputmode' => 'decimal'])
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->getStateUsing(fn (Product $record) => $record->prices->first()?->price)
                    ->updateStateUsing(fn (Product $record, $state) => $this->writePrice($record->getKey(), $state)),
            ])
            ->filters([
                TernaryFilter::make('has_price')
                    ->label('Price')
                    ->placeholder('All products')
                    ->trueLabel('With a price')
                    ->falseLabel('Without a price')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas(
                            'prices',
                            fn (Builder $q) => $q->where('price_list_id', $listId),
                        ),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave(
                            'prices',
                            fn (Builder $q) => $q->where('price_list_id', $listId),
                        ),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('setPrice')
                        ->label('Set price')
                        ->icon('heroicon-o-currency-euro')
                        ->schema([
                            TextInput::make('price')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->extraInputAttributes(['step' => '0.01']),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (Product $record) => $this->writePrice($record->getKey(), $data['price']));

                            Notification::make()
                                ->title($records->count().' price(s) updated')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->paginated([25, 50, 100]);
    }

    protected function writePrice(int $productId, mixed $state): void
    {
        $state = ($state === '' || $state === null) ? null : round((float) $state, 2);

        if ($state === null) {
            ProductPrice::query()
                ->where('product_id', $productId)
                ->where('price_list_id', $this->priceListId)
                ->delete();

            return;
        }

        ProductPrice::updateOrCreate(
            ['product_id' => $productId, 'price_list_id' => $this->priceListId],
            ['price' => $state],
        );
    }
}
