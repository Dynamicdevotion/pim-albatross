<?php

namespace Modules\ImportGestionali\Enums;

use Modules\ImportGestionali\Support\MappingTarget;

/**
 * One of the per-language columns on `product_translations`. Paired with a
 * language id (see {@see MappingTarget})
 * to form one entry of the mapping step's dynamic "Traduzioni" group — one
 * entry per active language × translatable field, read from
 * `Locales::active()` at runtime, never hardcoded.
 */
enum TranslatableField: string
{
    case Name = 'name';
    case Description = 'description';
    case MetaTitle = 'meta_title';
    case MetaDescription = 'meta_description';
    case Slug = 'slug';

    public function label(): string
    {
        return __('pim.import.field.'.$this->value);
    }
}
