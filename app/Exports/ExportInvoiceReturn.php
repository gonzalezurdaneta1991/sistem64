<?php

namespace App\Exports;

use App\Models\InvoiceReturn;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Http\Resources\InvoiceReturnListResource;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportInvoiceReturn implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = InvoiceReturn::with('invoice.client', 'user');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $query->where(function ($query) use ($term) {
            $query->where('reason', 'LIKE', '%' . $term . '%')
                ->orWhere('slug', 'LIKE', '%' . $term . '%')
                ->orWhereHas('invoice', function ($newQuery) use ($term) {
                    $newQuery->where('invoice_no', 'LIKE', '%' . $term . '%')
                        ->orWhere('po_reference', 'LIKE', '%' . $term . '%')
                        ->orWhereHas('client', function ($anotherQuery) use ($term) {
                            $anotherQuery->where('name', 'LIKE', '%' . $term . '%')
                                ->orWhere('client_id', 'LIKE', '%' . $term . '%');
                        });
                });
        });

        $invoiceReturns = InvoiceReturnListResource::collection($query->latest()->get())->map(function ($invoiceReturn) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                config('config.invoiceReturnPrefix') . ' - ' . $invoiceReturn->return_no,
                date('jS M, Y', strtotime($invoiceReturn->date)),
                $invoiceReturn->status ? 'Active' : 'Inactive',
                config('config.invoicePrefix') . ' - ' .  $invoiceReturn->invoice?->invoice_no,
                $invoiceReturn->invoice?->client?->name,
                $invoiceReturn->reason,
                $currencySymbol . strval($invoiceReturn->total_return),
            ];
        });

        $totalReturnPrice = $invoiceReturns->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[6]  ?? 0));
        });

        // Add the total total return price as a new row
        $invoiceReturns->push([
            '', '', '', '', '', '', 'Total Return = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalReturnPrice,
        ]);

        return $invoiceReturns;
    }

    public function headings(): array
    {
        return [
            'Return No',
            'Date',
            'Status',
            'Invoice No',
            'Client',
            'Return Reason',
            'Cost of Return Products',
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
