<?php

namespace App\Exports;

use App\Models\AccountTransaction;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportAccountTransactionHistory implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = AccountTransaction::with('cashbookAccount', 'user');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('transaction_date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('reason', 'LIKE', '%' . $term . '%')
                ->orWhere('amount', 'LIKE', '%' . $term . '%')
                ->orWhereHas('cashbookAccount', function ($newQuery) use ($term) {
                    $newQuery->where('bank_name', 'LIKE', '%' . $term . '%')
                        ->orWhere('branch_name', 'LIKE', '%' . $term . '%')
                        ->orWhere('account_number', 'LIKE', '%' . $term . '%');
                });
        });

        // Retrieve balance transactions
        $transactions = $query->latest()->get()->map(function ($transaction) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $transaction->user?->name ?? 'N?A',
                date('jS M, Y', strtotime($transaction->transaction_date)),
                $transaction->status ? 'Active' : 'Inactive',
                $transaction->reason ?? 'N/A',
                $transaction->type ? 'Credit' : 'Debit',
                $transaction->cashbookAccount?->bank_name . '[' . $transaction->cashbookAccount?->account_number . ']',
                $currencySymbol . strval($transaction->amount),
            ];
        });

        $totalAmount = $transactions->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[6] ?? 0));
        });

        // Add the total paid as a new row
        $transactions->push([
            '', '', '', '', '', '', 'Total Amount = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalAmount,
        ]);

        return $transactions;
    }

    public function headings(): array
    {
        return [
            'Created By',
            'Date',
            'Status',
            'Reason',
            'Type',
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
                $columnG = 'G';
                $event->getSheet()->getDelegate()->getStyle("{$columnG}2:{$columnG}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
