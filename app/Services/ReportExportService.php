<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function csv(array $headers, array $rows, string $filename): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($headers, $rows);
        $writer = new CsvWriter($spreadsheet);
        $writer->setDelimiter(',');
        $writer->setEnclosure('"');
        $writer->setUseBOM(true);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, "{$filename}.csv", [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    public function excel(array $headers, array $rows, string $filename): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($headers, $rows);
        $writer = new XlsxWriter($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildSpreadsheet(array $headers, array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}1", $header);
            $sheet->getStyle("{$col}1")->getFont()->setBold(true);
            $col++;
        }

        $rowNum = 2;
        foreach ($rows as $row) {
            $col = 'A';
            foreach ($row as $cell) {
                $sheet->setCellValue("{$col}{$rowNum}", (string) $cell);
                $col++;
            }
            $rowNum++;
        }

        $lastCol = --$col;
        if ($lastCol >= 'A') {
            foreach (range('A', $lastCol) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }

        return $spreadsheet;
    }
}
