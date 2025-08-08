<?php

namespace App\Exports;

use App\Models\AccountTransaction;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportAccountTransaction implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
{
    protected $term;

    public function __construct($term)
    {
        $this->term = $term;
    }


    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $term = $this->term;

        $query = AccountTransaction::with('cashbookAccount', 'user')->where('reason', 'LIKE', 'Non invoice balance added%')->orWhere('reason', 'LIKE', 'Non invoice balance removed from%');

        if ($term) {
            $query = AccountTransaction::with('cashbookAccount', 'user')
                ->where('reason', 'LIKE', 'Non invoice balance added%')->where(function ($query) use ($term) {
                    $query->orWhere('amount', 'LIKE', '%' . $term . '%')
                        ->orWhereHas('cashbookAccount', function ($newQuery) use ($term) {
                            $newQuery->where('bank_name', 'LIKE', '%' . $term . '%');
                            $newQuery->orWhere('account_number', 'LIKE', '%' . $term . '%');
                        });
                });
        }

        // Retrieve adjustments
        $adjustments = $query->latest()->get()->map(function ($adjustment) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $adjustment->cashbookAccount->bank_name ?? 'N/A',
                date('jS M, Y', strtotime($adjustment->transaction_date)),
                $adjustment->status ? 'Active' : 'Inactive',
                $adjustment->cashbookAccount->account_number ?? 'N/A',
                $currencySymbol . strval($adjustment->amount),
                $adjustment->type ? 'Add Balance' : 'Remove Balance',
            ];
        });

        return $adjustments;
    }

    public function headings(): array
    {
        return [
            'Bank Name',
            'Date',
            'Status',
            'Account Number',
            'Amount',
            'Type',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
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
            },
        ];
    }
}
