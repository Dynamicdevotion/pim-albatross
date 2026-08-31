<?php

namespace Modules\ExportProdotti\Support;

use Modules\ImportGestionali\Support\SpreadsheetReader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Thin openspout writer wrapper — the export-side counterpart of
 * {@see SpreadsheetReader}. Streaming and
 * low memory: rows are flushed to disk as they are added, so a whole-catalogue
 * export never materialises in memory.
 *
 * CSV is written UTF-8 with a BOM (openspout default) so Excel opens accented
 * text correctly; the delimiter is a comma, which the importer's sniffer
 * detects on the way back in.
 */
final class SpreadsheetWriter
{
    public const FORMAT_CSV = 'csv';

    public const FORMAT_XLSX = 'xlsx';

    private function __construct(private readonly WriterInterface $writer) {}

    public static function open(string $format, string $absolutePath): self
    {
        $writer = $format === self::FORMAT_XLSX ? new XlsxWriter : new CsvWriter;
        $writer->openToFile($absolutePath);

        return new self($writer);
    }

    /**
     * @param  list<string|int|float|null>  $values
     */
    public function writeRow(array $values): void
    {
        $this->writer->addRow(Row::fromValues(array_map(
            static fn (string|int|float|null $value): string|int|float => $value ?? '',
            $values,
        )));
    }

    public function close(): void
    {
        $this->writer->close();
    }

    /**
     * The file extension / format key for a user-chosen format, falling back
     * to CSV for anything unexpected.
     */
    public static function normalizeFormat(?string $format): string
    {
        return $format === self::FORMAT_XLSX ? self::FORMAT_XLSX : self::FORMAT_CSV;
    }
}
