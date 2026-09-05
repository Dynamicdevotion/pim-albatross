<?php

namespace Modules\ImportGestionali\Enums;

/**
 * A field of the product record that an import column can be mapped to.
 *
 * Covers "simple" products and — through {@see ParentSku} — variable products:
 * a row whose `parent_sku` cell is empty is a product of its own (and becomes
 * the variable container if other rows reference it), a row with `parent_sku`
 * filled is a variant of the product with that SKU.
 */
enum TargetField: string
{
    case Sku = 'sku';
    case ParentSku = 'parent_sku';
    case Name = 'name';
    case Description = 'description';
    case Price = 'price';
    case Stock = 'stock';
    case Weight = 'weight';
    case Length = 'length';
    case Width = 'width';
    case Height = 'height';
    case Status = 'status';
    case ImageUrl = 'image_url';
    case GalleryUrls = 'gallery_urls';

    public function label(): string
    {
        return __('pim.import.field.'.$this->value);
    }

    public function isNumeric(): bool
    {
        return in_array($this, [
            self::Price, self::Stock, self::Weight, self::Length, self::Width, self::Height,
        ], true);
    }

    /**
     * value => label, for a Select. The "ignore this column" entry is keyed ''.
     *
     * @return array<string, string>
     */
    public static function selectOptions(): array
    {
        $options = ['' => __('pim.import.field.ignore')];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
