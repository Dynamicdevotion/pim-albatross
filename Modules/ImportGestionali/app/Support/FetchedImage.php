<?php

namespace Modules\ImportGestionali\Support;

/**
 * The raw bytes of a downloaded image plus a safe file name for storage.
 */
final readonly class FetchedImage
{
    public function __construct(
        public string $bytes,
        public string $filename,
    ) {}
}
