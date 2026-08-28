<?php

namespace Modules\ImportGestionali\Support;

use Generator;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Reads CSV / XLSX / ODS with openspout (streaming, low memory). Legacy .xls
 * (Excel 97-2003) is not supported — openspout cannot read it.
 *
 * Every file-level problem is raised as {@see UnreadableImportFile} with a
 * message already translated for the user.
 */
class SpreadsheetReader
{
    private const CSV_EXTENSIONS = ['csv', 'txt', 'tsv'];

    public function __construct(
        private readonly int $sampleLimit = 50,
    ) {}

    /**
     * Look at the file: header, a sample of rows, the total data-row count,
     * and (for CSV) the detected delimiter and encoding.
     *
     * @throws UnreadableImportFile
     */
    public function inspect(string $absolutePath, string $extension): FileShape
    {
        $extension = strtolower($extension);
        [$delimiter, $encoding] = $this->isCsv($extension)
            ? $this->sniffCsv($absolutePath)
            : [null, null];

        $reader = $this->makeReader($extension, $delimiter, $encoding);

        try {
            $reader->open($absolutePath);
        } catch (Throwable $e) {
            throw UnreadableImportFile::cannotOpen($e);
        }

        $header = [];
        $sample = [];
        $dataCount = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headerSeen = false;

                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $this->stringifyCells($row->toArray());

                    if (! $headerSeen) {
                        // The header is the first non-blank row — a file can have
                        // a title or image row above it (common in ERP exports).
                        if ($this->isBlank($cells)) {
                            continue;
                        }

                        $header = $cells;
                        $headerSeen = true;

                        continue;
                    }

                    if ($this->isBlank($cells)) {
                        continue;
                    }

                    $dataCount++;

                    if (count($sample) < $this->sampleLimit) {
                        $sample[] = $cells;
                    }
                }

                break; // first sheet only
            }
        } catch (Throwable $e) {
            $reader->close();

            throw UnreadableImportFile::parseFailed($e);
        }

        $reader->close();

        $header = self::normalizeHeader($header);

        if ($header === [] || (count($header) === 1 && preg_match('/[;,\t|]/', $header[0]))) {
            throw UnreadableImportFile::badHeader();
        }

        if ($dataCount === 0) {
            throw UnreadableImportFile::noRows();
        }

        $width = count($header);
        $sample = array_map(fn (array $cells): array => $this->fit($cells, $width), $sample);

        return new FileShape($header, $sample, $dataCount, $delimiter, $encoding);
    }

    /**
     * Stream every data row as a positional array aligned to the header,
     * keyed by the spreadsheet line number (the header is line 1).
     *
     * @return Generator<int, list<string>>
     *
     * @throws UnreadableImportFile
     */
    public function rows(string $absolutePath, string $extension, ?string $delimiter, ?string $encoding): Generator
    {
        $reader = $this->makeReader(strtolower($extension), $delimiter, $encoding);

        try {
            $reader->open($absolutePath);
        } catch (Throwable $e) {
            throw UnreadableImportFile::cannotOpen($e);
        }

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $width = null;
                $headerSeen = false;

                foreach ($sheet->getRowIterator() as $line => $row) {
                    $cells = $this->stringifyCells($row->toArray());

                    if (! $headerSeen) {
                        if ($this->isBlank($cells)) {
                            continue;
                        }

                        $width = count(self::normalizeHeader($cells));
                        $headerSeen = true;

                        continue;
                    }

                    if ($this->isBlank($cells)) {
                        continue;
                    }

                    yield $line => $this->fit($cells, $width ?? count($cells));
                }

                break;
            }
        } catch (Throwable $e) {
            throw UnreadableImportFile::parseFailed($e);
        } finally {
            $reader->close();
        }
    }

    /**
     * Trailing empty header cells are dropped; duplicate names are suffixed so
     * every column stays addressable. Applied identically at inspect and read
     * time so column positions line up.
     *
     * @param  list<string>  $header
     * @return list<string>
     */
    public static function normalizeHeader(array $header): array
    {
        while ($header !== [] && trim((string) end($header)) === '') {
            array_pop($header);
        }

        $seen = [];
        $out = [];

        foreach ($header as $i => $name) {
            $name = trim((string) $name);

            if ($name === '') {
                $name = 'Colonna '.($i + 1);
            }

            $key = mb_strtolower($name);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $out[] = $seen[$key] > 1 ? $name.' ('.$seen[$key].')' : $name;
        }

        return $out;
    }

    private function isCsv(string $extension): bool
    {
        return in_array($extension, self::CSV_EXTENSIONS, true);
    }

    private function makeReader(string $extension, ?string $delimiter, ?string $encoding): ReaderInterface
    {
        if ($this->isCsv($extension)) {
            $options = new CsvOptions;
            $options->FIELD_DELIMITER = $delimiter ?: ',';
            // Keep blank lines so the row iterator key stays the real
            // spreadsheet line number, which the report refers to.
            $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

            if ($encoding !== null && $encoding !== '') {
                $options->ENCODING = $encoding;
            }

            return new CsvReader($options);
        }

        return match ($extension) {
            'xlsx' => new XlsxReader,
            'ods' => new OdsReader,
            'xls' => throw UnreadableImportFile::unsupportedType('xls'),
            default => throw UnreadableImportFile::unsupportedType($extension),
        };
    }

    /**
     * @return array{0: string, 1: ?string} [delimiter, encoding]
     *
     * @throws UnreadableImportFile
     */
    private function sniffCsv(string $absolutePath): array
    {
        $handle = @fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw UnreadableImportFile::cannotOpen();
        }

        $head = (string) fread($handle, 8192);
        fclose($handle);

        // Byte-order mark: openspout strips a UTF-8 BOM itself.
        $encoding = null;

        if (! mb_check_encoding($head, 'UTF-8')) {
            $detected = mb_detect_encoding($head, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ISO-8859-15'], true);

            if ($detected === false || $detected === 'UTF-8') {
                throw UnreadableImportFile::badEncoding();
            }

            $encoding = $detected;
        }

        $firstLine = strtok($head, "\r\n") ?: $head;

        $counts = [
            ';' => substr_count($firstLine, ';'),
            ',' => substr_count($firstLine, ','),
            "\t" => substr_count($firstLine, "\t"),
            '|' => substr_count($firstLine, '|'),
        ];
        arsort($counts);
        $delimiter = array_key_first($counts);

        if ($counts[$delimiter] === 0) {
            $delimiter = ',';
        }

        return [$delimiter, $encoding];
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return list<string>
     */
    private function stringifyCells(array $cells): array
    {
        return array_map(
            static fn (mixed $value): string => match (true) {
                $value === null => '',
                is_string($value) => trim($value),
                is_bool($value) => $value ? '1' : '0',
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                default => trim((string) $value),
            },
            array_values($cells),
        );
    }

    /**
     * @param  list<string>  $cells
     * @return list<string>
     */
    private function fit(array $cells, int $width): array
    {
        $cells = array_slice($cells, 0, $width);

        return array_pad($cells, $width, '');
    }

    /**
     * @param  list<string>  $cells
     */
    private function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== '') {
                return false;
            }
        }

        return true;
    }
}
