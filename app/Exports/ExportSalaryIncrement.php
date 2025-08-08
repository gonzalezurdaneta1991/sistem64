<?php

namespace App\Exports;

use App\Models\SalaryIncrement;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Http\Resources\SalaryIncrementResource;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportSalaryIncrement implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
{
    protected $startDate;
    protected $endDate;
    protected $term;

    public function __construct($startDate, $endDate, $term)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->term = $term;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $term = $this->term;
        $query = SalaryIncrement::query();

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('increment_date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('reason', 'LIKE', '%' . $term . '%')
                ->orWhere('increment_amount', 'LIKE', '%' . $term . '%')
                ->orWhereHas('employee', function ($newQuery) use ($term) {
                    $newQuery->where('name', 'LIKE', '%' . $term . '%')
                        ->orWhere('name', 'LIKE', '%' . $term . '%')
                        ->orWhere('emp_id', 'LIKE', '%' . $term . '%')
                        ->orWhere('designation', 'LIKE', '%' . $term . '%')
                        ->orWhere('salary', 'LIKE', '%' . $term . '%');
                });
        });

        $increments = SalaryIncrementResource::collection($query->latest()->get())->map(function ($increment) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $increment->employee?->name,
                date('jS M, Y', strtotime($increment->increment_date)),
                $increment->status ? 'Active' : 'Inactive',
                config('config.employeePrefix') . ' - ' . $increment->employee?->emp_id,
                $increment->reason,
                $currencySymbol . strval($increment->employee?->salary),
                $currencySymbol . strval($increment->employee?->totalSalary()),
                $currencySymbol . strval($increment->increment_amount),
            ];
        });

        $totalIncrementSalary = $increments->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[7]  ?? 0));
        });

        // Add the total salary as a new row
        $increments->push([
            '', '', '', '', '', '', '', 'Total Increment = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalIncrementSalary,
        ]);

        return $increments;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Increment Date',
            'Status',
            'Emp ID',
            'Increment Reason',
            'Basic Salary',
            'Present Salary',
            'Increment Amount',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Get the last row index
                $lastRow = $event->getSheet()->getDelegate()->getHighestRow();

                // Style the last row (total amount row)
                $event->getSheet()->getDelegate()->getStyle('A' . $lastRow . ':H' . $lastRow)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 13,
                    ],
                    'fill' => [
                        'fillType' => 'solid',
                        'startColor' => [
                            'rgb' => '00FF00',
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);

                // Style the header row (headings)
                $event->getSheet()->getDelegate()->getStyle('A1:H1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 13,
                    ],
                    'fill' => [
                        'fillType' => 'solid',
                        'startColor' => [
                            'rgb' => '00FF00',
                        ],
                    ],
                ]);

                // Style the header row (headings)
                $event->getSheet()->getDelegate()->getStyle('H1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                // Align column to the right
                $columnH = 'H';
                $event->getSheet()->getDelegate()->getStyle("{$columnH}2:{$columnH}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
