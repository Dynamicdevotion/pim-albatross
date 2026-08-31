<?php

return [
    'name' => 'ExportProdotti',

    /*
     * Exports whose matching product count (top-level rows, before variant
     * expansion) is this value or lower are generated inline in the request
     * and streamed straight to the browser. Larger ones are pushed onto the
     * queue (which needs the scheduled `queue:work --stop-when-empty` — see
     * routes/console.php) and offered for download from the run's report page.
     */
    'inline_max_rows' => (int) env('EXPORT_INLINE_MAX_ROWS', 1000),

    /*
     * Filesystem disk the generated files are written to. Must be a private
     * (non-public) disk — an export can contain the whole catalogue.
     */
    'disk' => env('EXPORT_DISK', 'local'),

    /*
     * Generated files older than this many days are deleted by
     * `exportprodotti:prune-files`. The ExportRecord rows (the reports) stay.
     */
    'prune_days' => (int) env('EXPORT_PRUNE_DAYS', 7),
];
