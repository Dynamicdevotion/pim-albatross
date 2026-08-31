<?php

namespace Modules\Products\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Modules\Products\Filament\Resources\Products\Tables\ProductsTable;

/**
 * A small extension seam for the products list: other modules register extra
 * per-row and bulk actions here from their service provider, and
 * {@see ProductsTable}
 * folds them into the table. The Products module stays unaware of who is
 * contributing (dependency inversion) — today it is the WooSync module's
 * "Sincronizza con WooCommerce" action, gated behind its own feature flag.
 *
 * Factories are stored (not built actions) so each table render gets a fresh
 * instance, matching how Filament expects actions to be created.
 */
final class ProductRowActions
{
    /** @var array<int, Closure(): Action> */
    private static array $record = [];

    /** @var array<int, Closure(): BulkAction> */
    private static array $bulk = [];

    /**
     * @param  Closure(): Action  $factory
     */
    public static function registerRecord(Closure $factory): void
    {
        self::$record[] = $factory;
    }

    /**
     * @param  Closure(): BulkAction  $factory
     */
    public static function registerBulk(Closure $factory): void
    {
        self::$bulk[] = $factory;
    }

    /**
     * @return list<Action>
     */
    public static function record(): array
    {
        return array_map(static fn (Closure $factory): Action => $factory(), self::$record);
    }

    /**
     * @return list<BulkAction>
     */
    public static function bulk(): array
    {
        return array_map(static fn (Closure $factory): BulkAction => $factory(), self::$bulk);
    }

    /**
     * Drop every registration — for test isolation only.
     */
    public static function flush(): void
    {
        self::$record = [];
        self::$bulk = [];
    }
}
