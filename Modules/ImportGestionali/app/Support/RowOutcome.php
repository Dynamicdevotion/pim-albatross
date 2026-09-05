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
 *
 * `productId` is set only for a variable-container row, so the two-pass
 * variant importer can wire the child variants to it. `UNCHANGED` is that
 * same case when the container already existed and the "update existing"
 * toggle is off: the container is reused as-is (variants still attach) and
 * nothing is counted.
 */
final readonly class RowOutcome
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const SKIPPED = 'skipped';

    public const UNCHANGED = 'unchanged';

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
        public ?int $productId = null,
        public ?string $code = null,
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

    /**
     * `$code` is an optional machine-readable tag (never shown to the user)
     * that lets {@see ImportRunner} tell apart the ways a parent group can be
     * blocked and phrase the per-variant report line accordingly.
     */
    public static function skipped(int $line, string $reason, ?string $code = null): self
    {
        return new self(self::SKIPPED, $line, $reason, [], [], null, $code);
    }

    public static function unchanged(int $line, ?int $productId = null): self
    {
        return new self(self::UNCHANGED, $line, null, [], [], $productId);
    }

    /**
     * @param  list<TaxonomyResolution>  $taxonomies
     */
    public function withTaxonomies(array $taxonomies): self
    {
        return new self($this->action, $this->line, $this->reason, $this->warnings, $taxonomies, $this->productId, $this->code);
    }

    public function withProductId(?int $productId): self
    {
        return new self($this->action, $this->line, $this->reason, $this->warnings, $this->taxonomies, $productId, $this->code);
    }

    public function isSkip(): bool
    {
        return $this->action === self::SKIPPED;
    }
}
