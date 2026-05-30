<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function csv(array $headers, array $rows, string $filename, ?string $title = null, ?string $dateRange = null): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($headers, $rows, $title, $dateRange);
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

    public function excel(array $headers, array $rows, string $filename, ?string $title = null, ?string $dateRange = null, ?string $sheetName = null): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($headers, $rows, $title, $dateRange);

        if ($sheetName) {
            $spreadsheet->getActiveSheet()->setTitle(substr($sheetName, 0, 31));
        }

        $writer = new XlsxWriter($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildSpreadsheet(array $headers, array $rows, ?string $title = null, ?string $dateRange = null): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $rowNum = 1;

        if ($title) {
            $sheet->setCellValue("A{$rowNum}", $title);
            $sheet->getStyle("A{$rowNum}")->getFont()->setBold(true)->setSize(14);
            $rowNum++;
        }

        if ($dateRange) {
            $sheet->setCellValue("A{$rowNum}", $dateRange);
            $sheet->getStyle("A{$rowNum}")->getFont()->setSize(10);
            $rowNum++;
            $rowNum++;
        }

        $headerRow = $rowNum;
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}{$headerRow}", $header);
            $sheet->getStyle("{$col}{$headerRow}")->getFont()->setBold(true);
            $col++;
        }

        $dataStartRow = $headerRow + 1;
        foreach ($rows as $row) {
            $col = 'A';
            foreach ($row as $cell) {
                $sheet->setCellValue("{$col}{$dataStartRow}", (string) $cell);
                $col++;
            }
            $dataStartRow++;
        }

        $lastCol = chr(ord('A') + count($headers) - 1);
        foreach (range('A', $lastCol) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        if ($title || $dateRange) {
            $sheet->mergeCells("A{$rowNum}:{$lastCol}{$rowNum}");
        }

        $sheet->freezePane("A{$headerRow}");

        return $spreadsheet;
    }
}
