<?php

namespace App\Exports;

use App\Models\Department;
use Maatwebsite\Excel\Events\AfterSheet;
use App\Http\Resources\DepartmentResource;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExportExployeeDepartment implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents
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

        $query = Department::where('name', 'LIKE', '%' . $term . '%')->orWhere('note', 'LIKE', '%' . $term . '%');
        $empDepartments = DepartmentResource::collection($query->latest()->get())->map(function ($empDepartment) {
            return [
                $empDepartment->name,
                $empDepartment->note,
                $empDepartment->status ? 'Active' : 'Inactive',

            ];
        });

        return $empDepartments;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Note',
            'Status'
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Style the header row (headings)
                $event->getSheet()->getDelegate()->getStyle('A1:C1')->applyFromArray([
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
