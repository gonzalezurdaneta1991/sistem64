<?php

namespace App\Exports;

use App\Models\Payroll;
use App\Http\Resources\PayrollResource;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportPayroll implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = Payroll::with('employee', 'payrollTransaction.cashbookAccount');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('salary_date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('salary_month', 'LIKE', '%' . $term . '%')
                ->orWhere('deduction_reason', 'LIKE', '%' . $term . '%')
                ->orWhereHas('employee', function ($newQuery) use ($term) {
                    $newQuery->where('name', 'LIKE', '%' . $term . '%')
                        ->orWhere('emp_id', 'LIKE', '%' . $term . '%')
                        ->orWhere('designation', 'LIKE', '%' . $term . '%')
                        ->orWhere('salary', 'LIKE', '%' . $term . '%');
                })
                ->orWhereHas('payrollTransaction', function ($newQuery) use ($term) {
                    $newQuery->where('cheque_no', 'LIKE', '%' . $term . '%')->orWhere('amount', '=', $term)
                        ->orWhereHas('cashbookAccount', function ($newQuery) use ($term) {
                            $newQuery->where('account_number', 'LIKE', '%' . $term . '%')
                                ->orWhere('bank_name', 'LIKE', '%' . $term . '%');
                        });
                });
        });

        $payrolls = PayrollResource::collection($query->latest()->get())->map(function ($payroll) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $payroll->employee?->name,
                date('jS M, Y', strtotime($payroll->salary_date)),
                $payroll->status ? 'Active' : 'Inactive',
                config('config.employeePrefix') . ' - ' . $payroll->employee?->id,
                $payroll->employee?->designation,
                $payroll->salary_month,
                $payroll->payrollTransaction?->cashbookAccount?->bank_name . '[' . $payroll->payrollTransaction?->cashbookAccount?->account_number . ']',
                $currencySymbol . strval($payroll->payrollTransaction?->amount),
            ];
        });

        $totalPaid = $payrolls->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[7] ?? 0));
        });

        // Add the total paid as a new row
        $payrolls->push([
            '', '', '', '', '', '', '', 'Total Paid = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalPaid,
        ]);

        return $payrolls;
    }

    public function headings(): array
    {
        return [
            'Emp Name',
            'Salary Date',
            'Status',
            'Emp ID',
            'Designation',
            'Salary Month',
            'Account',
            'Total Paid',
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
                $columnH = 'H';
                $event->getSheet()->getDelegate()->getStyle("{$columnH}2:{$columnH}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
