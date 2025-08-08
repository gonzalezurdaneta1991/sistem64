<?php

namespace App\Exports;

use App\Models\Purchase;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportPurchase implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = Purchase::with('supplier', 'purchasePayments', 'user');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('purchase_date', [$this->startDate, $this->endDate]);
        }

        $query = $query->where(function ($query) use ($term) {
            $query->where('purchase_no', 'LIKE', '%' . $term . '%')
                ->orWhere('sub_total', 'LIKE', '%' . $term . '%')
                ->orWhere('transport', 'LIKE', '%' . $term . '%')
                ->orWhere('discount', 'LIKE', '%' . $term . '%')
                ->orWhere('po_reference', 'LIKE', '%' . $term . '%')
                ->orWhere('payment_terms', 'LIKE', '%' . $term . '%')
                ->orWhereHas('supplier', function ($newQuery) use ($term) {
                    $newQuery->where('name', 'LIKE', '%' . $term . '%')
                        ->orWhere('company_name', 'LIKE', '%' . $term . '%')
                        ->orWhere('phone', 'LIKE', '%' . $term . '%');
                });
        });

        // Retrieve purchases
        $purchases = $query->latest()->get()->map(function ($purchase) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            $totalPaid = $purchase?->purchasePayments?->sum('amount') ?? 0;
            return [
                $purchase->purchase_no ? config('config.purchasePrefix') . '-' . $purchase->purchase_no : 'N/A',
                date('jS M, Y', strtotime($purchase->purchase_date)),
                $purchase->supplier->name ?? 'N/A',
                $currencySymbol . strval($purchase->sub_total),
                $currencySymbol . strval($purchase->transport > 0 ? $purchase->transport : '0'),
                $currencySymbol . strval($purchase->discount > 0 ? $purchase->discount : '0'),
                $currencySymbol . strval($purchase->calculated_total),
                $currencySymbol . strval($totalPaid > 0 ? $totalPaid : '0'),
                $currencySymbol . strval($purchase->calculated_due > 0 ? $purchase->calculated_due : '0'),
            ];
        });


        // Calculate the total of the amount related columns
        $subTotal = $purchases->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[3] ?? 0));
        });

        $totalTransport = $purchases->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[4] ?? 0));
        });

        $totalDiscout = $purchases->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[5] ?? 0));
        });

        $netTotal = $purchases->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[6] ?? 0));
        });

        $totalPaid = $purchases->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[7] ?? 0));
        });

        $totalDue = $purchases->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[8] ?? 0));
        });

        // Add the total paid as a new row
        $purchases->push([
            '', '', '', 'Sub Total = ' . getGeneralSettingsInfo()['currency']['symbol']  . $subTotal, 'Total Transport = ' . getGeneralSettingsInfo()['currency']['symbol']  . $totalTransport, 'Total Discount = ' . getGeneralSettingsInfo()['currency']['symbol']  . $totalDiscout, 'Net Total = ' . getGeneralSettingsInfo()['currency']['symbol']  . $netTotal, 'Total Paid = ' . getGeneralSettingsInfo()['currency']['symbol']  . $totalPaid, ' 	Total Due = ' . getGeneralSettingsInfo()['currency']['symbol']  . $totalDue,
        ]);

        return $purchases;
    }

    public function headings(): array
    {
        return [
            'Purchase No',
            'Purchase Date',
            'Supplier',
            'Subtotal',
            'Transport',
            'Discount',
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
                $event->getSheet()->getDelegate()->getStyle('A' . $lastRow . ':I' . $lastRow)->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('A1:I1')->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('C1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                $event->getSheet()->getDelegate()->getStyle('D1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
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

                // Align column to the right
                $columnC = 'C';
                $event->getSheet()->getDelegate()->getStyle("{$columnC}2:{$columnC}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $netTotalColumn = 'D';
                $event->getSheet()->getDelegate()->getStyle("{$netTotalColumn}2:{$netTotalColumn}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
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
            },
        ];
    }
}
