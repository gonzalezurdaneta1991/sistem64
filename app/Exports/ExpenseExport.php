<?php

namespace App\Exports;

use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Sheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExpenseExport implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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

    public function collection()
    {
        $term = $this->term;
        $expensesQuery = Expense::with('expSubCategory.expCategory', 'expTransaction.cashbookAccount', 'user')->latest();

        // Apply date range filter
        if ($this->startDate && $this->endDate) {
            $expensesQuery->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        // Apply term filters
        $expensesQuery->where(function ($query) use ($term) {
            $query->where('reason', 'LIKE', '%' . $term . '%')
                ->orWhereHas('expSubCategory', function ($query) use ($term) {
                    $query->where('name', 'LIKE', '%' . $term . '%')
                        ->orWhereHas('expCategory', function ($query) use ($term) {
                            $query->where('name', 'LIKE', '%' . $term . '%');
                        });
                })
                ->orWhereHas('expTransaction', function ($query) use ($term) {
                    $query->where('amount', 'LIKE', '%' . $term . '%')
                        ->orWhereHas('cashbookAccount', function ($query) use ($term) {
                            $query->where('account_number', 'LIKE', '%' . $term . '%');
                        });
                });
        });

        // Retrieve expenses
        $expenses = $expensesQuery->get()->map(function ($expense) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $expense->reason ?? 'N/A',
                date('jS M, Y', strtotime($expense->date)),
                $expense->status ? 'Active' : 'Inactive',
                $expense->expSubCategory->expCategory->name . ' [' . config('config.expCatPrefix') . '-' . $expense->expSubCategory->expCategory->code . ']' ?? 'N/A',
                $expense->expSubCategory->name . ' [' . config('config.expSubCatPrefix') . '-' . $expense->expSubCategory->code . ']' ?? 'N/A',
                $expense->expTransaction->cashbookAccount->bank_name . '[' . $expense->expTransaction->cashbookAccount->account_number . ']' ?? 'N/A',
                $currencySymbol . strval($expense->expTransaction->amount),
            ];
        });

        // Calculate total amount
        $totalAmount = $expenses->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[6] ?? 0));
        });

        // Add total amount as a new row
        $expenses->push(['', '', '', '', '', '', 'Total Amount = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalAmount,]);

        return $expenses;
    }

    public function headings(): array
    {
        return [
            'Expense Reason',
            'Date',
            'Status',
            'Category',
            'Sub Category',
            'Account',
            'Amount',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Get the last row index
                $lastRow = $event->getSheet()->getDelegate()->getHighestRow();

                // Style the last row (total amount row)
                $event->getSheet()->getDelegate()->getStyle('A' . $lastRow . ':G' . $lastRow)->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('A1:G1')->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('G1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);

                // Align column to the right
                $columnG = 'G';
                $event->getSheet()->getDelegate()->getStyle("{$columnG}2:{$columnG}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
