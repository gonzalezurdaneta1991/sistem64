<?php

namespace App\Exports;

use App\Models\NonInvoicePayment;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExportClientNonInvoicePayment implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = NonInvoicePayment::with('client', 'paymentTransaction.cashbookAccount');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('amount', 'LIKE', '%' . $term . '%')
                ->orWhereHas('client', function ($newQuery) use ($term) {
                    $newQuery->where('name', 'LIKE', '%' . $term . '%')
                        ->orWhere('email', 'LIKE', '%' . $term . '%')
                        ->orWhere('company_name', 'LIKE', '%' . $term . '%')
                        ->orWhere('phone', 'LIKE', '%' . $term . '%');
                })
                ->orWhereHas('paymentTransaction', function ($newQuery) use ($term) {
                    $newQuery->where('cheque_no', 'LIKE', '%' . $term . '%')
                        ->orWhere('receipt_no', 'LIKE', '%' . $term . '%')->orWhereHas('cashbookAccount', function ($newQuery) use ($term) {
                            $newQuery->where('account_number', 'LIKE', '%' . $term . '%')
                                ->orWhere('bank_name', 'LIKE', '%' . $term . '%');
                        });
                });
        });


        // Retrieve balance clientNonInvoicePayments
        return  $query->latest()->get()->map(function ($clientNonInvoicePayment) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $clientNonInvoicePayment->client?->name ?? 'N/A',
                date('jS M, Y', strtotime($clientNonInvoicePayment->date)),
                $clientNonInvoicePayment->status ? 'Active' : 'Inactive',
                $clientNonInvoicePayment->type ? 'Due Paid' : 'Due Added',
                $clientNonInvoicePayment->paymentTransaction ? $clientNonInvoicePayment->paymentTransaction?->cashbookAccount?->bank_name . '[' . $clientNonInvoicePayment->paymentTransaction?->cashbookAccount?->account_number . ']' : '',
                $currencySymbol . strval($clientNonInvoicePayment->amount),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Client',
            'Payment Date',
            'Status',
            'Payment Type',
            'Account',
            'Amount',
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
