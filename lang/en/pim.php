<?php

// Admin panel UI strings (labels, actions, messages). Product/taxonomy content
// is translated separately via the Localization module.

return [
    'nav' => [
        'pricing' => 'Pricing',
    ],

    'resource' => [
        'product' => ['label' => 'Product', 'plural' => 'Products'],
        'taxonomy' => ['label' => 'Taxonomy', 'plural' => 'Taxonomies'],
        'price_list' => ['label' => 'Price list', 'plural' => 'Price lists'],
        'language' => ['label' => 'Language', 'plural' => 'Languages'],
    ],

    'page' => [
        'bulk_prices' => [
            'nav' => 'Bulk price editing',
            'title' => 'Bulk price editing',
        ],
    ],

    'field' => [
        'name' => 'Name',
        'sku' => 'SKU',
        'external_id' => 'External ID',
        'status' => 'Status',
        'type' => 'Type',
        'stock' => 'Stock',
        'variants' => 'Variants',
        'variant' => 'Variant',
        'participating_taxonomies' => 'Taxonomies involved',
        'variant_values' => 'Values to combine',
        'description' => 'Description',
        'slug' => 'Slug',
        'parent' => 'Parent',
        'children' => 'Children',
        'code' => 'Code',
        'base' => 'Base',
        'active' => 'Active',
        'default' => 'Default',
        'price' => 'Price',
        'prices' => 'Prices',
        'price_list' => 'Price list',
        'price_min' => 'Min price',
        'price_max' => 'Max price',
        'terms' => 'Terms',
        'taxonomy_terms' => 'Taxonomy terms',
        'translations' => 'Translations',
        'source_list' => 'Source list',
        'adjustment_percent' => 'Adjustment %',
        'category' => 'Category',
        'saved_view' => 'Saved view',
        'search' => 'Search',
        'columns' => 'Columns',
        'base_suffix' => ' — base',
        'weight' => 'Weight',
        'length' => 'Length',
        'width' => 'Width',
        'height' => 'Height',
        'image' => 'Image',
        'main_image' => 'Main image',
        'gallery' => 'Gallery',
    ],

    'option' => [
        'status' => [
            'draft' => 'Draft',
            'active' => 'Active',
            'archived' => 'Archived',
        ],
        'type' => [
            'simple' => 'Simple',
            'variable' => 'Variable',
            'variant' => 'Variant',
        ],
        'price' => [
            'all' => 'All products',
            'with' => 'With a price',
            'without' => 'Without a price',
        ],
        'variant_scope' => [
            'all' => 'All',
            'variants' => 'Variants only',
            'simple' => 'Simple only',
        ],
        'stock' => [
            'zero' => 'Out of stock',
            'low' => 'Low (≤ :threshold)',
        ],
        'any' => 'Any',
        'none' => '— none —',
    ],

    'filter' => [
        'missing_translation' => 'Missing translation',
        'price' => 'Price',
        'type' => 'Type',
        'variant_scope' => 'Variants',
        'taxonomy_terms' => 'Taxonomy',
        'price_presence' => 'Price presence',
    ],

    'action' => [
        'set_default' => 'Set as default',
        'deactivate' => 'Deactivate…',
        'assign_taxonomy_terms' => 'Assign taxonomy terms',
        'set_price' => 'Set price',
        'adjust_selection' => 'Adjust % (selection)',
        'adjust_category' => 'Adjust % (category)',
        'save_view' => 'Save as view',
        'update_view' => 'Update view',
        'delete_view' => 'Delete view',
        'add_price' => 'Add a price',
        'generate_variants' => 'Generate variants',
        'add_variant' => 'Add a variant',
    ],

    'section' => [
        'populate_prices' => 'Populate prices from another list',
        'populate_prices_hint' => 'Optional. Copies every price from the chosen list into the new one, applying a percentage change.',
        'dimensions' => 'Dimensions & weight',
        'media' => 'Images',
    ],

    'helper' => [
        'slug_from_name' => 'Leave blank to generate it from the base name.',
        'language_code' => 'ISO 639-1, lowercase (e.g. "it", "en").',
        'percent' => 'e.g. 10 for +10%, -15 for −15%. Blank copies prices unchanged.',
        'percent_short' => 'e.g. 10 for +10%, -15 for −15%.',
        'adjust_category_scope' => 'Applies only to the price list selected above.',
        'variant_sku' => 'Proposed — editable before generating.',
        'generate_variants' => 'Pick which taxonomies define the variants and which of their values to combine. One variant is created per combination.',
        'variable_no_price' => 'A variable product groups its variants; price and stock live on each variant.',
        'variant_main_image' => 'Left empty, the variant shows its parent product\'s main image.',
    ],

    'modal' => [
        'set_default_hint' => 'This list becomes the default and is forced active; the current default is demoted.',
        'deactivate_heading' => 'Deactivate :name',
        'deactivate_hint' => 'This language already has translated content in the catalogue.',
        'deactivate_mode' => [
            'keep' => 'Keep the content — just hide it (it reappears if the language is re-activated)',
            'delete' => 'Delete every translation in this language',
        ],
    ],

    'notification' => [
        'language_activated' => ':name activated',
        'language_deactivated' => ':name deactivated',
        'content_deleted' => ':count translation row(s) removed.',
        'content_kept' => 'Content kept and hidden.',
        'price_list_default' => ':name is now the default price list',
        'terms_assigned' => 'Terms assigned to :count product(s)',
        'view_saved' => 'View ":name" saved',
        'view_updated' => 'View ":name" updated',
        'view_deleted' => 'View deleted',
        'prices_set' => ':count price(s) set',
        'prices_adjusted_selection' => ':count price(s) adjusted (rows without a price in this list were skipped)',
        'prices_adjusted_category' => ':count price(s) adjusted in this list',
        'select_rows_first' => 'Select some rows in the grid first',
        'variants_generated' => ':count variant(s) generated',
        'variants_generated_partial' => ':created generated, :skipped skipped (SKU already exists)',
        'variants_none_selected' => 'Select at least one value to combine',
        'too_many_combinations' => 'Too many combinations (:count) — select fewer values (max :max)',
    ],

    'column' => [
        'variants_count' => '{0} no variants|{1} :count variant|[2,*] :count variants',
    ],

    'grid' => [
        'search_placeholder' => 'Name or SKU',
        'row_cap' => 'Showing the first :count products — narrow the filters to reach the rest.',
    ],

    'validation' => [
        'type_locked_has_variants' => "Delete the linked variants before changing this product's type.",
        'variant_needs_parent' => 'A variant must belong to a variable product.',
        'only_variant_has_parent' => 'Only a variant can have a parent product.',
        'parent_not_variable' => 'The parent product is not a variable product.',
    ],

    'tooltip' => [
        'translated_languages' => 'Languages this product has content for',
        'variants_count' => 'Number of variants of this product',
    ],
];
