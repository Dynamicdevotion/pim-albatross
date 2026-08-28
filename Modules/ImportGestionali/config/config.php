<?php

return [
    'name' => 'ImportGestionali',

    /*
     * Imports of this many data rows or fewer run inline in the confirm
     * request; larger files are pushed onto the queue (which needs the
     * scheduled `queue:work --stop-when-empty` — see routes/console.php).
     */
    'inline_max_rows' => (int) env('IMPORT_INLINE_MAX_ROWS', 300),

    /*
     * Upload size cap for the source file, in megabytes.
     */
    'max_file_mb' => (int) env('IMPORT_MAX_FILE_MB', 20),

    /*
     * How many skipped/failed rows to keep in the report before truncating
     * (the counts stay exact; only the detailed list is capped).
     */
    'issues_cap' => (int) env('IMPORT_ISSUES_CAP', 500),

    /*
     * Rows shown in the pre-confirm preview.
     */
    'preview_rows' => 10,

    /*
     * Filesystem disk the uploaded source files are stored on. Must be a
     * private (non-public) disk — these are raw ERP exports.
     */
    'disk' => env('IMPORT_DISK', 'local'),

    /*
     * Source files older than this many days are deleted by
     * `importgestionali:prune-files`.
     */
    'prune_days' => (int) env('IMPORT_PRUNE_DAYS', 7),

    /*
     * Per-image HTTP timeout (seconds) when downloading from an `image_url` /
     * `gallery_urls` column. The size cap is `media-library.max_file_size`.
     */
    'image_timeout' => (int) env('IMPORT_IMAGE_TIMEOUT', 15),
];
