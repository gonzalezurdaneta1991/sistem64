<?php

namespace App\Exports;

use App\Models\InvoicePayment;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportClientInvoicePayment implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = InvoicePayment::with('invoice.client', 'invoicePaymentTransaction.cashbookAccount', 'user');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('amount', '=', $term)
                ->orWhereHas('invoice', function ($newQuery) use ($term) {
                    $newQuery->where('invoice_no', 'LIKE', '%' . $term . '%')
                        ->orWhere('po_reference', 'LIKE', '%' . $term . '%')
                        ->orWhereHas('client', function ($anotherQuery) use ($term) {
                            $anotherQuery->where('name', 'LIKE', '%' . $term . '%')
                                ->orWhere('phone', 'LIKE', '%' . $term . '%');
                        });
                })
                ->orWhereHas('invoicePaymentTransaction', function ($newQuery) use ($term) {
                    $newQuery->where('cheque_no', 'LIKE', '%' . $term . '%')
                        ->orWhere('receipt_no', 'LIKE', '%' . $term . '%')
                        ->orWhereHas('cashbookAccount', function ($newQuery) use ($term) {
                            $newQuery->where('account_number', 'LIKE', '%' . $term . '%')
                                ->orWhere('bank_name', 'LIKE', '%' . $term . '%');
                        });
                });
        });

        // Retrieve balance clientInvoicePayments
        $clientInvoicePayments = $query->latest()->get()->map(function ($clientInvoicePayment) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                config('config.invoicePrefix') . ' - ' . $clientInvoicePayment->invoice_id ?? 'N/A',
                date('jS M, Y', strtotime($clientInvoicePayment->date)),
                $clientInvoicePayment->status ? 'Active' : 'Inactive',
                $clientInvoicePayment->invoice?->client->name ?? 'N/A',
                $currencySymbol . strval($clientInvoicePayment->invoice?->calculated_total),
                $clientInvoicePayment->invoicePaymentTransaction?->cashbookAccount?->bank_name . '[' . $clientInvoicePayment->invoicePaymentTransaction?->cashbookAccount?->account_number . ']',
                $currencySymbol . strval($clientInvoicePayment->invoicePaymentTransaction?->amount),
            ];
        });

        $totalAmount = $clientInvoicePayments->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[4] ?? 0));
        });
        $totalPaidAmount = $clientInvoicePayments->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[6]  ?? 0));
        });

        // Add the total paid as a new row
        $clientInvoicePayments->push([
            '', '', '', '', 'Total Amount = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalAmount, '', 'Total Paid Amount =' . getGeneralSettingsInfo()['currency']['symbol'] . $totalPaidAmount,
        ]);

        return $clientInvoicePayments;
    }

    public function headings(): array
    {
        return [
            'Invoice No',
            'Payment Date',
            'Status',
            'Client',
            'Total Amount',
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
                $event->getSheet()->getDelegate()->getStyle('E1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                $event->getSheet()->getDelegate()->getStyle('G1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                // Align column to the right
                $columnE = 'E';
                $event->getSheet()->getDelegate()->getStyle("{$columnE}2:{$columnE}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnG = 'G';
                $event->getSheet()->getDelegate()->getStyle("{$columnG}2:{$columnG}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
