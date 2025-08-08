<?php

namespace App\Exports;

use App\Models\ProductSubCategory;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Http\Resources\ProductSubCategoryResource;

class ExportProductSubCategory implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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

        $query = ProductSubCategory::with('category')->where('name', 'LIKE', '%' . $term . '%')
            ->orWhere('note', 'LIKE', '%' . $term . '%')
            ->orWhereHas('category', function ($newQuery) use ($term) {
                $newQuery->where('name', 'LIKE', '%' . $term . '%');
            });

        $productSubCategories = ProductSubCategoryResource::collection($query->latest()->get())->map(function ($productSubCategory) {
            return [
                $productSubCategory->category?->name . '[' . config('config.proCatPrefix') . ' - ' . $productSubCategory->category?->code . ']',
                config('config.proSubCatPrefix') . ' - ' . $productSubCategory->code,
                $productSubCategory->name,
                $productSubCategory->status ? 'Active' : 'Inactive',
            ];
        });

        return $productSubCategories;
    }
    public function headings(): array
    {
        return [
            'Category',
            'Sub Category Code',
            'Sub Category Name',
            'Status',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Style the header row (headings)
                $event->getSheet()->getDelegate()->getStyle('A1:D1')->applyFromArray([
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
