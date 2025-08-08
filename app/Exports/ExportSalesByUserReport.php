<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use App\Http\Resources\InvoiceListResource;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportSalesByUserReport implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = Invoice::with('client', 'invoicePayments', 'user');
        $term = $this->term;
        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('invoice_date', [$this->startDate, $this->endDate]);
        }

        if ($term != 0) {
            $query = $query->where(function ($query) use ($term) {
                $query->WhereHas('user', function ($newQuery) use ($term) {
                    $newQuery->where('id', $term);
                });
            });
        }

        $invoices = InvoiceListResource::collection($query->latest()->get())->map(function ($invoice) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $invoice->user->name . '[' . ($invoice->user?->employee?->designation ?? 'Super Admin') . ']',
                date('jS M, Y', strtotime($invoice->invoice_date)),
                config('config.invoicePrefix') . ' - ' . $invoice->invoice_no,
                $invoice->client->name ?? 'N/A',
                $currencySymbol . strval($invoice->invoiceTotal()),
            ];
        });

        // Calculate the total of the amount related columns
        $netTotal = $invoices->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[4]));
        });

        // Add the total paid as a new row
        $invoices->push([
            '', '', '', '', 'Net Total = ' . getGeneralSettingsInfo()['currency']['symbol'] . $netTotal,
        ]);

        return $invoices;
    }

    public function headings(): array
    {
        return [
            'Sale By',
            'Invoice Date',
            'Invoice No',
            'Client',
            'Net Total',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Get the last row index
                $lastRow = $event->getSheet()->getDelegate()->getHighestRow();

                // Style the last row (total amount row)
                $event->getSheet()->getDelegate()->getStyle('A' . $lastRow . ':E' . $lastRow)->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('A1:E1')->applyFromArray([
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

                // Align column to the right
                $InvoiceDate = 'E';
                $event->getSheet()->getDelegate()->getStyle("{$InvoiceDate}2:{$InvoiceDate}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
