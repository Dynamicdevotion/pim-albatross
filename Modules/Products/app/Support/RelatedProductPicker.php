<?php

namespace Modules\Products\Support;

use Filament\Forms\Components\Select;
use Modules\Localization\Support\Locales;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;

/**
 * A searchable, multi-select product picker for up-sell / cross-sell —
 * deliberately built like {@see ExistingImagePicker} rather than a Filament
 * `relationship()` select: options are resolved on the fly
 * (`getSearchResultsUsing`) and never preloaded, so the field stays cheap
 * regardless of catalogue size. Only `simple`/`variable` products are
 * selectable (never a `variant`, and never the product being edited itself).
 */
final class RelatedProductPicker
{
    private const RESULTS_LIMIT = 50;

    public static function make(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->multiple()
            ->native(false)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search, ?Product $record): array => self::results($search, $record))
            ->getOptionLabelsUsing(fn (array $values): array => self::labels($values));
    }

    /**
     * @return array<int, string> product id => label
     */
    private static function results(string $search, ?Product $record): array
    {
        return self::candidates($record)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('sku', 'like', '%'.$search.'%')
                ->orWhereHas('translations', fn ($query) => $query
                    ->where('language_id', Locales::idFor(Locales::baseCode()))
                    ->where('name', 'like', '%'.$search.'%'))))
            ->limit(self::RESULTS_LIMIT)
            ->get()
            ->mapWithKeys(fn (Product $product): array => [$product->id => self::label($product)])
            ->all();
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private static function labels(array $values): array
    {
        if ($values === []) {
            return [];
        }

        return Product::query()
            ->with('translations')
            ->whereIn('id', $values)
            ->get()
            ->mapWithKeys(fn (Product $product): array => [$product->id => self::label($product)])
            ->all();
    }

    private static function label(Product $product): string
    {
        $name = $product->translate(Locales::baseCode())?->name ?? $product->sku;

        return "{$name} ({$product->sku})";
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    private static function candidates(?Product $record)
    {
        return Product::query()
            ->where('type', '!=', ProductType::Variant->value)
            ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()));
    }
}
