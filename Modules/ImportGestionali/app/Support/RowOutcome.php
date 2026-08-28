<?php

namespace Modules\ImportGestionali\Support;

/**
 * What happened (or, in a dry run, what would happen) to a single row.
 *
 * `warnings` holds non-fatal notes for a row that was still imported — an
 * image URL that would not download, say. They land in the report next to
 * the real skips.
 */
final readonly class RowOutcome
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const SKIPPED = 'skipped';

    /**
     * @param  list<string>  $warnings
     */
    private function __construct(
        public string $action,
        public int $line,
        public ?string $reason = null,
        public array $warnings = [],
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

    public function isSkip(): bool
    {
        return $this->action === self::SKIPPED;
    }
}
