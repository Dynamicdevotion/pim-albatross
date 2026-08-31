<?php

namespace Modules\Dashboard\Filament\Widgets;

use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Modules\Localization\Support\Locales;
use Modules\Products\Filament\Resources\Products\ProductResource;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductListQuery;

/**
 * The most recently created top-level products that still have no main image —
 * an actionable "finish these" list. Each row links to the product form.
 */
class ProductsMissingImage extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('pim.dashboard.missing_image.heading'))
            ->query(
                ProductListQuery::base()
                    ->whereDoesntHave('media', fn (Builder $query) => $query->where('collection_name', 'main_image'))
                    ->latest()
                    ->limit(8)
            )
            ->paginated(false)
            ->emptyStateHeading(__('pim.dashboard.missing_image.empty'))
            ->columns([
                ImageColumn::make('placeholder')
                    ->label('')
                    ->state(fn (): string => asset('images/placeholder-product.svg'))
                    ->imageSize(32)
                    ->square(),
                TextColumn::make('name_base')
                    ->label(__('pim.field.name'))
                    ->state(fn (Product $record): ?string => $record->translate(Locales::baseCode())?->name)
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('sku')
                    ->label(__('pim.field.sku')),
                TextColumn::make('created_at')
                    ->label(__('pim.dashboard.col.created'))
                    ->date('d/m/Y'),
            ])
            ->recordUrl(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]));
    }
}
