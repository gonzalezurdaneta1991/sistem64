<?php

namespace App\Exports;

use App\Models\PurchaseReturn;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Http\Resources\PurchaseReturnListResource;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportPurchaseReturn implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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
        $query = PurchaseReturn::with('purchase.supplier');

        if ($this->startDate && $this->endDate) {
            $query = $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        $query = $query->where(function ($query) use ($term) {
            $query->where('reason', 'LIKE', '%' . $term . '%')
                ->orWhere('slug', 'LIKE', '%' . $term . '%')
                ->orWhere('code', 'LIKE', '%' . $term . '%')
                ->orWhere('total_return', 'LIKE', '%' . $term . '%')
                ->orWhereHas('purchase', function ($newQuery) use ($term) {
                    $newQuery->where('purchase_no', 'LIKE', '%' . $term . '%')
                        ->orWhere('po_reference', 'LIKE', '%' . $term . '%')
                        ->orWhereHas('supplier', function ($anotherQuery) use ($term) {
                            $anotherQuery->where('name', 'LIKE', '%' . $term . '%')
                                ->orWhere('phone', 'LIKE', '%' . $term . '%');
                        });
                });
        });

        $purchaseReturns = PurchaseReturnListResource::collection($query->latest()->get())->map(function ($purchaseReturns) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                config('config.purchaseReturnPrefix') . ' - ' . $purchaseReturns->code,
                date('jS M, Y', strtotime($purchaseReturns->date)),
                $purchaseReturns->status ? 'Active' : 'Inactive',
                config('config.purchasePrefix') . ' - ' .  $purchaseReturns->purchase?->purchase_no,
                $purchaseReturns->purchase?->supplier?->name,
                $purchaseReturns->reason,
                $currencySymbol . strval($purchaseReturns->total_return),
            ];
        });

        $totalPaid = $purchaseReturns->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '',  $row[6] ?? 0));
        });

        // Add the total paid as a new row
        $purchaseReturns->push([
            '', '', '', '', '', '', 'Total Return = ' . getGeneralSettingsInfo()['currency']['symbol']  . $totalPaid,
        ]);

        return $purchaseReturns;
    }

    public function headings(): array
    {
        return [
            'Return No',
            'Date',
            'Status',
            'Purchase No',
            'Supplier',
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
