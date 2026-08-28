<?php

namespace Modules\ImportGestionali\Support;

use finfo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Downloads one product image from a URL, streaming so an oversized or
 * non-image response never fills memory. Every failure is an
 * {@see ImageFetchException} — a translated fragment the importer turns
 * into a report line without skipping the product row.
 */
final class ImageFetcher
{
    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly int $timeout = 15,
        private readonly int $maxBytes = 5_242_880,
    ) {}

    public static function make(): self
    {
        return new self(
            (int) config('importgestionali.image_timeout', 15),
            (int) config('media-library.max_file_size', 5_242_880),
        );
    }

    /**
     * @throws ImageFetchException
     */
    public function fetch(string $url): FetchedImage
    {
        $url = trim($url);

        if (! preg_match('#^https?://#i', $url)) {
            throw ImageFetchException::badUrl($url);
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($this->isBlockedHost($host)) {
            throw ImageFetchException::blockedHost($url);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withOptions(['stream' => true])
                ->get($url);
        } catch (Throwable $e) {
            throw ImageFetchException::unreachable($url, $e);
        }

        if ($response->failed()) {
            throw ImageFetchException::httpError($response->status());
        }

        $body = $response->toPsrResponse()->getBody();
        $bytes = '';

        while (! $body->eof()) {
            $bytes .= $body->read(8192);

            if (strlen($bytes) > $this->maxBytes) {
                throw ImageFetchException::tooLarge();
            }
        }

        if ($bytes === '') {
            throw ImageFetchException::noBytes();
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: null;

        if (! in_array($mime, self::MIME_TYPES, true)) {
            throw ImageFetchException::badType($mime);
        }

        return new FetchedImage($bytes, $this->filename($url, $mime));
    }

    private function filename(string $url, string $mime): string
    {
        $stem = Str::slug(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME)) ?: 'immagine';

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return "{$stem}.{$extension}";
    }

    /**
     * A cheap guard against pointing the importer at loopback / link-local /
     * RFC-1918 addresses. Not full SSRF protection (no DNS resolution) — the
     * feature is admin-only and the URLs come from the operator's own export.
     */
    private function isBlockedHost(string $host): bool
    {
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
