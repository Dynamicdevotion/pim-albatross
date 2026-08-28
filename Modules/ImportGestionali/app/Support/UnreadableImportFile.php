<?php

namespace Modules\ImportGestionali\Support;

use RuntimeException;
use Throwable;

/**
 * A file-level problem that makes the whole import impossible: the message is
 * already translated and safe to show to the user. Row-level problems are not
 * exceptions — they are collected into the report.
 */
final class UnreadableImportFile extends RuntimeException
{
    public static function cannotOpen(?Throwable $previous = null): self
    {
        return new self(__('pim.import.error.cannot_open'), 0, $previous);
    }

    public static function parseFailed(?Throwable $previous = null): self
    {
        return new self(__('pim.import.error.parse_failed'), 0, $previous);
    }

    public static function unsupportedType(string $extension): self
    {
        return new self(__('pim.import.error.unsupported_type', ['ext' => strtoupper($extension)]));
    }

    public static function badHeader(): self
    {
        return new self(__('pim.import.error.bad_header'));
    }

    public static function noRows(): self
    {
        return new self(__('pim.import.error.no_rows'));
    }

    public static function badEncoding(): self
    {
        return new self(__('pim.import.error.bad_encoding'));
    }
}
