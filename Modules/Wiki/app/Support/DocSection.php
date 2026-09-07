<?php

namespace Modules\Wiki\Support;

/**
 * One docs/ subfolder. `slug` is the folder name (e.g. `"08-woocommerce"`),
 * used both as the search key into config('wiki.section_visibility') and as
 * the URL prefix for its pages. `inactive` marks a section that documents a
 * commercial feature the client doesn't currently have turned on (see
 * config('wiki.section_feature_flags')) — the section still renders, just
 * with a "Non attivo" badge in the sidebar.
 */
final class DocSection
{
    /**
     * @param  list<DocPage>  $pages
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly int $order,
        public readonly DocPage $indexPage,
        public readonly array $pages,
        public readonly bool $inactive = false,
    ) {}
}
