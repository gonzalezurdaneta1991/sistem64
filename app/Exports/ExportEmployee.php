<?php

namespace App\Exports;

use App\Models\Employee;
use App\Http\Resources\EmployeeResource;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportEmployee implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = Employee::with('department');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('joining_date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('name', 'Like', '%' . $term . '%')
                ->orWhere('emp_id', 'Like', '%' . $term . '%')
                ->orWhere('designation', 'Like', '%' . $term . '%')
                ->orWhere('mobile_number', 'Like', '%' . $term . '%')
                ->orWhere('salary', 'Like', '%' . $term . '%')
                ->orWhereHas('department', function ($newQuery) use ($term) {
                    $newQuery->where('name', 'LIKE', '%' . $term . '%');
                });
        });

        $employees = EmployeeResource::collection($query->latest()->get())->map(function ($employee) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $employee->name,
                date('jS M, Y', strtotime($employee->birth_date)),
                date('jS M, Y', strtotime($employee->joining_date)),
                $employee->status ? 'Active' : 'Inactive',
                config('config.employeePrefix') . ' - ' . $employee->emp_id,
                $employee->department?->name,
                $employee->designation,
                $employee->mobile_number,
                $currencySymbol . strval($employee->totalSalary()),
            ];
        });

        $totalSalary = $employees->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[8]  ?? 0));
        });

        // Add the total salary as a new row
        $employees->push([
            '', '', '', '', '', '', '', '', 'Total Salary = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalSalary,
        ]);

        return $employees;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Birth Date',
            'Join Date',
            'Status',
            'Emp ID',
            'Department',
            'Designation',
            'Contact Number',
            'Total Salary',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Get the last row index
                $lastRow = $event->getSheet()->getDelegate()->getHighestRow();

                // Style the last row (total amount row)
                $event->getSheet()->getDelegate()->getStyle('A' . $lastRow . ':I' . $lastRow)->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('A1:I1')->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('I1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                // Align column to the right
                $columnI = 'I';
                $event->getSheet()->getDelegate()->getStyle("{$columnI}2:{$columnI}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
