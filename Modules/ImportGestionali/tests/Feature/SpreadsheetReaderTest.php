<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Modules\ImportGestionali\Support\SpreadsheetReader;
use Modules\ImportGestionali\Support\UnreadableImportFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\TestCase;

class SpreadsheetReaderTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function tmp(string $extension, string $contents): string
    {
        $path = sys_get_temp_dir().'/imp_'.bin2hex(random_bytes(6)).'.'.$extension;
        file_put_contents($path, $contents);
        $this->tmpFiles[] = $path;

        return $path;
    }

    public function test_reads_a_semicolon_csv(): void
    {
        $path = $this->tmp('csv', "Codice;Nome;Prezzo\nA1;Sedia;10\nA2;Tavolo;20\n");

        $shape = (new SpreadsheetReader)->inspect($path, 'csv');

        $this->assertSame(['Codice', 'Nome', 'Prezzo'], $shape->header);
        $this->assertSame(';', $shape->delimiter);
        $this->assertSame(2, $shape->dataRowCount);
        $this->assertSame(['A1', 'Sedia', '10'], $shape->sampleRows[0]);
    }

    public function test_reads_a_comma_csv(): void
    {
        $path = $this->tmp('csv', "Codice,Nome,Prezzo\nA1,Sedia,10\n");

        $shape = (new SpreadsheetReader)->inspect($path, 'csv');

        $this->assertSame(',', $shape->delimiter);
        $this->assertSame(1, $shape->dataRowCount);
    }

    public function test_strips_a_utf8_bom(): void
    {
        $path = $this->tmp('csv', "\xEF\xBB\xBFCodice;Nome\nA1;Città\n");

        $shape = (new SpreadsheetReader)->inspect($path, 'csv');

        $this->assertSame('Codice', $shape->header[0]);
        $this->assertNull($shape->encoding);
    }

    public function test_transcodes_a_windows_1252_csv(): void
    {
        $utf8 = "Codice;Nome\nA1;Città perù\n";
        $path = $this->tmp('csv', mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8'));

        $reader = new SpreadsheetReader;
        $shape = $reader->inspect($path, 'csv');

        $this->assertSame('Windows-1252', $shape->encoding);

        $rows = iterator_to_array($reader->rows($path, 'csv', $shape->delimiter, $shape->encoding));
        $this->assertSame('Città perù', $rows[2][1]);
    }

    public function test_reads_an_xlsx(): void
    {
        $path = sys_get_temp_dir().'/imp_'.bin2hex(random_bytes(6)).'.xlsx';
        $this->tmpFiles[] = $path;

        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Codice', 'Nome', 'Prezzo']));
        $writer->addRow(Row::fromValues(['A1', 'Sedia', '10']));
        $writer->addRow(Row::fromValues(['A2', 'Tavolo', '20']));
        $writer->close();

        $shape = (new SpreadsheetReader)->inspect($path, 'xlsx');

        $this->assertSame(['Codice', 'Nome', 'Prezzo'], $shape->header);
        $this->assertSame(2, $shape->dataRowCount);
    }

    public function test_streams_data_rows_with_line_numbers(): void
    {
        $path = $this->tmp('csv', "Codice;Nome\nA1;Uno\n\nA2;Due\n");

        $reader = new SpreadsheetReader;
        $rows = iterator_to_array($reader->rows($path, 'csv', ';', null));

        $this->assertSame([2, 4], array_keys($rows));
        $this->assertSame(['A2', 'Due'], $rows[4]);
    }

    public function test_a_corrupt_file_is_rejected(): void
    {
        $path = $this->tmp('xlsx', "this is definitely not a spreadsheet \x00\x01\x02");

        $this->expectException(UnreadableImportFile::class);

        (new SpreadsheetReader)->inspect($path, 'xlsx');
    }

    public function test_a_header_only_file_is_rejected(): void
    {
        $path = $this->tmp('csv', "Codice;Nome\n");

        $this->expectException(UnreadableImportFile::class);

        (new SpreadsheetReader)->inspect($path, 'csv');
    }

    public function test_a_blank_only_file_is_rejected(): void
    {
        $path = $this->tmp('csv', "\n\n\n");

        $this->expectException(UnreadableImportFile::class);

        (new SpreadsheetReader)->inspect($path, 'csv');
    }

    public function test_leading_blank_rows_are_skipped_and_the_header_is_the_first_real_row(): void
    {
        $path = $this->tmp('csv', "\n\nCodice;Nome;Prezzo\nA1;Sedia;10\nA2;Tavolo;20\n");

        $reader = new SpreadsheetReader;
        $shape = $reader->inspect($path, 'csv');

        $this->assertSame(['Codice', 'Nome', 'Prezzo'], $shape->header);
        $this->assertSame(2, $shape->dataRowCount);
        $this->assertSame(['A1', 'Sedia', '10'], $shape->sampleRows[0]);

        // and rows() agrees on where the header is, keeping physical line numbers
        $rows = iterator_to_array($reader->rows($path, 'csv', ';', null));
        $this->assertSame([4, 5], array_keys($rows));
    }

    public function test_an_xlsx_with_a_blank_first_row_still_reads(): void
    {
        $path = sys_get_temp_dir().'/imp_'.bin2hex(random_bytes(6)).'.xlsx';
        $this->tmpFiles[] = $path;

        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['', '', '']));
        $writer->addRow(Row::fromValues(['Codice', 'Nome', 'Prezzo']));
        $writer->addRow(Row::fromValues(['A1', 'Anello', '99']));
        $writer->close();

        $shape = (new SpreadsheetReader)->inspect($path, 'xlsx');

        $this->assertSame(['Codice', 'Nome', 'Prezzo'], $shape->header);
        $this->assertSame(1, $shape->dataRowCount);
    }

    public function test_legacy_xls_is_rejected(): void
    {
        $path = $this->tmp('xls', 'anything');

        $this->expectException(UnreadableImportFile::class);

        (new SpreadsheetReader)->inspect($path, 'xls');
    }

    public function test_duplicate_headers_are_made_unique(): void
    {
        $path = $this->tmp('csv', "Prezzo;Prezzo;Nome\n1;2;x\n");

        $shape = (new SpreadsheetReader)->inspect($path, 'csv');

        $this->assertSame(['Prezzo', 'Prezzo (2)', 'Nome'], $shape->header);
    }
}
