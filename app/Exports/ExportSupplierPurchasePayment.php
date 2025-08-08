<?php

namespace App\Exports;

use App\Models\PurchasePayment;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Http\Resources\PurchasePaymentResource;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportSupplierPurchasePayment implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = PurchasePayment::with('purchase.supplier', 'purchasePaymentTransaction.cashbookAccount', 'user');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('amount', '=', $term)
                ->orWhereHas('purchase', function ($newQuery) use ($term) {
                    $newQuery->where('purchase_no', 'LIKE', '%' . $term . '%')
                        ->orWhere('po_reference', 'LIKE', '%' . $term . '%')
                        ->orWhereHas('supplier', function ($newQuery) use ($term) {
                            $newQuery->where('name', 'LIKE', '%' . $term . '%')
                                ->orWhere('name', 'LIKE', '%' . $term . '%')
                                ->orWhere('phone', 'LIKE', '%' . $term . '%');
                        });
                })
                ->orWhereHas('purchasePaymentTransaction', function ($newQuery) use ($term) {
                    $newQuery->where('cheque_no', 'LIKE', '%' . $term . '%')
                        ->orWhere('receipt_no', 'LIKE', '%' . $term . '%')
                        ->orWhere('amount', 'LIKE', '%' . $term . '%')
                        ->orWhereHas('cashbookAccount', function ($newQuery) use ($term) {
                            $newQuery->where('account_number', 'LIKE', '%' . $term . '%')->orWhere('bank_name', 'LIKE', '%' . $term . '%');
                        });
                });
        });

        $purchasePayments = PurchasePaymentResource::collection($query->latest()->get())->map(function ($purchasePayment) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                config('config.purchasePrefix') . ' - ' . $purchasePayment->purchase?->purchase_no,
                date('jS M, Y', strtotime($purchasePayment->date)),
                $purchasePayment->status ? 'Active' : 'Inactive',
                $purchasePayment->purchase?->supplier?->name,
                $currencySymbol . strval($purchasePayment->purchase?->purchaseTotal()),
                $purchasePayment->purchasePaymentTransaction?->cashbookAccount?->bank_name . '[' . $purchasePayment->purchasePaymentTransaction?->cashbookAccount?->account_number . ']',
                $currencySymbol . strval($purchasePayment->amount),
            ];
        });

        $totalPaidAmount = $purchasePayments->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[6]  ?? 0));
        });

        // Add the total salary as a new row
        $purchasePayments->push([
            '', '', '', '', '', '', 'Total Paid Amount = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalPaidAmount,
        ]);

        return $purchasePayments;
    }

    public function headings(): array
    {
        return [
            'Purchase No',
            'Payment Date',
            'Status',
            'Supplier',
            'Total',
            'Account',
            'Paid Amount',
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
