<?php

namespace App\Exports;

use App\Models\BalanceTansfer;
use App\Models\BalanceTransfer;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportBalanceTransfer implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = BalanceTansfer::with('debitTransaction.cashbookAccount', 'creditTransaction.cashbookAccount', 'user');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('reason', 'LIKE', '%' . $term . '%')
                ->orWhere('amount', 'LIKE', '%' . $term . '%')
                ->orWhereHas('debitTransaction', function ($newQuery) use ($term) {
                    $newQuery->whereHas('cashbookAccount', function ($newQuery) use ($term) {
                        $newQuery->where('account_number', 'LIKE', '%' . $term . '%')
                            ->orWhere('bank_name', 'LIKE', '%' . $term . '%');
                    });
                })
                ->orWhereHas('creditTransaction', function ($newQuery) use ($term) {
                    $newQuery->whereHas('cashbookAccount', function ($newQuery) use ($term) {
                        $newQuery->where('account_number', 'LIKE', '%' . $term . '%')
                            ->orWhere('bank_name', 'LIKE', '%' . $term . '%');
                    });
                });
        });

        // Retrieve balance transfers
        $transfers = $query->latest()->get()->map(function ($transfer) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $transfer->reason ?? 'N/A',
                date('jS M, Y', strtotime($transfer->date)),
                $transfer->status ? 'Active' : 'Inactive',
                $transfer->debitTransaction?->cashbookAccount?->bank_name . '[' . $transfer->debitTransaction?->cashbookAccount?->account_number . ']',
                $transfer->creditTransaction?->cashbookAccount?->bank_name . '[' . $transfer->creditTransaction?->cashbookAccount?->account_number . ']',
                $currencySymbol . strval($transfer->amount),
            ];
        });

        $totalAmount = $transfers->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[5]  ?? 0));
        });

        // Add the total paid as a new row
        $transfers->push([
            '', '', '', '', '', 'Total Amount = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalAmount,
        ]);

        return $transfers;
    }

    public function headings(): array
    {
        return [
            'Reason',
            'Date',
            'Status',
            'From Account',
            'To Account',
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
                $event->getSheet()->getDelegate()->getStyle('A' . $lastRow . ':F' . $lastRow)->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('A1:F1')->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('F1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                // Align column to the right
                $columnF = 'F';
                $event->getSheet()->getDelegate()->getStyle("{$columnF}2:{$columnF}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
