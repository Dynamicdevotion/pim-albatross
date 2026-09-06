<?php

return [
    'name' => 'Wiki',

    /*
     * Where the wiki's Markdown source lives. Content is versioned in the
     * repository, not the database — this module only reads and renders it.
     */
    'docs_path' => base_path('docs'),

    /*
     * Per-section visibility, keyed by the docs/ folder name. A section
     * without an entry here is always shown (it documents a core module that
     * every installation has). A section that documents an optional add-on
     * maps to that module's own feature flag, so the wiki reflects only what
     * the client actually has — this reuses the existing flag rather than
     * inventing a new visibility mechanism.
     */
    'section_visibility' => [
        '08-woocommerce' => fn (): bool => class_exists(\Modules\WooSync\Support\WooSync::class)
            && \Modules\WooSync\Support\WooSync::enabled(),
    ],
];
