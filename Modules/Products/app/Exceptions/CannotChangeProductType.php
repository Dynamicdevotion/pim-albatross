<?php

namespace Modules\Products\Exceptions;

use RuntimeException;

/**
 * Raised by Product's saving guard when a write would leave the
 * simple/variable/variant structure inconsistent. The Filament layer catches
 * the "has variants" case and shows it as an inline form error instead.
 */
class CannotChangeProductType extends RuntimeException
{
    public static function hasVariants(): self
    {
        return new self(__('pim.validation.type_locked_has_variants'));
    }

    public static function variantNeedsParent(): self
    {
        return new self(__('pim.validation.variant_needs_parent'));
    }

    public static function onlyVariantHasParent(): self
    {
        return new self(__('pim.validation.only_variant_has_parent'));
    }

    public static function parentNotVariable(): self
    {
        return new self(__('pim.validation.parent_not_variable'));
    }
}
