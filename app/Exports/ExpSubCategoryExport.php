<?php

namespace App\Exports;

use App\Models\ExpenseSubCategory;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExpSubCategoryExport implements FromCollection,  WithHeadings, ShouldAutoSize, WithStyles
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
        $query = ExpenseSubCategory::query()->with('expCategory')->latest();

        if ($term) {
            $query->where(function ($subQuery) use ($term) {
                $subQuery->where('name', 'LIKE', '%' . $term . '%')
                    ->orWhere('code', 'LIKE', '%' . $term . '%')
                    ->orWhere('note', 'LIKE', '%' . $term . '%')
                    ->orWhereHas('expCategory', function ($newQuery) use ($term) {
                        $newQuery->where('name', 'LIKE', '%' . $term . '%')
                            ->orWhere('code', 'LIKE', '%' . $term . '%');
                    });
            });
        }
        $expenseSubCategories = $query->get();

        return $expenseSubCategories->map(function ($expenseSubCategory) {
            return [
                $expenseSubCategory->expCategory->name . ' [' . config('config.expCatPrefix') . ' - '. $expenseSubCategory->expCategory->code . ']' ?? 'N/A',
                $expenseSubCategory->code ? config('config.expSubCatPrefix') . '-' . $expenseSubCategory->code : 'N/A',
                $expenseSubCategory->name ?? 'N/A',
                $expenseSubCategory->status ? 'Active' : 'Inactive',
                date('d-M-Y', strtotime($expenseSubCategory->created_at)),
            ];
        });
    }

    // excel file columns
    public function headings(): array
    {
        return [
            'Category Name',
            'Code',
            'SubCategory Name',
            'Status',
            'Created Date',
        ];
    }

    // Apply styles to the heading row
    public function styles($sheet)
    {
        return [
            1 => [
                'font' => [
                    'size' => 13,
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => [
                        'rgb' => '00FF00',
                    ],
                ],
            ],
        ];
    }
}
