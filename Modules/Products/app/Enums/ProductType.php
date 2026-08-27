<?php

namespace Modules\Products\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The shape of a product row.
 *
 *  - Simple:   an independent product (the historical default).
 *  - Variable: a container that groups variants; carries the shared name,
 *              description and common terms but has no own price or stock.
 *  - Variant:  a child of a Variable product with its own sku, prices, stock
 *              and distinguishing terms.
 */
enum ProductType: string implements HasColor, HasLabel
{
    case Simple = 'simple';
    case Variable = 'variable';
    case Variant = 'variant';

    public function getLabel(): string
    {
        return __("pim.option.type.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Simple => 'gray',
            self::Variable => 'info',
            self::Variant => 'warning',
        };
    }
}
