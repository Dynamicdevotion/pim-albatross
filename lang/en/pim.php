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
        'barcode' => 'Barcode',
        'external_id' => 'External ID',
        'status' => 'Status',
        'type' => 'Type',
        'stock' => 'Stock',
        'meta_title' => 'Meta title',
        'meta_description' => 'Meta description',
        'pick_existing' => 'Existing image',
        'variants' => 'Variants',
        'variant' => 'Variant',
        'participating_taxonomies' => 'Taxonomies involved',
        'variant_values' => 'Values to combine',
        'description' => 'Description',
        'slug' => 'Slug',
        'internal_code' => 'Internal code',
        'parent' => 'Parent',
        'children' => 'Children',
        'code' => 'Code',
        'base' => 'Base',
        'active' => 'Active',
        'default' => 'Default',
        'price' => 'Price',
        'sale_price' => 'Sale price',
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
            'with' => 'With a price',
            'without' => 'Without a price',
        ],
        'stock' => [
            'zero' => 'Out of stock',
            'low' => 'Low (≤ :threshold)',
        ],
        'none' => '— none —',
    ],

    'filter' => [
        'missing_translation' => 'Missing translation',
        'missing_translation_any' => 'Any active language',
        'price' => 'Price',
        'type' => 'Type',
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
        'pick_existing_image' => 'Choose from an existing image',
        'pick_existing_confirm' => 'Use this image',
    ],

    'section' => [
        'populate_prices' => 'Populate prices from another list',
        'populate_prices_hint' => 'Optional. Copies every price from the chosen list into the new one, applying a percentage change.',
        'shipping' => 'Shipping',
        'media' => 'Images',
        'seo' => 'SEO',
    ],

    'helper' => [
        'slug_from_name' => 'Leave blank to generate it from the base name.',
        'slug_from_name_translated' => 'Leave blank to generate it from this language\'s name.',
        'internal_code' => 'Technical identifier used internally (e.g. WooCommerce sync, import); generated automatically from the name, not editable.',
        'sale_price' => 'Discounted price shown instead of the regular price; leave blank for no offer.',
        'language_code' => 'ISO 639-1, lowercase (e.g. "it", "en").',
        'percent' => 'e.g. 10 for +10%, -15 for −15%. Blank copies prices unchanged.',
        'percent_short' => 'e.g. 10 for +10%, -15 for −15%.',
        'adjust_category_scope' => 'Applies only to the price list selected above.',
        'variant_sku' => 'Proposed — editable before generating.',
        'generate_variants' => 'Pick which taxonomies define the variants and which of their values to combine. One variant is created per combination.',
        'variable_no_price' => 'A variable product groups its variants; price and stock live on each variant.',
        'variant_main_image' => 'Left empty, the variant shows its parent product\'s main image.',
        'meta_title' => 'Title for search engines (~60 characters). Empty = no meta.',
        'meta_description' => 'Summary for search engines (~155 characters).',
        'pick_existing' => 'The chosen file is duplicated: each product keeps its own copy.',
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
        'pick_existing_done' => 'Image copied to the product',
        'pick_existing_needs_save' => 'Save the product first to pick an existing image',
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
        'slug_taken' => 'This slug is already in use in this language.',
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
        'group' => [
            'fields' => 'Product fields',
            'taxonomies' => 'Taxonomies',
        ],
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
            'create_missing_terms' => 'Create missing terms automatically',
            'replace_taxonomy_terms' => 'Replace existing terms for the mapped taxonomies',
        ],
        'help' => [
            'file' => 'Accepted formats: CSV, XLSX, ODS. The old .xls format (Excel 97-2003) is not supported — save it as .xlsx or .csv.',
            'map' => 'For each column in the file, choose the system field it maps to. SKU is required: it is the key products are matched on. Image columns hold URLs to download the files from; mapping one always sends the import to the queue. A column can also be mapped to a taxonomy: the cell holds one or more term names separated by |.',
            'update_existing' => 'Off: rows whose SKU already exists are skipped and listed in the report. On: they update the existing product; fields left empty are not touched.',
            'create_missing_terms' => 'Off: a term not found in the taxonomy is reported and ignored. On: the term is created on the fly in the matching taxonomy.',
            'replace_taxonomy_terms' => 'Off: resolved terms are added to those already on the product. On: for each mapped taxonomy, the cell\'s terms replace the current ones (only when at least one term resolves).',
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
            'tax_missing' => 'not found',
            'tax_gone' => 'taxonomy no longer available',
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
            'term_not_found' => 'row :line: term ":term" not found in the :taxonomy taxonomy, ignored',
            'taxonomy_gone' => 'row :line: a mapped taxonomy is no longer available, links ignored',
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
            'failed' => 'Export failed. See the details on the export report page.',
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

    // WooSync module — pushes products to a WooCommerce store. A separately
    // sold add-on, toggled per installation (Laravel Pennant feature "woosync"
    // / WOOSYNC_ENABLED).
    'woosync' => [
        'nav' => [
            'group' => 'WooCommerce',
            'settings' => 'Connection',
            'runs' => 'Syncs',
        ],
        'page' => [
            'title' => 'WooCommerce connection',
        ],
        'section' => [
            'connection' => 'WooCommerce store',
            'connection_hint' => 'Store URL and REST API keys (Consumer Key / Secret). The store must use HTTPS.',
            'last_test' => 'Last test',
        ],
        'field' => [
            'store_url' => 'Store URL',
            'consumer_key' => 'Consumer Key',
            'consumer_secret' => 'Consumer Secret',
        ],
        'help' => [
            'store_url' => 'e.g. https://shop.example.com — must start with https://',
            'consumer_key' => 'WooCommerce → Settings → Advanced → REST API. Read/Write permissions are required.',
        ],
        'validation' => [
            'https' => 'The store URL must use HTTPS.',
        ],
        'action' => [
            'sync' => 'Sync to WooCommerce',
            'sync_bulk' => 'Sync selected products to WooCommerce',
            'test' => 'Test connection',
            'save' => 'Save',
        ],
        'confirm' => [
            'single' => 'Send product ":product" to the WooCommerce store?',
            'bulk' => 'Send the selected products to the WooCommerce store? Simple and variable products are synced (variants are never sent as products of their own, only as part of their parent).',
        ],
        'test' => [
            'ok' => 'Connection succeeded.',
            'failed' => 'Connection failed.',
            'never' => 'No test run yet.',
            'summary' => 'Tested :when — :outcome',
        ],
        'notify' => [
            'queued' => 'Sync queued: it will be processed shortly.',
            'done' => 'Sync finished: :created created, :updated updated, :skipped skipped, :failed failed.',
            'delete_failed' => 'Product ":product" deleted from the PIM, but deleting it on WooCommerce failed: manual action needed.',
        ],
        'notification' => [
            'saved' => 'WooCommerce settings saved.',
        ],
        'trigger' => [
            'single' => 'Single',
            'bulk' => 'Bulk',
        ],
        'status' => [
            'pending' => 'Pending',
            'processing' => 'Running',
            'completed' => 'Completed',
            'failed' => 'Failed',
        ],
        'result' => [
            'created' => 'Created',
            'updated' => 'Updated',
            'skipped' => 'Skipped',
            'failed' => 'Failed',
        ],
        'col' => [
            'when' => 'Date',
            'user' => 'User',
            'trigger' => 'Type',
            'total' => 'Total',
            'created' => 'Created',
            'updated' => 'Updated',
            'skipped' => 'Skipped',
            'failed' => 'Failed',
        ],
        'run' => [
            'label' => 'Sync',
            'plural' => 'Syncs',
        ],
        'report' => [
            'summary' => 'Summary',
            'started' => 'Started',
            'finished' => 'Finished',
            'running_hint' => 'Sync in progress… this page refreshes itself.',
            'error' => 'Error',
            'items' => 'Per-product outcome',
            'item_product' => 'Product',
            'item_result' => 'Outcome',
            'item_reason' => 'Detail',
        ],
        'skip' => [
            'variant_standalone' => 'Skipped: variants are never synced on their own, only as part of their variable product.',
            'no_sku' => 'Skipped: missing SKU.',
            'archived' => 'Skipped: archived product, not synced.',
            'archived_trashed' => 'Skipped: archived product — moved to trash on WooCommerce.',
            'archived_trash_failed' => 'Skipped: archived product — could not move it to trash on WooCommerce (still live there).',
        ],
        'warn' => [
            'no_price' => 'No price on the default price list: sent without a price.',
            'no_name' => 'Missing name in the base language (:locale): used the SKU.',
            'variant_missing_attribute' => 'Variant ":variant": no value for attribute ":attribute", left out of that combination.',
        ],
        // Per-variation notes (variable products): land in the "Detail"
        // field of the parent's row in the per-product outcome.
        'variant' => [
            'note' => 'Variant ":variant": :note',
            'failed' => 'Variant ":variant" not synced: :error',
        ],
        // Stock reconciliation notes (delta model, last_known_stock on
        // woosync_product_links): they land in the per-product "Detail" column
        // of the run report.
        'stock' => [
            'first_sync' => 'First sync: PIM stock sent to WooCommerce.',
            'recreated' => 'Product missing on WooCommerce: recreated, PIM stock re-sent.',
            'first_sync_overwrite' => 'First sync: PIM stock sent; WooCommerce value (:woo) overwritten.',
            'woo_unmanaged' => 'Stock management is off on WooCommerce: stock not synced.',
            'delta_applied' => 'Stock reconciled: PIM change :delta on the WooCommerce value :woo → :new.',
            'clamped' => 'The change would have taken stock below zero: set to 0.',
        ],
        'error' => [
            'not_configured' => 'WooCommerce connection is not configured.',
            'unreachable' => 'Store unreachable.',
            'auth' => 'WooCommerce credentials are invalid or expired.',
            'rate_limited' => 'The store asked us to slow down (too many requests). Try again later.',
            'rejected' => 'Request rejected by the store.',
            'gone' => 'Resource not found on the store.',
            'store_error' => 'Store internal error.',
            'unexpected' => 'Unexpected error during the sync.',
        ],
    ],
];
