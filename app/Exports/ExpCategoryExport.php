<?php

namespace App\Exports;

use App\Models\ExpenseCategory;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Illuminate\Support\Collection;

class ExpCategoryExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
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

        $query = ExpenseCategory::query()->latest();

        if ($term) {
            $query->where('name', 'LIKE', '%' . $term . '%')
                ->orWhere('slug', 'LIKE', '%' . $term . '%')
                ->orWhere('code', 'LIKE', '%' . $term . '%')
                ->orWhere('note', 'LIKE', '%' . $term . '%');
        }

        $expenseCategories = $query->get();

        return $expenseCategories->map(function ($expCategory) {
            return [
                $expCategory->name ?? 'N/A',
                $expCategory->code ? config('config.expCatPrefix') . '-' . $expCategory->code : 'N/A',
                $expCategory->note,
                $expCategory->status ? 'Active' : 'Inactive',
                date('d-M-Y', strtotime($expCategory->created_at)),
            ];
        });
    }

    // excel file columns
    public function headings(): array
    {
        return [
            'Name',
            'Code',
            'Note',
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
