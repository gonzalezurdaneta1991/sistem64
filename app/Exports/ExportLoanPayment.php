<?php

namespace App\Exports;

use App\Models\LoanPayment;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use App\Http\Resources\LoanPaymentResource;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportLoanPayment implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = LoanPayment::with('loan', 'loanPaymentTransaction.cashbookAccount');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('reference_no', 'LIKE', '%' . $term . '%')
                ->orWhereHas('loan', function ($newQuery) use ($term) {
                    $newQuery->where('reason', 'LIKE', '%' . $term . '%')->orWhere('reference_no', 'LIKE', '%' . $term . '%');
                })
                ->orWhereHas('loanPaymentTransaction', function ($newQuery) use ($term) {
                    $newQuery->where('amount', '=', $term)
                        ->orWhereHas('cashbookAccount', function ($newQuery) use ($term) {
                            $newQuery->where('account_number', 'LIKE', '%' . $term . '%')
                                ->orWhere('bank_name', 'LIKE', '%' . $term . '%');
                        });
                });
        });

        $loanPayments = LoanPaymentResource::collection($query->latest()->get())->map(function ($loanPayment) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $loanPayment['reference_no'],
                date('jS M, Y', strtotime($loanPayment->date)),
                $loanPayment->status ? 'Active' : 'Inactive',
                $loanPayment->loan?->reference_no,
                $loanPayment->loan?->loanAuthority?->name,
                $currencySymbol . strval($loanPayment->loan?->payable),
                $currencySymbol . strval($loanPayment->interest),
                $loanPayment->loanPaymentTransaction?->cashbookAccount?->bank_name . '[' . $loanPayment->loanPaymentTransaction?->cashbookAccount?->account_number . ']',
                $currencySymbol . strval($loanPayment->amount),
            ];
        });

        $totalPaid = $loanPayments->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[8]  ?? 0));
        });

        // Add the total paid as a new row
        $loanPayments->push([
            '', '', '', '', '', '', '', '', 'Total Paid = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalPaid,
        ]);

        return $loanPayments;
    }

    public function headings(): array
    {
        return [
            'Payment Ref.',
            'Date',
            'Status',
            'Loan Ref.',
            'Authority',
            'Payable',
            'Interest',
            'Account',
            'Amount Paid',
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
