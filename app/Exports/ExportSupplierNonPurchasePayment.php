<?php

namespace App\Exports;

use App\Models\NonPurchasePayment;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExportSupplierNonPurchasePayment implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = NonPurchasePayment::with('supplier', 'paymentTransaction.cashbookAccount');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('amount', '=', $term)
                ->orWhereHas('supplier', function ($newQuery) use ($term) {
                    $newQuery->where('name', 'LIKE', '%' . $term . '%')
                        ->orWhere('email', 'LIKE', '%' . $term . '%')
                        ->orWhere('company_name', 'LIKE', '%' . $term . '%')
                        ->orWhere('phone', 'LIKE', '%' . $term . '%');
                })
                ->orWhereHas('paymentTransaction', function ($newQuery) use ($term) {
                    $newQuery->where('cheque_no', 'Like', '%' . $term . '%')->orWhere('receipt_no', 'Like', '%' . $term . '%')->whereHas('cashbookAccount', function ($newQuery) use ($term) {
                        $newQuery->where('account_number', 'LIKE', '%' . $term . '%')
                            ->orWhere('bank_name', 'LIKE', '%' . $term . '%');
                    });
                });
        });

        // Retrieve balance supplierNonInvoicePayments
        return  $query->latest()->get()->map(function ($supplierNonInvoicePayment) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $supplierNonInvoicePayment->supplier?->name ?? 'N/A',
                date('jS M, Y', strtotime($supplierNonInvoicePayment->date)),
                $supplierNonInvoicePayment->status ? 'Active' : 'Inactive',
                $supplierNonInvoicePayment->type ? 'Due Paid' : 'Due Added',
                $supplierNonInvoicePayment->paymentTransaction ? $supplierNonInvoicePayment->paymentTransaction?->cashbookAccount?->bank_name . '[' . $supplierNonInvoicePayment->paymentTransaction?->cashbookAccount?->account_number . ']' : '',
                $currencySymbol . strval($supplierNonInvoicePayment->amount),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Supplier',
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
