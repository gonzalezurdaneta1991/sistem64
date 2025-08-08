<?php

namespace App\Exports;

use App\Models\Product;
use App\Http\Resources\ProductResource;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportNonZeroInventory implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
{
    protected $term;

    public function __construct($term)
    {
        $this->term = $term;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $term = $this->term;
        $query = Product::with('proSubCategory.category', 'productUnit', 'productTax', 'productBrand')->where('inventory_count', '>', 0);
        $query = $query->where(function ($query) use ($term) {
            $query->where('name', 'LIKE', '%' . $term . '%')
                ->orWhere('slug', 'LIKE', '%' . $term . '%')
                ->orWhere('model', 'LIKE', '%' . $term . '%')
                ->orWhere('code', 'LIKE', '%' . $term . '%')
                ->orWhere('regular_price', 'LIKE', '%' . $term . '%')
                ->orWhere('purchase_price', 'LIKE', '%' . $term . '%')
                ->orWhereHas('proSubCategory', function ($newQuery) use ($term) {
                    $newQuery->where('name', 'LIKE', '%' . $term . '%')
                        ->orWhereHas('category', function ($newQuery) use ($term) {
                            $newQuery->where('name', 'LIKE', '%' . $term . '%');
                        });
                });
        });

        $nonZeroInventories = ProductResource::collection($query->latest()->get())->map(function ($nonZeroInventory) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                config('config.productPrefix') . ' - ' . $nonZeroInventory->code,
                $nonZeroInventory->status ? 'Active' : 'Inactive',
                $nonZeroInventory->name,
                $nonZeroInventory->model,
                $currencySymbol . strval($nonZeroInventory->purchase_price),
                $currencySymbol . strval($nonZeroInventory->sellingPrice()),
                $nonZeroInventory->inventory_count > 0 ? $nonZeroInventory->inventory_count : '0',
                $currencySymbol . strval($nonZeroInventory->purchase_price * $nonZeroInventory->inventory_count),
            ];
        });

        $totalStock = $nonZeroInventories->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[6]  ?? 0));
        });
        $totalInventoryValue = $nonZeroInventories->sum(function ($row) {
            return floatval(str_replace(getGeneralSettingsInfo()['currency']['symbol'], '', $row[7]  ?? 0));
        });

        // Add the total salary as a new row
        $nonZeroInventories->push([
            '', '', '', '', '', '', 'Total Stock = ' .  $totalStock, 'Total Value = ' . getGeneralSettingsInfo()['currency']['symbol'] . $totalInventoryValue,
        ]);

        return $nonZeroInventories;
    }

    public function headings(): array
    {
        return [
            'Code',
            'Status',
            'Name',
            'Item Model',
            'Avg. Purchase Price',
            'Selling Price',
            'Stock',
            'Inventory Value',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Get the last row index
                $lastRow = $event->getSheet()->getDelegate()->getHighestRow();

                // Style the last row (total amount row)
                $event->getSheet()->getDelegate()->getStyle('A' . $lastRow . ':H' . $lastRow)->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('A1:H1')->applyFromArray([
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
                $event->getSheet()->getDelegate()->getStyle('H1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);
                // Align column to the right
                $columnG = 'G';
                $event->getSheet()->getDelegate()->getStyle("{$columnG}2:{$columnG}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnH = 'H';
                $event->getSheet()->getDelegate()->getStyle("{$columnH}2:{$columnH}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
