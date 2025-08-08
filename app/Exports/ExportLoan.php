<?php

namespace App\Exports;

use App\Models\Loan;
use App\Http\Resources\LoanResource;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportLoan implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = Loan::with('loanAuthority', 'loanTransaction.cashbookAccount', 'user');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('reason', 'LIKE', '%' . $term . '%')
                ->orWhere('reference_no', 'LIKE', '%' . $term . '%')
                ->orWhereHas('loanAuthority', function ($newQuery) use ($term) {
                    $newQuery->where('name', 'LIKE', '%' . $term . '%');
                })
                ->orWhereHas('loanTransaction', function ($newQuery) use ($term) {
                    $newQuery->where('amount', '=', $term)
                        ->orWhereHas('cashbookAccount', function ($newQuery) use ($term) {
                            $newQuery->where('account_number', 'LIKE', '%' . $term . '%')->orWhere('bank_name', 'LIKE', '%' . $term . '%')
                                ->orWhere('bank_name', 'LIKE', '%' . $term . '%');
                        });
                });
        });

        $loans = LoanResource::collection($query->latest()->get())->map(function ($loan) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $loan['reference_no'],
                date('jS M, Y', strtotime($loan->date)),
                $loan->status ? 'Active' : 'Inactive',
                $loan->loanAuthority?->name,
                $loan->installmentStr(),
                $currencySymbol . strval(($loan->totalInterest() > 0) ? ($loan->totalInterest() . (($loan->loan_type == 1) ? ('(' . $loan->interest . '%' . ')') : '')) : '0'),
                $loan->loanTransaction?->cashbookAccount?->bank_name . '[' . $loan->loanTransaction?->cashbookAccount?->account_number . ']',
                $currencySymbol . strval($loan->loanTransaction?->amount),
                $currencySymbol . strval($loan['payable']),
                $currencySymbol . strval($loan->totalDue()),
            ];
        });

        $totalAmount = $loans->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[7]  ?? 0));
        });
        $totalPayable = $loans->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[8]  ?? 0));
        });
        $totalDue = $loans->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[9]  ?? 0));
        });

        // Add the total paid as a new row
        $loans->push([
            '', '', '', '', '', '', '', 'Total Amount = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalAmount, 'Total Payable = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalPayable, 'Total Due = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalDue,
        ]);

        return $loans;
    }

    public function headings(): array
    {
        return [
            'Reference No',
            'Date',
            'Status',
            'Authority',
            'Installment',
            'Interest',
            'Account',
            'Amount',
            'Payable',
            'Due',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Get the last row index
                $lastRow = $event->getSheet()->getDelegate()->getHighestRow();

                // Style the last row (total amount row)
                $event->getSheet()->getDelegate()->getStyle('A' . $lastRow . ':J' . $lastRow)->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('A1:J1')->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('I1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                $event->getSheet()->getDelegate()->getStyle('J1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                // Align column to the right
                $columnH = 'H';
                $event->getSheet()->getDelegate()->getStyle("{$columnH}2:{$columnH}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnI = 'I';
                $event->getSheet()->getDelegate()->getStyle("{$columnI}2:{$columnI}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnJ = 'J';
                $event->getSheet()->getDelegate()->getStyle("{$columnJ}2:{$columnJ}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
