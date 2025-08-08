<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Http\Resources\ProductListingResource;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportProduct implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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

        $query = Product::with('proSubCategory.category')->where('name', 'LIKE', '%' . $term . '%')
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

        $products = ProductListingResource::collection($query->latest()->get())->map(function ($product) {
            $currencySymbol = getGeneralSettingsInfo()['currency']['symbol'];
            return [
                $product->proSubCategory?->name . '[' . config('config.proSubCatPrefix') . ' - ' . $product->proSubCategory?->code . ']',
                $product->status ? 'Active' : 'Inactive',
                config('config.productPrefix') . ' - ' . $product->code,
                $product->name,
                $product->model,
                $product->productUnit?->code,
                $currencySymbol . strval($product->sellingPrice()),
            ];
        });

        return $products;
    }

    public function headings(): array
    {
        return [
            'Category',
            'Status',
            'Code',
            'Name',
            'Item Model',
            'Unit',
            'Selling Price',

        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
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
            },
        ];
    }
}
