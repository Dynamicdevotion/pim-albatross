<?php

use Modules\WooSync\Support\WooSync;

return [
    'name' => 'Wiki',

    /*
     * Where the wiki's Markdown source lives. Content is versioned in the
     * repository, not the database — this module only reads and renders it.
     */
    'docs_path' => base_path('docs'),

    /*
     * Per-section visibility, keyed by the docs/ folder name. A section
     * without an entry here is always shown. This is for sections that don't
     * apply at all to an installation (the module isn't installed) — a
     * section documenting a commercial feature that IS installed but simply
     * turned off belongs in `section_feature_flags` below instead, so it
     * stays visible with a "Non attivo" badge rather than disappearing.
     */
    'section_visibility' => [],

    /*
     * Per-section commercial-feature status, keyed by the docs/ folder name.
     * A section without an entry here is always considered active. A section
     * documenting a commercial add-on (WooSync, multi-language, multiple
     * price lists) maps to that feature's own `::enabled()` seam, so the
     * sidebar can mark it "Non attivo" when the client doesn't have it
     * turned on — the guide doubles as an upsell surface for every
     * commercial module, not just documentation for what's already active.
     *
     * Add an entry here (keyed by the section's folder name) once the
     * "Lingue" / "Listini" docs sections are written, e.g.:
     *   '03-lingue' => fn (): bool => \Modules\Localization\Support\MultilanguageFeature::enabled(),
     *   '04-listini' => fn (): bool => \Modules\Pricing\Support\MultiplePriceListsFeature::enabled(),
     */
    'section_feature_flags' => [
        '08-woocommerce' => fn (): bool => class_exists(WooSync::class)
            && WooSync::enabled(),
    ],
];
