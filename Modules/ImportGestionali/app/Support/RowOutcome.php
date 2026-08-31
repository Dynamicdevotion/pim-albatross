<?php

namespace Modules\ImportGestionali\Support;

/**
 * What happened (or, in a dry run, what would happen) to a single row.
 *
 * `warnings` holds non-fatal notes for a row that was still imported — an
 * image URL that would not download, a taxonomy term that was not found. They
 * land in the report next to the real skips.
 *
 * `taxonomies` carries the per-taxonomy resolution ({@see TaxonomyResolution})
 * so the preview can show the expected term matches.
 */
final readonly class RowOutcome
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const SKIPPED = 'skipped';

    /**
     * @param  list<string>  $warnings
     * @param  list<TaxonomyResolution>  $taxonomies
     */
    private function __construct(
        public string $action,
        public int $line,
        public ?string $reason = null,
        public array $warnings = [],
        public array $taxonomies = [],
    ) {}

    /**
     * @param  list<string>  $warnings
     */
    public static function created(int $line, array $warnings = []): self
    {
        return new self(self::CREATED, $line, null, $warnings);
    }

    /**
     * @param  list<string>  $warnings
     */
    public static function updated(int $line, array $warnings = []): self
    {
        return new self(self::UPDATED, $line, null, $warnings);
    }

    public static function skipped(int $line, string $reason): self
    {
        return new self(self::SKIPPED, $line, $reason);
    }

    /**
     * @param  list<TaxonomyResolution>  $taxonomies
     */
    public function withTaxonomies(array $taxonomies): self
    {
        return new self($this->action, $this->line, $this->reason, $this->warnings, $taxonomies);
    }

    public function isSkip(): bool
    {
        return $this->action === self::SKIPPED;
    }
}
