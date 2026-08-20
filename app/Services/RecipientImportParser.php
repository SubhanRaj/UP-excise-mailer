<?php

namespace App\Services;

use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Parses an uploaded recipient file into a flat [headers, rows] shape the import wizard can
 * map columns from. PDF has no real columns — it's regex-extracted into name/email pairs
 * directly (known ceiling: text-layer PDFs only, no OCR — see CLAUDE.md).
 */
class RecipientImportParser
{
    public function parse(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'csv' => $this->parseSpreadsheet(new CsvReader(new CsvOptions()), $path),
            'xlsx' => $this->parseSpreadsheet(new XlsxReader(new XlsxOptions()), $path),
            'pdf' => $this->parsePdf($path),
            default => ['headers' => [], 'rows' => []],
        };
    }

    private function parseSpreadsheet(CsvReader|XlsxReader $reader, string $path): array
    {
        $reader->open($path);

        $headers = [];
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $i => $row) {
                $cells = array_map(fn ($v) => trim((string) $v), $row->toArray());

                if ($i === 1) {
                    $headers = $cells;
                } else {
                    $rows[] = $cells;
                }
            }

            break; // Only the first sheet — ad-hoc recipient lists aren't multi-sheet workbooks.
        }

        $reader->close();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** Extracts "Name <email@x.com>" or bare "email@x.com" lines, one recipient per match. */
    private function parsePdf(string $path): array
    {
        $text = (new PdfParser())->parseFile($path)->getText();

        preg_match_all('/(?:([^\r\n<]{2,80})\s*<)?\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\s*>?/', $text, $matches, PREG_SET_ORDER);

        $rows = array_map(fn ($m) => [trim($m[1] ?? ''), trim($m[2])], $matches);

        return ['headers' => ['Name', 'Email'], 'rows' => $rows];
    }
}
