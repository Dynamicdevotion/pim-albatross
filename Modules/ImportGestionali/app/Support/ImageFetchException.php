<?php

namespace Modules\ImportGestionali\Support;

use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * A single image could not be downloaded. The message is a translated
 * fragment ("immagine non raggiungibile …"); the caller prefixes the row
 * number and never lets it skip the whole row.
 */
final class ImageFetchException extends RuntimeException
{
    public static function badUrl(string $url): self
    {
        return new self(__('pim.import.image.bad_url', ['url' => Str::limit($url, 80)]));
    }

    public static function blockedHost(string $url): self
    {
        return new self(__('pim.import.image.blocked_host', ['url' => Str::limit($url, 80)]));
    }

    public static function unreachable(string $url, ?Throwable $previous = null): self
    {
        return new self(__('pim.import.image.unreachable', ['url' => Str::limit($url, 80)]), 0, $previous);
    }

    public static function httpError(int $status): self
    {
        return new self(__('pim.import.image.http_error', ['status' => $status]));
    }

    public static function noBytes(): self
    {
        return new self(__('pim.import.image.empty'));
    }

    public static function tooLarge(): self
    {
        return new self(__('pim.import.image.too_large'));
    }

    public static function badType(?string $mime): self
    {
        return new self(__('pim.import.image.bad_type', ['type' => $mime ?? '?']));
    }
}
