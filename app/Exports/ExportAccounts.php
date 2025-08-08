<?php

namespace App\Exports;

use App\Models\Account;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportAccounts implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = Account::query();

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('bank_name', 'Like', '%' . $term . '%')
                ->orWhere('branch_name', 'Like', '%' . $term . '%')
                ->orWhere('account_number', 'Like', '%' . $term . '%');
        });

        // Retrieve accounts
        $accounts = $query->latest()->get()->map(function ($account) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $account->bank_name ?? 'N/A',
                $account->branch_name ?? 'N/A',
                date('jS M, Y', strtotime($account->date)),
                $account->status ? 'Active' : 'Inactive',
                $account->account_number,
                $currencySymbol . strval($account->availableBalance),

            ];
        });

        // Calculate the total of the amount related columns
        $totalAvailableBalance = $accounts->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[5]  ?? 0));
        });

        // Add the total Available Balance as a new row
        $accounts->push([
            '', '', '', '', '', 'Total Available Balance = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalAvailableBalance,
        ]);

        return $accounts;
    }


    public function headings(): array
    {
        return [
            'Bank Name',
            'Branch Name',
            'Date',
            'Status',
            'Account Number',
            'Available Balance',
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
