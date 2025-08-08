<?php

namespace App\Exports;

use App\Models\ProductCategory;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Http\Resources\ProductCategoryResource;

class ExportProductCategory implements FromCollection,  WithHeadings, ShouldAutoSize, WithEvents
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

        $query = ProductCategory::where('name', 'LIKE', '%' . $term . '%')
            ->orWhere('slug', 'LIKE', '%' . $term . '%')
            ->orWhere('note', 'LIKE', '%' . $term . '%');

        $productCategories = ProductCategoryResource::collection($query->latest()->get())->map(function ($productCategory) {
            return [
                config('config.proCatPrefix') . ' - ' . $productCategory->code,
                $productCategory->name,
                $productCategory->note,
                $productCategory->status ? 'Active' : 'Inactive',
            ];
        });

        return $productCategories;
    }

    public function headings(): array
    {
        return [
            'Code',
            'Name',
            'Note',
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
