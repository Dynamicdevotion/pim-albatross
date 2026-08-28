<?php

namespace Modules\ImportGestionali\Support;

/**
 * What a quick look at an uploaded file tells us, without loading it whole.
 */
final readonly class FileShape
{
    /**
     * @param  list<string>  $header  column names, positional
     * @param  list<list<string>>  $sampleRows  first rows (data only), each positional and aligned to $header
     * @param  int  $dataRowCount  number of non-blank data rows
     */
    public function __construct(
        public array $header,
        public array $sampleRows,
        public int $dataRowCount,
        public ?string $delimiter,
        public ?string $encoding,
    ) {}
}
