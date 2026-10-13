<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Neutralise spreadsheet formula injection for user controlled strings.
     * Cells beginning with =, +, -, @, tab or CR are prefixed with a single quote.
     */
    public static function safeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = (string) $value;
        if ($string === '') {
            return '';
        }

        if (preg_match('/^[=+\-@\t\r]/', $string) === 1) {
            return "'".$string;
        }

        return $string;
    }

    /**
     * Build a UTF-8 CSV streamed response from an iterator of rows.
     *
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function csv(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        $safeName = preg_replace('/[^A-Za-z0-9._\-]+/', '_', $filename) ?: 'export.csv';
        if (! str_ends_with(strtolower($safeName), '.csv')) {
            $safeName .= '.csv';
        }

        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens the file with the correct encoding.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map([self::class, 'safeCell'], $headings));
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safeCell'], (array) $row));
            }
            fclose($out);
        }, $safeName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Stream a spreadsheet friendly XML workbook that Excel opens natively.
     * Used as an offline-safe fallback when a dedicated Excel writer is unavailable.
     *
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function excelXml(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        $safeName = preg_replace('/[^A-Za-z0-9._\-]+/', '_', $filename) ?: 'export';
        $safeName = preg_replace('/\.(csv|xlsx?|xml)$/i', '', $safeName).'.xls';

        return response()->streamDownload(function () use ($headings, $rows) {
            $write = function (mixed $value): string {
                return htmlspecialchars(self::safeCell($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
            };

            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<?mso-application progid="Excel.Sheet"?>'."\n";
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'."\n";
            echo '<Worksheet ss:Name="Export"><Table>'."\n";
            echo '<Row>';
            foreach ($headings as $heading) {
                echo '<Cell><Data ss:Type="String">'.$write($heading).'</Data></Cell>';
            }
            echo '</Row>'."\n";
            foreach ($rows as $row) {
                echo '<Row>';
                foreach ((array) $row as $cell) {
                    $isNumeric = is_int($cell) || is_float($cell) || (is_string($cell) && is_numeric(trim($cell)) && ! preg_match('/^0[0-9]/', trim($cell)));
                    echo '<Cell><Data ss:Type="'.($isNumeric ? 'Number' : 'String').'">'.$write($cell).'</Data></Cell>';
                }
                echo '</Row>'."\n";
            }
            echo '</Table></Worksheet></Workbook>'."\n";
        }, $safeName, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
