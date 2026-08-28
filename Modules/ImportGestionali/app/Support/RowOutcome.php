<?php

namespace Modules\ImportGestionali\Support;

/**
 * What happened (or, in a dry run, what would happen) to a single row.
 */
final readonly class RowOutcome
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const SKIPPED = 'skipped';

    private function __construct(
        public string $action,
        public int $line,
        public ?string $reason = null,
    ) {}

    public static function created(int $line): self
    {
        return new self(self::CREATED, $line);
    }

    public static function updated(int $line): self
    {
        return new self(self::UPDATED, $line);
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
