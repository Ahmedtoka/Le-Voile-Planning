<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** تصدير عام — أي شاشة فيها جدول بتستخدمه. */
class TableExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(
        private array $headings,
        private array $rows,
        private string $sheetTitle = 'تقرير',
    ) {}

    public function array(): array   { return $this->rows; }
    public function headings(): array { return $this->headings; }
    public function title(): string   { return $this->sheetTitle; }
}
