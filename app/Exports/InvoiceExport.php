<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use App\Http\Resources\InvoiceListResource;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class InvoiceExport implements FromCollection, WithHeadings, WithEvents
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
        $query = Invoice::with('client', 'invoicePayments', 'user');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('invoice_date', [$this->startDate, $this->endDate]);
        }

        $query = $query->where(function ($query) use ($term) {
            $query->where('invoice_no', 'LIKE', '%' . $term . '%')
                ->where('reference', 'LIKE', '%' . $term . '%')
                ->orWhere('sub_total', 'LIKE', '%' . $term . '%')
                ->orWhere('po_reference', 'LIKE', '%' . $term . '%')
                ->orWhere('payment_terms', 'LIKE', '%' . $term . '%')
                ->orWhere('delivery_place', 'LIKE', '%' . $term . '%')
                ->orWhereHas('client', function ($newQuery) use ($term) {
                    $newQuery->where('name', 'LIKE', '%' . $term . '%')
                        ->orWhere('client_id', 'LIKE', '%' . $term . '%');
                });
        });

        $invoices = InvoiceListResource::collection($query->latest()->get())->map(function ($invoice) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                config('config.invoicePrefix') . ' - ' . $invoice->invoice_no,
                date('jS M, Y', strtotime($invoice->invoice_date)),
                $invoice->status ? 'Active' : 'Inactive',
                $invoice->client->name ?? 'N/A',
                $currencySymbol . strval($invoice->sub_total),
                $currencySymbol . strval($invoice->transport > 0 ? $invoice->transport : '0'),
                $currencySymbol . strval($invoice->discount > 0 ? $invoice->discount : '0'),
                $currencySymbol . strval($invoice->taxAmount() > 0 ? $invoice->taxAmount() : '0'),
                $currencySymbol . strval($invoice->invoiceTotal()),
                $currencySymbol . strval($invoice->invoiceTotalPaid() > 0 ? $invoice->invoiceTotalPaid() : '0'),
                $currencySymbol . strval($invoice->totalDue() > 0 ? $invoice->totalDue() : '0'),
            ];
        });

        // Calculate the total of the amount related columns
        $subTotal = $invoices->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[4] ?? 0));
        });

        $transportTotal = $invoices->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[5] ?? 0));
        });

        $discountTotal = $invoices->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[6] ?? 0));
        });

        $taxTotal = $invoices->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[7] ?? 0));
        });

        $netTotal = $invoices->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[8] ?? 0));
        });

        $paidTotal = $invoices->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[9]  ?? 0));
        });

        $totalDue = $invoices->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[10]  ?? 0));
        });

        // Add the total paid as a new row
        $invoices->push([
            '', '', '', '', 'Sub Total = ' . getGeneralSettingsInfo()['currency']['symbol'] . $subTotal, 'Total Transport = ' . getGeneralSettingsInfo()['currency']['symbol'] . $transportTotal,  'Total Discount = ' . getGeneralSettingsInfo()['currency']['symbol'] . $discountTotal,  'Total Tax = ' . getGeneralSettingsInfo()['currency']['symbol'] . $taxTotal, 'Net Total = ' . getGeneralSettingsInfo()['currency']['symbol'] . $netTotal, 'Total Paid = ' . getGeneralSettingsInfo()['currency']['symbol'] . $paidTotal, 'Total Due = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalDue, '', '',
        ]);

        return $invoices;
    }

    // excel file columns
    public function headings(): array
    {
        return [
            'Invoice No',
            'Invoice Date',
            'Status',
            'Client Name',
            'Sub Total',
            'Transport',
            'Discount',
            'Tax',
            'Net Total',
            'Total Paid',
            'Total Due',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Get the last row index
                $lastRow = $event->getSheet()->getDelegate()->getHighestRow();

                // Style the last row (total amount row)
                $event->getSheet()->getDelegate()->getStyle('A' . $lastRow . ':K' . $lastRow)->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('A1:K1')->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('F1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                $event->getSheet()->getDelegate()->getStyle('G1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
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
                $event->getSheet()->getDelegate()->getStyle('K1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);


                // Align column to the right
                $columnE = 'E';
                $event->getSheet()->getDelegate()->getStyle("{$columnE}2:{$columnE}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnF = 'F';
                $event->getSheet()->getDelegate()->getStyle("{$columnF}2:{$columnF}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnG = 'G';
                $event->getSheet()->getDelegate()->getStyle("{$columnG}2:{$columnG}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnH = 'H';
                $event->getSheet()->getDelegate()->getStyle("{$columnH}2:{$columnH}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnI = 'I';
                $event->getSheet()->getDelegate()->getStyle("{$columnI}2:{$columnI}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnJ = 'J';
                $event->getSheet()->getDelegate()->getStyle("{$columnJ}2:{$columnJ}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnK = 'K';
                $event->getSheet()->getDelegate()->getStyle("{$columnK}2:{$columnK}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
