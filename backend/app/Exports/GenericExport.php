<?php

namespace App\Exports;

use App\Services\ExportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GenericExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function __construct(private array $headings, private array $rows) {}

    public function array(): array
    {
        return array_map(fn ($row) => array_map(function ($value) {
            // Spreadsheet formula injection protection for string cells.
            if (is_string($value)) {
                return ExportService::safeCell($value);
            }

            return $value;
        }, (array) $row), $this->rows);
    }

    public function headings(): array
    {
        return array_map(fn ($h) => ExportService::safeCell($h), $this->headings);
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
