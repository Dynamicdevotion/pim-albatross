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
        'missing_translation_any' => 'Any active language',
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

    // ImportGestionali module — product import from CSV/Excel.
    'import' => [
        'nav' => [
            'group' => 'Import',
            'upload' => 'Import products',
            'history' => 'Import results',
        ],
        'page' => [
            'title' => 'Import products from a file',
        ],
        'step' => [
            'upload' => 'Upload the file',
            'map' => 'Map the columns',
            'preview' => 'Preview',
        ],
        'confirm' => 'Confirm and import',
        'field' => [
            'file' => 'CSV or Excel file',
            'ignore' => '— ignore this column —',
            'sku' => 'SKU',
            'name' => 'Name',
            'description' => 'Description',
            'price' => 'Price',
            'stock' => 'Stock',
            'weight' => 'Weight',
            'length' => 'Length',
            'width' => 'Width',
            'height' => 'Height',
            'status' => 'Status',
            'image_url' => 'Main image (URL)',
            'gallery_urls' => 'Gallery (URLs separated by |)',
            'update_existing' => 'Update products that already exist',
        ],
        'help' => [
            'file' => 'Accepted formats: CSV, XLSX, ODS. The old .xls format (Excel 97-2003) is not supported — save it as .xlsx or .csv.',
            'map' => 'For each column in the file, choose the system field it maps to. SKU is required: it is the key products are matched on. Image columns hold URLs to download the files from; mapping one always sends the import to the queue.',
            'update_existing' => 'Off: rows whose SKU already exists are skipped and listed in the report. On: they update the existing product; fields left empty are not touched.',
            'sample' => 'e.g. :values',
        ],
        'preview' => [
            'intro' => 'Preview of the first :shown rows out of :total.',
            'update_on' => 'Update existing products: on',
            'update_off' => 'Update existing products: off',
            'empty' => 'No rows to show.',
            'outcome' => 'Expected outcome',
            'will_create' => 'Will be created',
            'will_update' => 'Will update the existing one',
            'will_skip' => 'Skipped',
        ],
        'notify' => [
            'done' => 'Import complete',
            'queued' => 'Import started: the file is large, processing continues in the queue',
        ],
        'status' => [
            'pending' => 'Queued',
            'processing' => 'Running',
            'completed' => 'Completed',
            'failed' => 'Failed',
        ],
        'col' => [
            'when' => 'Date',
            'user' => 'User',
            'created' => 'Created',
            'updated' => 'Updated',
            'skipped' => 'Skipped',
        ],
        'record' => [
            'label' => 'Import result',
            'plural' => 'Import results',
        ],
        'report' => [
            'summary' => 'Summary',
            'counts' => 'Result',
            'started' => 'Started',
            'finished' => 'Finished',
            'total_rows' => 'Rows in the file',
            'error' => 'Error',
            'skipped_rows' => 'Rows with problems',
            'more' => '…and :count more notices.',
            'running_hint' => 'The import is still running. Reload the page to refresh the numbers.',
        ],
        'error' => [
            'title' => 'File cannot be imported',
            'cannot_open' => 'The file is not a valid CSV or Excel file, or it is corrupt.',
            'parse_failed' => 'The file content cannot be read: it may be corrupt or in an unexpected format.',
            'unsupported_type' => 'The :ext format is not supported. Save the file as CSV or XLSX.',
            'bad_header' => 'The column header cannot be read. Make sure the first row holds the column names.',
            'no_rows' => 'The file contains no data rows.',
            'bad_encoding' => 'The file uses an unsupported encoding. Re-export it from your ERP as UTF-8.',
            'not_inspected' => 'Upload a valid file first.',
            'sku_unmapped' => 'You must map a column to the SKU field: it is the key products are matched on.',
            'field_mapped_twice' => 'The ":field" field is mapped to more than one column.',
            'file_gone' => 'The uploaded file is no longer available. Upload it again and retry.',
            'unexpected' => 'Unexpected error during the import.',
        ],
        'issue' => [
            'sku_missing' => 'row :line: SKU missing',
            'sku_dup_in_file' => 'row :line: SKU ":sku" duplicated in the file (already on row :first)',
            'sku_exists' => 'row :line: SKU ":sku" already exists, skipped',
            'name_missing' => 'row :line: name missing (required for a new product)',
            'price_not_numeric' => 'row :line: price is not a number (":value")',
            'stock_not_numeric' => 'row :line: stock is not a number (":value")',
            'stock_not_integer' => 'row :line: stock must be a whole number (":value")',
            'weight_not_numeric' => 'row :line: weight is not a number (":value")',
            'length_not_numeric' => 'row :line: length is not a number (":value")',
            'width_not_numeric' => 'row :line: width is not a number (":value")',
            'height_not_numeric' => 'row :line: height is not a number (":value")',
            'status_unknown' => 'row :line: status ":value" not recognised (allowed: draft, active, archived)',
            'negative' => 'row :line: :field cannot be negative (":value")',
            'image_main' => 'row :line: main image — :detail',
            'image_gallery' => 'row :line: gallery — :ok/:total images imported (:failed not downloaded)',
        ],
        'image' => [
            'bad_url' => 'invalid URL (:url)',
            'blocked_host' => 'host not allowed (:url)',
            'unreachable' => 'unreachable (:url)',
            'http_error' => 'download failed (HTTP :status)',
            'empty' => 'empty file',
            'too_large' => 'over the size limit',
            'bad_type' => 'unsupported format (:type) — allowed: JPG, PNG, WebP',
        ],
    ],

    // ExportProdotti module — export products to CSV/XLSX.
    'export' => [
        'nav' => [
            'group' => 'Export',
            'history' => 'Export results',
        ],
        'record' => [
            'label' => 'Export result',
            'plural' => 'Export results',
        ],
        'action' => [
            'trigger' => 'Export',
            'download' => 'Download',
        ],
        'modal' => [
            'heading' => 'Export products',
            'description' => 'Every product matching the filters currently applied to the list is exported, with no page limit. Variants of variable products are included as separate rows.',
            'submit' => 'Export',
            'summary' => '{0} No product matches the current filters|{1} :count product matches the current filters|[2,*] :count products match the current filters',
        ],
        'field' => [
            'format' => 'Format',
            'columns' => 'Columns to include',
        ],
        'format' => [
            'xlsx' => 'Excel (.xlsx)',
            'csv' => 'CSV (.csv)',
        ],
        'column' => [
            'sku' => 'SKU',
            'name' => 'Name (base language)',
            'description' => 'Description (base language)',
            'price' => 'Price (default price list)',
            'stock' => 'Stock',
            'weight' => 'Weight',
            'length' => 'Length',
            'width' => 'Width',
            'height' => 'Height',
            'status' => 'Status',
            'image_url' => 'Main image (URL)',
            'gallery_urls' => 'Gallery (URLs separated by |)',
        ],
        'notify' => [
            'queued' => 'Export started: the catalogue is large, processing continues in the background. You can follow its progress on this page.',
        ],
        'status' => [
            'pending' => 'Queued',
            'processing' => 'Running',
            'completed' => 'Completed',
            'failed' => 'Failed',
        ],
        'col' => [
            'when' => 'Date',
            'user' => 'User',
            'format' => 'Format',
            'columns' => 'Columns',
            'rows' => 'Rows',
        ],
        'report' => [
            'summary' => 'Summary',
            'started' => 'Started',
            'finished' => 'Finished',
            'rows' => 'Rows exported',
            'columns' => 'Columns',
            'error' => 'Error',
            'running_hint' => 'The export is still running. This page refreshes itself; the download button appears as soon as the file is ready.',
        ],
        'error' => [
            'unexpected' => 'Unexpected error while exporting.',
        ],
    ],

    // Branding module — panel appearance (single `settings` row).
    'branding' => [
        'nav' => [
            'group' => 'Settings',
            'label' => 'Branding',
        ],
        'page' => [
            'title' => 'Branding & appearance',
        ],
        'section' => [
            'identity' => 'Panel identity',
            'identity_hint' => 'Logo, name and colour used in the admin header and theme.',
        ],
        'field' => [
            'logo' => 'Logo',
            'brand_name' => 'Company / product name',
            'primary_color' => 'Primary colour',
        ],
        'help' => [
            'logo' => 'Shown in the header instead of the text name. JPG, PNG or WebP, max 5 MB.',
            'brand_name' => 'Used in the header when no logo has been uploaded.',
            'primary_color' => 'Applied to theme buttons, links and accents. Empty = default colour (amber).',
        ],
        'palette' => [
            'default' => 'Default (amber)',
        ],
        'action' => [
            'save' => 'Save',
        ],
        'notification' => [
            'saved' => 'Branding settings saved',
        ],
    ],

    // Dashboard module — catalogue overview on the panel home.
    'dashboard' => [
        'stat' => [
            'active' => 'Active products',
            'draft' => 'Drafts',
            'archived' => 'Archived',
            'no_price' => 'No price',
            'no_price_hint' => 'No price on the ":list" list',
            'stock_zero' => 'Out of stock',
            'missing_translation' => 'Missing translation',
        ],
        'col' => [
            'created' => 'Created on',
        ],
        'missing_image' => [
            'heading' => 'Recent products without an image',
            'empty' => 'Every recent product has a main image.',
        ],
        'import_issues' => [
            'heading' => 'Rows dropped by the last import',
            'view_report' => 'View full report',
            'subheading' => 'Import ":file" on :date — :count rows dropped.',
            'more' => '…and :count more rows.',
            'empty' => 'No recent import dropped any rows.',
        ],
        'chart' => [
            'category' => [
                'heading' => 'Products by category',
                'dataset' => 'Products',
            ],
        ],
    ],
];
