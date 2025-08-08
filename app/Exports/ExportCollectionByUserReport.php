<?php

namespace App\Exports;

use App\Models\InvoicePayment;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Http\Resources\InvoicePaymentResource;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportCollectionByUserReport implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = InvoicePayment::with('user.employee', 'invoice', 'invoicePaymentTransaction');
        $term = $this->term;
        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        if ($term != 0) {
            $query = $query->where(function ($query) use ($term) {
                $query->WhereHas('user', function ($newQuery) use ($term) {
                    $newQuery->where('id', $term);
                });
            });
        }

        $invoicePayments = InvoicePaymentResource::collection($query->latest()->get())->map(function ($invoicePayment) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $invoicePayment->user->name . '[' . ($invoicePayment->user?->employee?->designation ?? 'Super Admin') . ']',
                date('jS M, Y', strtotime($invoicePayment?->invoicePaymentTransaction->transaction_date)),
                config('config.invoicePrefix') . ' - ' . $invoicePayment->invoice_id,
                $invoicePayment->invoice?->client->name ?? 'N/A',
                $currencySymbol . strval($invoicePayment->amount),
            ];
        });

        // Calculate the total of the amount related columns
        $totalAmount = $invoicePayments->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[4]));
        });

        // Add the total paid as a new row
        $invoicePayments->push([
            '', '', '', '', 'Total Amount = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalAmount,
        ]);

        return $invoicePayments;
    }

    public function headings(): array
    {
        return [
            'Collect By',
            'Transaction Date',
            'Invoice No',
            'Client',
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
                $columnE = 'E';
                $event->getSheet()->getDelegate()->getStyle("{$columnE}2:{$columnE}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
